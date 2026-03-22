<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\OtpVerification;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Custom logger for forgot-password flow.
     * Some environments have issues with Laravel logging; this guarantees file output.
     */
    private function forgotPasswordLog(string $level, string $message, array $context = []): void
    {
        try {
            $logFile = storage_path('logs/forgot_password_custom.log');

            $contextJson = !empty($context)
                ? ' | context=' . json_encode($context, JSON_UNESCAPED_SLASHES)
                : '';

            file_put_contents(
                $logFile,
                '[' . now()->toIso8601String() . '] ' . strtoupper($level) . ': ' . $message . $contextJson . PHP_EOL,
                FILE_APPEND
            );
        } catch (\Throwable $e) {
            // Intentionally ignore to avoid breaking forgot-password endpoint.
        }
    }

    /**
     * User Login with phone and password
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|min:10|max:15',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Normalize phone number (remove spaces, dashes, etc. for consistent matching)
        $phoneNumber = preg_replace('/[^0-9]/', '', trim($request->phone_number));
        $password = $request->password;

        // Log login attempt for debugging
        \Log::info('Login attempt', [
            'original_phone' => $request->phone_number,
            'normalized_phone' => $phoneNumber,
            'phone_length' => strlen($phoneNumber)
        ]);

        // Validate normalized phone number
        if (strlen($phoneNumber) < 10 || strlen($phoneNumber) > 15) {
            \Log::warning('Invalid phone format', ['phone' => $phoneNumber, 'length' => strlen($phoneNumber)]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number format'
            ], 422);
        }

        // Find user by phone number - use exact match only (after normalization)
        // Also check for soft deleted users
        $user = DB::table('users')
            ->where('phone_number', $phoneNumber)
            ->where('user_type', 'broker')
            ->whereNull('deleted_at')
            ->first();

        // If not found with exact match, try to find user with different format and normalize it
        if (!$user) {
            // Try to find user with phone that normalizes to the same number
            $allUsers = DB::table('users')
                ->whereNull('deleted_at')
                ->get();
            
            $matchingUser = null;
            foreach ($allUsers as $u) {
                $normalizedDbPhone = preg_replace('/[^0-9]/', '', trim($u->phone_number));
                if ($normalizedDbPhone === $phoneNumber) {
                    $matchingUser = $u;
                    break;
                }
            }
            
            if ($matchingUser) {
                if ($matchingUser->user_type === 'admin') {
                    return response()->json([
                        'success' => false,
                        'message' => 'This phone number is registered as an admin account. Please use admin login.'
                    ], 401);
                }
                
                if ($matchingUser->user_type !== 'broker') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number or password'
            ], 401);
                }
                
                if ($matchingUser->status !== 'active') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your account is not active. Please contact administrator.'
                    ], 403);
                }
                
                // Check password
                if (Hash::check($password, $matchingUser->password)) {
                    // Password matches but phone format in DB is different - normalize it
                    DB::table('users')->where('id', $matchingUser->id)->update([
                        'phone_number' => $phoneNumber,
                        'updated_at' => now()
                    ]);
                    $user = $matchingUser;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid phone number or password'
                    ], 401);
                }
            } else {
                \Log::warning('Login failed: User not found', [
                    'phone' => $phoneNumber,
                    'normalized_phone' => $phoneNumber
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number or password'
                ], 401);
            }
        }

        // Check password
        if (!Hash::check($password, $user->password)) {
            \Log::warning('Login failed: Password mismatch', [
                'user_id' => $user->id,
                'phone' => $phoneNumber,
                'password_provided' => !empty($password),
                'password_hash_exists' => !empty($user->password)
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number or password'
            ], 401);
        }
        
        \Log::info('Login successful', [
            'user_id' => $user->id,
            'phone' => $phoneNumber,
            'name' => $user->name
        ]);

        // Check if user is active
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active. Please contact administrator.'
            ], 403);
        }

        // Update last login
        DB::table('users')->where('id', $user->id)->update([
            'last_login_at' => now(),
            'updated_at' => now(),
        ]);

        // Get User model instance for Sanctum
        $userModel = \App\Models\User::find($user->id);
        if (!$userModel) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        // Generate Sanctum token
        $token = $userModel->createToken('mobile-app')->plainTextToken;

        // Get user data
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'broker_id' => $user->broker_id,
            'referral_code' => $user->referral_code,
            'user_type' => $user->user_type,
            'slab_id' => $user->slab_id,
            'status' => $user->status,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $userData,
            ]
        ]);
    }

    /**
     * User Registration
     */
    public function register(Request $request)
    {
        // Normalize phone number (remove spaces, dashes, etc.)
        $normalizedPhone = preg_replace('/[^0-9]/', '', trim($request->phone_number));
        
        $validator = Validator::make(array_merge($request->all(), ['phone_number' => $normalizedPhone]), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Check if phone exists (excluding soft deleted)
                    $exists = DB::table('users')
                        ->where('phone_number', $value)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($exists) {
                        $fail('The phone number has already been taken.');
                    }
                },
            ],
            'referral_code' => 'required|string|exists:users,referral_code',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find referrer
        $referrer = DB::table('users')->where('referral_code', $request->referral_code)->first();
        if (!$referrer) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid referral code'
            ], 400);
        }

        // Get default slab for new users using helper method
        $defaultSlab = User::getDefaultSlab();
        
        if (!$defaultSlab) {
            return response()->json([
                'success' => false,
                'message' => 'No active slabs found. Please contact administrator to create an active slab.'
            ], 500);
        }

        // Generate broker ID and referral code: SHOB + 5 digits, sequential starting from 00001
        // Both broker_id and referral_code use the same value
        $lastUser = DB::table('users')
            ->where('broker_id', 'like', 'SHOB%')
            ->orWhere('referral_code', 'like', 'SHOB%')
            ->orderBy('id', 'desc')
            ->first();
        
        $lastNumber = 0; // Start from 0, first user gets 00001
        if ($lastUser) {
            // Check broker_id first
            if ($lastUser->broker_id && strpos($lastUser->broker_id, 'SHOB') === 0) {
                $codeStr = substr($lastUser->broker_id, 4); // Get part after "SHOB"
                $lastNumber = (int)$codeStr;
            }
            // Check referral_code if broker_id didn't have SHOB format
            if ($lastNumber == 0 && $lastUser->referral_code && strpos($lastUser->referral_code, 'SHOB') === 0) {
                $codeStr = substr($lastUser->referral_code, 4); // Get part after "SHOB"
                $lastNumber = (int)$codeStr;
            }
        }
        
        $nextNumber = $lastNumber + 1;
        $brokerId = 'SHOB' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        $referralCode = $brokerId; // Same value for both
        
        // Safety check: ensure uniqueness
        while (DB::table('users')->where('broker_id', $brokerId)->orWhere('referral_code', $referralCode)->exists()) {
            $nextNumber++;
            $brokerId = 'SHOB' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
            $referralCode = $brokerId;
        }

        // Use provided email
        $email = $request->email;

        // Use provided password
        $password = $request->password;

        // Create user
        // Note: referred_by_code and referred_by_user_id establish the referral relationship
        // These fields track who shared the referral code and who joined via that referral
        // Commission distribution uses the referral chain (referred_by_user_id) to distribute referral commissions to parents
        $userId = DB::table('users')->insertGetId([
            'name' => $request->name,
            'email' => $email,
            'phone_number' => $normalizedPhone,
            'password' => Hash::make($password),
            'broker_id' => $brokerId,
            'referral_code' => $referralCode,
            'referred_by_code' => $request->referral_code, // Referral code used during registration
            'referred_by_user_id' => $referrer->id, // User who referred this new user (who shared the code)
            'slab_id' => $defaultSlab ? $defaultSlab->id : null,
            'user_type' => 'broker',
            'status' => 'active',
            'kyc_verified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create wallet for user
        DB::table('wallets')->insert([
            'user_id' => $userId,
            'balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
            'total_deposited' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create referral relationship (for tracking purposes only - no commission distribution)
        // This record tracks who referred whom and when they joined
        DB::table('referrals')->insert([
            'referrer_id' => $referrer->id,
            'referred_id' => $userId,
            'level' => 1,
            'commission_percentage' => 0.00, // Set to 0 as commission distribution is disabled
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update referrer's downline count (for tracking/reporting purposes)
        DB::table('users')->where('id', $referrer->id)->increment('total_downline_count');

        // Assign all initial slabs to the new user (one per property type)
        $newUser = User::find($userId);
        if ($newUser) {
            $newUser->assignAllInitialSlabs();
        }

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user_id' => $userId,
                'broker_id' => $brokerId,
                'referral_code' => $referralCode,
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
            ]
        ], 201);
    }

    /**
     * Admin Login
     */
    public function adminLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = DB::table('users')
            ->where('email', $request->email)
            ->where('user_type', 'admin')
            ->where('status', 'active')
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Update last login
        DB::table('users')->where('id', $user->id)->update([
            'last_login_at' => now(),
            'updated_at' => now(),
        ]);

        // Get User model instance for Sanctum
        $userModel = \App\Models\User::find($user->id);
        if (!$userModel) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        // Generate Sanctum token
        $token = $userModel->createToken('admin-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'broker_id' => $user->broker_id,
                    'user_type' => $user->user_type,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 200);
    }

    /**
     * Send forgot password OTP to phone number or email
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required_without:email|string|min:10|max:15',
            'email' => 'required_without:phone_number|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Normalize phone number if provided
        $phoneNumber = $request->filled('phone_number') 
            ? preg_replace('/[^0-9]/', '', trim($request->phone_number)) 
            : null;
        $email = $request->email ?? null;
        
        // Find user by phone number (preferred) or email
        $user = null;
        if ($phoneNumber) {
            $user = DB::table('users')
                ->where('phone_number', $phoneNumber)
                ->where('user_type', 'broker')
                ->whereNull('deleted_at')
                ->first();
        }
        
        if (!$user && $email) {
          $email = strtolower($email);
        
          $user = DB::table('users')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('user_type', 'broker')
            ->whereNull('deleted_at')
            ->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with the provided phone number or email'
            ], 404);
        }
        
        // Use phone number as primary identifier, fallback to email
        $identifier = $user->phone_number ?: $user->email;
        $sendToEmail = $user->email;

        // Generate 6-digit OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Delete any existing OTP for this identifier
        OtpVerification::where('phone_number', $identifier)
            ->where('type', 'password_reset')
            ->delete();

        // Create new OTP record (using phone_number or email as identifier)
        OtpVerification::create([
            'phone_number' => $identifier,
            'otp' => $otp,
            'type' => 'password_reset',
            'is_verified' => false,
            'expires_at' => Carbon::now()->addMinutes(10), // OTP valid for 10 minutes
            'attempts' => 0,
        ]);

        // Send OTP via email if email exists, otherwise log it
        if ($sendToEmail) {
        try {
            $mailDriver = config('mail.default', 'log');
            
            // Use Mail facade with proper view
                Mail::send([], [], function ($message) use ($sendToEmail, $otp) {
                    $message->to($sendToEmail)
                    ->from(config('mail.from.address', 'noreply@shrihariomgroup.com'), config('mail.from.name', 'Shri Hari Om'))
                    ->subject('Password Reset OTP - Shri Hari Om')
                    ->html("
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                        </head>
                        <body style='font-family: Arial, sans-serif; padding: 20px; background-color: #f5f5f5;'>
                            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                                <h2 style='color: #9333EA; margin-top: 0;'>Password Reset Request</h2>
                                <p>You have requested to reset your password for Shri Hari Om Real Estate MLM Platform.</p>
                                <div style='background-color: #f9f9f9; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0;'>
                                    <p style='margin: 0 0 10px 0; color: #666;'>Your OTP is:</p>
                                    <p style='font-size: 32px; font-weight: bold; color: #9333EA; margin: 0; letter-spacing: 5px;'>{$otp}</p>
                                </div>
                                <p>This OTP is valid for <strong>10 minutes</strong>.</p>
                                <p style='color: #ff0000;'>If you did not request this, please ignore this email.</p>
                                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                                <p style='color: #666; font-size: 12px; margin: 0;'>This is an automated message, please do not reply.</p>
                            </div>
                        </body>
                        </html>
                    ");
            });
            
                $this->forgotPasswordLog('info', 'Password reset OTP sent (email).', [
                    'send_to_email' => $sendToEmail,
                    'mail_driver' => $mailDriver,
                ]);
        } catch (\Exception $e) {
            $this->forgotPasswordLog('error', 'Failed to send password reset OTP email.', [
                'error' => $e->getMessage(),
                'mail_driver' => config('mail.default', 'not set'),
                'mail_from_address' => config('mail.from.address', 'not set'),
            ]);
            
            // If using log driver, still return success (for development)
            if (config('mail.default') === 'log') {
                    $this->forgotPasswordLog('info', 'OTP generated and email logging enabled (mail driver log).', [
                        'send_to_email' => $sendToEmail,
                        'otp' => $otp,
                    ]);
                return response()->json([
                    'success' => true,
                        'message' => 'OTP has been generated. Check logs for OTP (mail driver is set to log).',
                        'otp' => $otp // Return OTP in development mode
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please check mail configuration and try again later.'
            ], 500);
        }
        } else {
            // No email, log OTP for admin to provide to user
            $this->forgotPasswordLog('info', 'Password reset OTP generated (phone only, no email configured).', [
                'identifier' => $identifier,
                'otp' => $otp,
            ]);
        }

        // Return OTP in development mode or when using log driver
        $appEnv = config('app.env');
        $appDebug = config('app.debug');
        $mailDriver = config('mail.default');
        $isDevelopment = ($appEnv === 'local' || $appDebug === true);
        $isLogDriver = ($mailDriver === 'log');
        $shouldReturnOtp = $isDevelopment || $isLogDriver;
        
        // Log for debugging
        $this->forgotPasswordLog('info', 'Forgot Password - OTP Generation summary.', [
            'identifier' => $identifier,
            'email' => $sendToEmail,
            'app_env' => $appEnv,
            'app_debug' => $appDebug,
            'mail_driver' => $mailDriver,
            'should_return_otp' => $shouldReturnOtp,
            'otp' => $shouldReturnOtp ? $otp : 'HIDDEN',
        ]);

        return response()->json([
            'success' => true,
            'message' => $sendToEmail 
                ? 'OTP has been sent to your email address' 
                : 'OTP has been generated. Please contact administrator.',
            'otp' => $shouldReturnOtp ? $otp : null, // Return OTP in development/log mode only
        ]);
    }

    /**
     * Verify OTP for password reset
     */
    public function verifyPasswordResetOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required_without:email|string|min:10|max:15',
            'email' => 'required_without:phone_number|email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Normalize phone number if provided
        $phoneNumber = $request->filled('phone_number') 
            ? preg_replace('/[^0-9]/', '', trim($request->phone_number)) 
            : null;
        $email = $request->email ?? null;
        $otp = $request->otp;

        // Find user first to get the correct identifier (same logic as forgotPassword)
        $user = null;
        if ($phoneNumber) {
            $user = DB::table('users')
                ->where('phone_number', $phoneNumber)
                ->where('user_type', 'broker')
                ->whereNull('deleted_at')
                ->first();
        }
        
        if (!$user && $email) {
            $user = DB::table('users')
                ->where('email', $email)
                ->where('user_type', 'broker')
                ->whereNull('deleted_at')
                ->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Use the same identifier logic as forgotPassword: phone_number if exists, else email
        $identifier = $user->phone_number ?: $user->email;

        \Log::info("Verify Password Reset OTP", [
            'request_phone' => $phoneNumber,
            'request_email' => $email,
            'user_phone' => $user->phone_number,
            'user_email' => $user->email,
            'identifier_used' => $identifier,
            'otp_provided' => $otp
        ]);

        // Find OTP record using the same identifier that was used when creating it
        $otpRecord = OtpVerification::where('phone_number', $identifier)
            ->where('type', 'password_reset')
            ->where('otp', $otp)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otpRecord) {
            // Log for debugging
            $existingOtp = OtpVerification::where('phone_number', $identifier)
                ->where('type', 'password_reset')
                ->first();
            
            \Log::warning("OTP Verification Failed", [
                'identifier' => $identifier,
                'otp_provided' => $otp,
                'existing_otp' => $existingOtp ? $existingOtp->otp : 'none',
                'existing_expires_at' => $existingOtp ? $existingOtp->expires_at : 'none',
                'is_expired' => $existingOtp ? ($existingOtp->expires_at <= Carbon::now()) : 'no record'
            ]);

            // Increment attempts
            OtpVerification::where('phone_number', $identifier)
                ->where('type', 'password_reset')
                ->increment('attempts');

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ], 400);
        }

        // Mark OTP as verified
        $otpRecord->update([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully'
        ]);
    }

    /**
     * Reset password using verified OTP
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required_without:email|string|min:10|max:15',
            'email' => 'required_without:phone_number|email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Normalize phone number if provided
        $phoneNumber = $request->filled('phone_number') 
            ? preg_replace('/[^0-9]/', '', trim($request->phone_number)) 
            : null;
        $email = $request->email ?? null;
        $otp = $request->otp;
        $password = $request->password;

        // Find user first to get the correct identifier (same logic as forgotPassword)
        $user = null;
        if ($phoneNumber) {
            $user = DB::table('users')
                ->where('phone_number', $phoneNumber)
                ->where('user_type', 'broker')
                ->whereNull('deleted_at')
                ->first();
        }
        
        if (!$user && $email) {
            $user = DB::table('users')
                ->where('email', $email)
                ->where('user_type', 'broker')
                ->whereNull('deleted_at')
                ->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Use the same identifier logic as forgotPassword: phone_number if exists, else email
        $identifier = $user->phone_number ?: $user->email;

        // Verify OTP is valid and verified using the same identifier that was used when creating it
        $otpRecord = OtpVerification::where('phone_number', $identifier)
            ->where('type', 'password_reset')
            ->where('otp', $otp)
            ->where('is_verified', true)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or unverified OTP. Please request a new OTP.'
            ], 400);
        }

        // Update password
        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'password' => Hash::make($password),
                'updated_at' => now(),
            ]);

        // Delete OTP record
        $otpRecord->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
                'errors' => ['current_password' => ['Current password is incorrect']]
            ], 422);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }
}
