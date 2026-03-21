<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MultiLevelUsersSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating multi-level MLM network users...');

        // Get Bronze slab (default for new users)
        $bronzeSlab = DB::table('slabs')->where('name', 'Bronze')->first();
        if (!$bronzeSlab) {
            $this->command->error('Bronze slab not found. Please run SlabSeeder first.');
            return;
        }

        // Get existing users to build upon
        $existingUsers = DB::table('users')
            ->where('user_type', 'broker')
            ->orderBy('id', 'desc')
            ->get();

        // Get the last broker ID number
        $lastUser = DB::table('users')
            ->where('broker_id', 'like', 'SH%')
            ->orderBy('id', 'desc')
            ->first();
        
        $lastBrokerNumber = $lastUser ? (int)substr($lastUser->broker_id, 2) : 1000;
        $currentBrokerNumber = $lastBrokerNumber + 1;

        // Indian names for realistic data
        $firstNames = [
            'Rajesh', 'Priya', 'Amit', 'Sunita', 'Vikram', 'Kavita', 'Ravi', 'Meera',
            'Suresh', 'Anita', 'Kumar', 'Pooja', 'Deepak', 'Rekha', 'Manoj', 'Sushma',
            'Naveen', 'Geeta', 'Prakash', 'Lata', 'Vijay', 'Shanti', 'Ramesh', 'Usha',
            'Arun', 'Suman', 'Raj', 'Neha', 'Sanjay', 'Rita', 'Vinod', 'Kiran',
            'Ashok', 'Manju', 'Dilip', 'Seema', 'Jagdish', 'Renu', 'Bharat', 'Sarla',
            'Hari', 'Pushpa', 'Ram', 'Kumari', 'Shyam', 'Radha', 'Krishna', 'Ganga',
            'Vishnu', 'Lakshmi', 'Shiva', 'Parvati', 'Brahma', 'Saraswati', 'Ganesha', 'Durga',
            'Anil', 'Kalpana', 'Mahesh', 'Sneha', 'Pankaj', 'Divya', 'Rohit', 'Swati',
            'Nikhil', 'Pallavi', 'Sachin', 'Ananya', 'Rahul', 'Isha', 'Karan', 'Tara'
        ];
        
        $lastNames = [
            'Sharma', 'Verma', 'Gupta', 'Singh', 'Kumar', 'Yadav', 'Patel', 'Jain',
            'Agarwal', 'Mishra', 'Pandey', 'Tiwari', 'Reddy', 'Rao', 'Naidu', 'Nair',
            'Menon', 'Iyer', 'Iyengar', 'Bhatt', 'Joshi', 'Desai', 'Mehta', 'Shah',
            'Trivedi', 'Chauhan', 'Rathore', 'Solanki', 'Malhotra', 'Kapoor', 'Khanna', 'Bansal'
        ];

        $cities = [
            'Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad',
            'Jaipur', 'Surat', 'Lucknow', 'Kanpur', 'Nagpur', 'Indore', 'Thane', 'Bhopal'
        ];

        $states = [
            'Maharashtra', 'Delhi', 'Karnataka', 'Telangana', 'Tamil Nadu', 'West Bengal',
            'Gujarat', 'Rajasthan', 'Uttar Pradesh', 'Madhya Pradesh', 'Andhra Pradesh',
            'Bihar', 'Punjab', 'Haryana', 'Kerala', 'Odisha'
        ];

        // Structure: Level 1 -> Level 2 -> ... -> Level 10
        // Each level will have users referring to users in the previous level
        // Level 1: 10 users (referred by existing users or admin)
        // Level 2: 20 users (2 per level 1 user)
        // Level 3: 40 users (2 per level 2 user)
        // Level 4: 80 users (2 per level 3 user)
        // Level 5: 100 users (distributed)
        // Level 6-10: 50 users each level (distributed)

        $usersByLevel = [];
        $allUsers = $existingUsers->pluck('id')->toArray();
        $userCounter = 0;

        // Level 1: Get 10 referrers from existing users (or use all if less than 10)
        $level1Referrers = $existingUsers->take(10)->pluck('id')->toArray();
        if (empty($level1Referrers)) {
            // If no existing users, we'll need to create level 1 users first
            $this->command->info('No existing users found. Creating level 1 users first...');
            $level1Referrers = $this->createLevel1Users($bronzeSlab, $currentBrokerNumber, $firstNames, $lastNames, $cities, $states);
            $currentBrokerNumber += 10;
            $userCounter += 10;
        }

        // Create Level 1 users (10 users)
        $level1Users = [];
        for ($i = 0; $i < 10; $i++) {
            $referrerId = $level1Referrers[$i % count($level1Referrers)];
            $user = $this->createUser($currentBrokerNumber++, $referrerId, $bronzeSlab, $firstNames, $lastNames, $cities, $states);
            $level1Users[] = $user['id'];
            $userCounter++;
        }
        $this->command->info("Created Level 1: 10 users");

        // Level 2: 20 users (2 per level 1 user)
        $level2Users = [];
        for ($i = 0; $i < 20; $i++) {
            $referrerId = $level1Users[$i % count($level1Users)];
            $user = $this->createUser($currentBrokerNumber++, $referrerId, $bronzeSlab, $firstNames, $lastNames, $cities, $states);
            $level2Users[] = $user['id'];
            $userCounter++;
        }
        $this->command->info("Created Level 2: 20 users");

        // Level 3: 40 users (2 per level 2 user)
        $level3Users = [];
        for ($i = 0; $i < 40; $i++) {
            $referrerId = $level2Users[$i % count($level2Users)];
            $user = $this->createUser($currentBrokerNumber++, $referrerId, $bronzeSlab, $firstNames, $lastNames, $cities, $states);
            $level3Users[] = $user['id'];
            $userCounter++;
        }
        $this->command->info("Created Level 3: 40 users");

        // Level 4: 80 users (2 per level 3 user)
        $level4Users = [];
        for ($i = 0; $i < 80; $i++) {
            $referrerId = $level3Users[$i % count($level3Users)];
            $user = $this->createUser($currentBrokerNumber++, $referrerId, $bronzeSlab, $firstNames, $lastNames, $cities, $states);
            $level4Users[] = $user['id'];
            $userCounter++;
        }
        $this->command->info("Created Level 4: 80 users");

        // Level 5: 100 users (distributed among level 4)
        $level5Users = [];
        for ($i = 0; $i < 100; $i++) {
            $referrerId = $level4Users[$i % count($level4Users)];
            $user = $this->createUser($currentBrokerNumber++, $referrerId, $bronzeSlab, $firstNames, $lastNames, $cities, $states);
            $level5Users[] = $user['id'];
            $userCounter++;
        }
        $this->command->info("Created Level 5: 100 users");

        // Levels 6-10: 50 users each (distributed among previous level)
        $previousLevelUsers = $level5Users;
        
        for ($level = 6; $level <= 10; $level++) {
            $currentLevelUsers = [];
            for ($i = 0; $i < 50; $i++) {
                $referrerId = $previousLevelUsers[$i % count($previousLevelUsers)];
                $user = $this->createUser($currentBrokerNumber++, $referrerId, $bronzeSlab, $firstNames, $lastNames, $cities, $states);
                $currentLevelUsers[] = $user['id'];
                $userCounter++;
            }
            $this->command->info("Created Level {$level}: 50 users");
            $previousLevelUsers = $currentLevelUsers;
        }

        $this->command->info("Successfully created {$userCounter} users across 10 levels!");
    }

    private function createUser($brokerNumber, $referredByUserId, $bronzeSlab, $firstNames, $lastNames, $cities, $states)
    {
        // Get referrer info
        $referrer = DB::table('users')->where('id', $referredByUserId)->first();
        $referredByCode = $referrer ? $referrer->referral_code : 'ADMIN001';

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
        if ($referrer) {
            DB::table('users')->where('id', $referredByUserId)->increment('total_downline_count');
        }

        return ['id' => $userId, 'broker_id' => $brokerId];
    }

    private function createLevel1Users($bronzeSlab, $startBrokerNumber, $firstNames, $lastNames, $cities, $states)
    {
        $userIds = [];
        $adminId = DB::table('users')->where('user_type', 'admin')->first()->id ?? null;

        for ($i = 0; $i < 10; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $name = $firstName . ' ' . $lastName;
            $email = strtolower($firstName . '.' . $lastName . '.' . $startBrokerNumber . '@example.com');
            
            while (DB::table('users')->where('email', $email)->exists()) {
                $email = strtolower($firstName . '.' . $lastName . '.' . $startBrokerNumber . '.' . rand(1000, 9999) . '@example.com');
            }

            $brokerId = 'SH' . str_pad($startBrokerNumber, 7, '0', STR_PAD_LEFT);
            $referralCode = 'REF' . strtoupper(Str::random(8));
            
            while (DB::table('users')->where('referral_code', $referralCode)->exists()) {
                $referralCode = 'REF' . strtoupper(Str::random(8));
            }

            $phoneNumber = '9' . rand(100000000, 999999999);
            while (DB::table('users')->where('phone_number', $phoneNumber)->exists()) {
                $phoneNumber = '9' . rand(100000000, 999999999);
            }

            $city = $cities[array_rand($cities)];
            $state = $states[array_rand($states)];

            $userId = DB::table('users')->insertGetId([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password123'),
                'user_type' => 'broker',
                'status' => 'active',
                'broker_id' => $brokerId,
                'phone_number' => $phoneNumber,
                'referral_code' => $referralCode,
                'referred_by_code' => 'ADMIN001',
                'referred_by_user_id' => $adminId,
                'slab_id' => $bronzeSlab->id,
                'kyc_verified' => false,
                'total_business_volume' => rand(0, 100000) / 100,
                'total_commission_earned' => rand(0, 10000) / 100,
                'total_downline_count' => 0,
                'address' => rand(1, 999) . ', ' . $firstName . ' Street, ' . $city,
                'city' => $city,
                'state' => $state,
                'pincode' => rand(100000, 999999),
                'created_at' => Carbon::now()->subDays(rand(180, 365)),
                'updated_at' => Carbon::now(),
            ]);

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

            $userIds[] = $userId;
            $startBrokerNumber++;
        }

        return $userIds;
    }
}

