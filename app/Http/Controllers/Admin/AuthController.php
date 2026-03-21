<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Show admin login form
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        
        return view('admin.auth.login');
    }

    /**
     * Handle admin login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $credentials = $request->only('email', 'password');
        
        // Check if user is admin
        $user = DB::table('users')
            ->where('email', $credentials['email'])
            ->where('user_type', 'admin')
            ->where('status', 'active')
            ->first();

            
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
           
            return back()->withErrors([
                'email' => 'Invalid credentials or insufficient permissions.',
            ])->withInput($request->except('password'));
        }

        // Manually log in the user
        Auth::loginUsingId($user->id);

        // Update last login
        DB::table('users')->where('id', $user->id)->update([
            'last_login_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Handle admin logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('admin.login');
    }
}
