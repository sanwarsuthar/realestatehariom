<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\PropertyType;
use App\Models\Slab;

class RandomUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all existing users (including admin) to randomly assign as referrers
        $existingUsers = DB::table('users')
            ->where('user_type', '!=', 'admin')
            ->whereNotNull('referral_code')
            ->get();
        
        // If no users exist, get admin as referrer
        if ($existingUsers->isEmpty()) {
            $adminUser = DB::table('users')->where('user_type', 'admin')->first();
            if ($adminUser) {
                $existingUsers = collect([$adminUser]);
            }
        }
        
        if ($existingUsers->isEmpty()) {
            $this->command->error('No existing users found to assign referrals. Please create at least one user first.');
            return;
        }
        
        // Get all initial slabs for each property type
        $propertyTypes = PropertyType::where('is_active', true)->orderBy('name')->get();
        $initialSlabs = [];
        
        foreach ($propertyTypes as $propertyType) {
            $firstSlab = Slab::where('is_active', true)
                ->whereHas('propertyTypes', function($query) use ($propertyType) {
                    $query->where('property_types.id', $propertyType->id);
                })
                ->orderBy('sort_order')
                ->first();
            
            if ($firstSlab) {
                $initialSlabs[] = $firstSlab;
                $this->command->info("✓ Found initial slab for {$propertyType->name}: {$firstSlab->name} (ID: {$firstSlab->id})");
            }
        }
        
        if (empty($initialSlabs)) {
            // Fallback: get any active slab
            $fallbackSlab = Slab::where('is_active', true)->orderBy('sort_order')->first();
            if ($fallbackSlab) {
                $initialSlabs[] = $fallbackSlab;
                $this->command->warn("⚠️  No property-type-specific slabs found. Using fallback slab: {$fallbackSlab->name}");
            } else {
                $this->command->error('❌ No active slabs found. Please create slabs first.');
                return;
            }
        }
        
        // Use the first property type's initial slab as default (same as User::getDefaultSlab())
        $defaultSlab = $initialSlabs[0];
        $this->command->info("📌 Available initial slabs: " . implode(', ', array_map(function($s) { return $s->name; }, $initialSlabs)));
        $this->command->info("📌 Users will be distributed across these initial slabs");
        
        // Generate broker ID and referral code: SHOB + 5 digits, sequential starting from 00001
        // Both broker_id and referral_code use the same value
        $lastUser = DB::table('users')
            ->where(function($query) {
                $query->where('broker_id', 'like', 'SHOB%')
                      ->orWhere('referral_code', 'like', 'SHOB%');
            })
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
        
        // Random first names and last names
        $firstNames = [
            'Raj', 'Priya', 'Amit', 'Sneha', 'Vikram', 'Anjali', 'Rahul', 'Kavya',
            'Suresh', 'Meera', 'Arjun', 'Divya', 'Karan', 'Pooja', 'Rohan', 'Neha',
            'Vishal', 'Shreya', 'Aditya', 'Ananya', 'Kunal', 'Isha', 'Nikhil', 'Riya',
            'Manish', 'Tanvi', 'Gaurav', 'Sakshi', 'Harsh', 'Aishwarya', 'Yash', 'Kriti',
            'Abhishek', 'Swati', 'Siddharth', 'Aditi', 'Varun', 'Mansi', 'Ritesh', 'Nidhi',
            'Pankaj', 'Shilpa', 'Deepak', 'Jyoti', 'Manoj', 'Preeti', 'Sunil', 'Rekha',
            'Anil', 'Sarita', 'Vijay', 'Lata', 'Mahesh', 'Geeta', 'Ramesh', 'Suman'
        ];
        
        $lastNames = [
            'Sharma', 'Patel', 'Singh', 'Kumar', 'Gupta', 'Verma', 'Yadav', 'Shah',
            'Mehta', 'Jain', 'Agarwal', 'Reddy', 'Rao', 'Nair', 'Iyer', 'Menon',
            'Pillai', 'Nair', 'Menon', 'Krishnan', 'Sundaram', 'Venkatesh', 'Raman',
            'Srinivasan', 'Subramanian', 'Lakshmi', 'Devi', 'Bai', 'Kumari', 'Das',
            'Bose', 'Banerjee', 'Chatterjee', 'Mukherjee', 'Ghosh', 'Sen', 'Roy',
            'Dutta', 'Basu', 'Chakraborty', 'Mandal', 'Sarkar', 'Mondal', 'Haldar'
        ];
        
        $password = Hash::make('11223344');
        $created = 0;
        
        $this->command->info('Creating 50 random users with initial slabs assigned...');
        
        for ($i = 1; $i <= 50; $i++) {
            // Generate broker ID and referral code: SHOB + 5 digits, sequential starting from 00001
            // Both broker_id and referral_code use the same value
            $lastNumber++;
            $brokerId = 'SHOB' . str_pad($lastNumber, 5, '0', STR_PAD_LEFT);
            $referralCode = $brokerId; // Same value for both
            
            // Ensure uniqueness
            while (DB::table('users')->where('broker_id', $brokerId)->orWhere('referral_code', $referralCode)->exists()) {
                $lastNumber++;
                $brokerId = 'SHOB' . str_pad($lastNumber, 5, '0', STR_PAD_LEFT);
                $referralCode = $brokerId;
            }
            
            // Random name
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $name = $firstName . ' ' . $lastName;
            
            // Generate unique email
            $email = strtolower($firstName . '.' . $lastName . '.' . $lastBrokerNumber . '@example.com');
            $emailCounter = 1;
            while (DB::table('users')->where('email', $email)->exists()) {
                $email = strtolower($firstName . '.' . $lastName . '.' . $lastBrokerNumber . '.' . $emailCounter . '@example.com');
                $emailCounter++;
            }
            
            // Generate unique phone number (10 digits, starting with 7, 8, or 9)
            $phonePrefix = [7, 8, 9][array_rand([7, 8, 9])];
            $phoneNumber = $phonePrefix . str_pad(rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
            $phoneCounter = 0;
            while (DB::table('users')->where('phone_number', $phoneNumber)->exists() && $phoneCounter < 100) {
                $phonePrefix = [7, 8, 9][array_rand([7, 8, 9])];
                $phoneNumber = $phonePrefix . str_pad(rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
                $phoneCounter++;
            }
            
            // Randomly select a referrer from existing users
            $referrer = $existingUsers->random();
            $referredByCode = $referrer->referral_code;
            $referredByUserId = $referrer->id;
            
            // Use first property type's initial slab as primary slab_id
            $primarySlab = $initialSlabs[0];
            
            // Create user
            $userId = DB::table('users')->insertGetId([
                'name' => $name,
                'email' => $email,
                'phone_number' => $phoneNumber,
                'password' => $password,
                'broker_id' => $brokerId,
                'referral_code' => $referralCode,
                'referred_by_code' => $referredByCode,
                'referred_by_user_id' => $referredByUserId,
                'slab_id' => $primarySlab->id,
                'user_type' => 'broker',
                'status' => 'active',
                'kyc_verified' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Assign ALL initial slabs to this user (one per property type)
            $user = User::find($userId);
            if ($user) {
                $user->assignAllInitialSlabs();
            }
            
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
            
            // Create referral relationship
            DB::table('referrals')->insert([
                'referrer_id' => $referredByUserId,
                'referred_id' => $userId,
                'level' => 1,
                'commission_percentage' => 0.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Update referrer's downline count
            DB::table('users')->where('id', $referredByUserId)->increment('total_downline_count');
            
            // Add this new user to the list of potential referrers for next users
            $newUser = (object)[
                'id' => $userId,
                'referral_code' => $referralCode,
            ];
            $existingUsers->push($newUser);
            
            $created++;
            
            if ($i % 10 == 0) {
                $this->command->info("Created {$i} users...");
            }
        }
        
        $this->command->info("✅ Successfully created {$created} random users!");
        $this->command->info("All users have password: 11223344");
        $this->command->info("✅ All users assigned ALL initial slabs (one per property type)");
        
        // Show summary
        $this->command->info("\n📊 Initial Slabs Assigned:");
        foreach ($propertyTypes as $propertyType) {
            $initialSlab = Slab::where('is_active', true)
                ->whereHas('propertyTypes', function($query) use ($propertyType) {
                    $query->where('property_types.id', $propertyType->id);
                })
                ->orderBy('sort_order')
                ->first();
            
            if ($initialSlab) {
                $count = DB::table('user_slabs')
                    ->where('property_type_id', $propertyType->id)
                    ->where('slab_id', $initialSlab->id)
                    ->count();
                $this->command->info("  - {$propertyType->name}: {$initialSlab->name} ({$count} users)");
            }
        }
    }
}
