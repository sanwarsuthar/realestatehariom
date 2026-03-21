<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MLMNetworkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Builds a proper MLM network starting from admin
     */
    public function run(): void
    {
        $this->command->info('Building MLM Network starting from Admin...');

        // Get admin user
        $admin = DB::table('users')->where('user_type', 'admin')->first();
        if (!$admin) {
            $this->command->error('Admin user not found. Please run AdminUserSeeder first.');
            return;
        }

        // Get Bronze slab
        $bronzeSlab = DB::table('slabs')->where('name', 'Bronze')->first();
        if (!$bronzeSlab) {
            $this->command->error('Bronze slab not found. Please run SlabSeeder first.');
            return;
        }

        // Get the last broker ID number
        $lastUser = DB::table('users')
            ->where('broker_id', 'like', 'SH%')
            ->where('user_type', 'broker')
            ->orderBy('id', 'desc')
            ->first();
        
        $currentBrokerNumber = $lastUser ? (int)substr($lastUser->broker_id, 2) + 1 : 1001;

        // Indian names for realistic data
        $firstNames = [
            'Rajesh', 'Priya', 'Amit', 'Sunita', 'Vikram', 'Kavita', 'Ravi', 'Meera',
            'Suresh', 'Anita', 'Kumar', 'Pooja', 'Deepak', 'Rekha', 'Manoj', 'Sushma',
            'Naveen', 'Geeta', 'Prakash', 'Lata', 'Vijay', 'Shanti', 'Ramesh', 'Usha',
            'Arun', 'Suman', 'Raj', 'Neha', 'Sanjay', 'Rita', 'Vinod', 'Kiran',
            'Ashok', 'Manju', 'Dilip', 'Seema', 'Jagdish', 'Renu', 'Bharat', 'Sarla',
            'Hari', 'Pushpa', 'Ram', 'Kumari', 'Shyam', 'Radha', 'Krishna', 'Ganga',
            'Anil', 'Kalpana', 'Mahesh', 'Sneha', 'Pankaj', 'Divya', 'Rohit', 'Swati'
        ];
        
        $lastNames = [
            'Sharma', 'Verma', 'Gupta', 'Singh', 'Kumar', 'Yadav', 'Patel', 'Jain',
            'Agarwal', 'Mishra', 'Pandey', 'Tiwari', 'Reddy', 'Rao', 'Naidu', 'Nair',
            'Menon', 'Iyer', 'Iyengar', 'Bhatt', 'Joshi', 'Desai', 'Mehta', 'Shah'
        ];

        $cities = ['Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad'];
        $states = ['Maharashtra', 'Delhi', 'Karnataka', 'Telangana', 'Tamil Nadu', 'West Bengal', 'Gujarat', 'Rajasthan'];

        // Build a proper tree structure:
        // Level 1: 5 users directly referred by admin
        // Level 2: Each level 1 user refers 2 users (10 total)
        // Level 3: Each level 2 user refers 2 users (20 total)
        // Level 4: Each level 3 user refers 2 users (40 total)
        // Level 5: Each level 4 user refers 2 users (80 total)
        // Continue building deeper levels...

        $levelUsers = []; // Track users at each level
        $userCounter = 0;

        // Level 1: 5 users referred by admin
        $this->command->info('Creating Level 1 users (directly from Admin)...');
        $level1Users = [];
        for ($i = 0; $i < 5; $i++) {
            $user = $this->createUser($currentBrokerNumber++, $admin->id, $admin->referral_code, $bronzeSlab, $firstNames, $lastNames, $cities, $states);
            $level1Users[] = $user['id'];
            $userCounter++;
        }
        $this->command->info("Created Level 1: 5 users");

        // Level 2: Each level 1 user refers 2 users
        $this->command->info('Creating Level 2 users...');
        $level2Users = [];
        foreach ($level1Users as $referrerId) {
            for ($i = 0; $i < 2; $i++) {
                $referrer = DB::table('users')->where('id', $referrerId)->first();
                $user = $this->createUser($currentBrokerNumber++, $referrerId, $referrer->referral_code, $bronzeSlab, $firstNames, $lastNames, $cities, $states);
                $level2Users[] = $user['id'];
                $userCounter++;
            }
        }
        $this->command->info("Created Level 2: " . count($level2Users) . " users");

        // Level 3: Each level 2 user refers 2 users
        $this->command->info('Creating Level 3 users...');
        $level3Users = [];
        foreach ($level2Users as $referrerId) {
            for ($i = 0; $i < 2; $i++) {
                $referrer = DB::table('users')->where('id', $referrerId)->first();
                $user = $this->createUser($currentBrokerNumber++, $referrerId, $referrer->referral_code, $bronzeSlab, $firstNames, $lastNames, $cities, $states);
                $level3Users[] = $user['id'];
                $userCounter++;
            }
        }
        $this->command->info("Created Level 3: " . count($level3Users) . " users");

        // Level 4: Each level 3 user refers 2 users
        $this->command->info('Creating Level 4 users...');
        $level4Users = [];
        foreach ($level3Users as $referrerId) {
            for ($i = 0; $i < 2; $i++) {
                $referrer = DB::table('users')->where('id', $referrerId)->first();
                $user = $this->createUser($currentBrokerNumber++, $referrerId, $referrer->referral_code, $bronzeSlab, $firstNames, $lastNames, $cities, $states);
                $level4Users[] = $user['id'];
                $userCounter++;
            }
        }
        $this->command->info("Created Level 4: " . count($level4Users) . " users");

        // Level 5: Each level 4 user refers 2 users
        $this->command->info('Creating Level 5 users...');
        $level5Users = [];
        foreach ($level4Users as $referrerId) {
            for ($i = 0; $i < 2; $i++) {
                $referrer = DB::table('users')->where('id', $referrerId)->first();
                $user = $this->createUser($currentBrokerNumber++, $referrerId, $referrer->referral_code, $bronzeSlab, $firstNames, $lastNames, $cities, $states);
                $level5Users[] = $user['id'];
                $userCounter++;
            }
        }
        $this->command->info("Created Level 5: " . count($level5Users) . " users");

        // Level 6-10: Continue building deeper levels
        $previousLevelUsers = $level5Users;
        for ($level = 6; $level <= 10; $level++) {
            $currentLevelUsers = [];
            foreach ($previousLevelUsers as $referrerId) {
                // Each user refers 2 new users
                for ($i = 0; $i < 2; $i++) {
                    $referrer = DB::table('users')->where('id', $referrerId)->first();
                    $user = $this->createUser($currentBrokerNumber++, $referrerId, $referrer->referral_code, $bronzeSlab, $firstNames, $lastNames, $cities, $states);
                    $currentLevelUsers[] = $user['id'];
                    $userCounter++;
                }
            }
            $this->command->info("Created Level {$level}: " . count($currentLevelUsers) . " users");
            $previousLevelUsers = $currentLevelUsers;
        }

        $this->command->info("Successfully created {$userCounter} users in MLM network structure!");
        $this->command->info("Admin referral code: ADMIN001");
    }

    private function createUser($brokerNumber, $referredByUserId, $referredByCode, $bronzeSlab, $firstNames, $lastNames, $cities, $states)
    {
        // Generate user data
        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        $name = $firstName . ' ' . $lastName;
        $email = strtolower($firstName . '.' . $lastName . '.' . $brokerNumber . '@example.com');
        
        // Ensure unique email
        while (DB::table('users')->where('email', $email)->exists()) {
            $email = strtolower($firstName . '.' . $lastName . '.' . $brokerNumber . '.' . rand(1000, 9999) . '@example.com');
        }

        $brokerId = 'SH' . str_pad($brokerNumber, 7, '0', STR_PAD_LEFT);
        $referralCode = 'REF' . strtoupper(Str::random(8));
        
        // Ensure unique referral code
        while (DB::table('users')->where('referral_code', $referralCode)->exists()) {
            $referralCode = 'REF' . strtoupper(Str::random(8));
        }

        $phoneNumber = '9' . rand(100000000, 999999999);
        // Ensure unique phone
        while (DB::table('users')->where('phone_number', $phoneNumber)->exists()) {
            $phoneNumber = '9' . rand(100000000, 999999999);
        }

        $city = $cities[array_rand($cities)];
        $state = $states[array_rand($states)];

        // Create user
        $userId = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password123'),
            'user_type' => 'broker',
            'status' => 'active',
            'broker_id' => $brokerId,
            'phone_number' => $phoneNumber,
            'referral_code' => $referralCode,
            'referred_by_code' => $referredByCode,
            'referred_by_user_id' => $referredByUserId,
            'slab_id' => $bronzeSlab->id,
            'kyc_verified' => false,
            'total_business_volume' => rand(0, 100000) / 100,
            'total_commission_earned' => rand(0, 10000) / 100,
            'total_downline_count' => 0,
            'address' => rand(1, 999) . ', ' . $firstName . ' Street, ' . $city,
            'city' => $city,
            'state' => $state,
            'pincode' => rand(100000, 999999),
            'created_at' => Carbon::now()->subDays(rand(0, 180)),
            'updated_at' => Carbon::now(),
        ]);

        // Create wallet
        DB::table('wallets')->insert([
            'user_id' => $userId,
            'balance' => rand(0, 50000) / 100,
            'total_earned' => rand(0, 50000) / 100,
            'total_withdrawn' => rand(0, 20000) / 100,
            'total_deposited' => rand(0, 30000) / 100,
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Update referrer's downline count
        DB::table('users')->where('id', $referredByUserId)->increment('total_downline_count');

        return ['id' => $userId, 'broker_id' => $brokerId, 'referral_code' => $referralCode];
    }
}

