<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all slabs for random assignment
        $slabs = DB::table('slabs')->get();
        $slabIds = $slabs->pluck('id')->toArray();
        
        // Get admin user ID for referrals
        $adminId = DB::table('users')->where('user_type', 'admin')->first()->id;
        
        // Indian names for realistic data
        $firstNames = [
            'Rajesh', 'Priya', 'Amit', 'Sunita', 'Vikram', 'Kavita', 'Ravi', 'Meera',
            'Suresh', 'Anita', 'Kumar', 'Pooja', 'Deepak', 'Rekha', 'Manoj', 'Sushma',
            'Naveen', 'Geeta', 'Prakash', 'Lata', 'Vijay', 'Shanti', 'Ramesh', 'Usha',
            'Suresh', 'Kamala', 'Ganesh', 'Sarita', 'Mohan', 'Indira', 'Rakesh', 'Vandana',
            'Arun', 'Suman', 'Raj', 'Neha', 'Sanjay', 'Rita', 'Vinod', 'Kiran',
            'Ashok', 'Manju', 'Dilip', 'Seema', 'Jagdish', 'Renu', 'Bharat', 'Sarla',
            'Hari', 'Pushpa', 'Ram', 'Kumari', 'Shyam', 'Radha', 'Krishna', 'Ganga',
            'Vishnu', 'Lakshmi', 'Shiva', 'Parvati', 'Brahma', 'Saraswati', 'Ganesha', 'Durga'
        ];
        
        $lastNames = [
            'Sharma', 'Verma', 'Gupta', 'Singh', 'Kumar', 'Yadav', 'Patel', 'Jain',
            'Agarwal', 'Mishra', 'Pandey', 'Tiwari', 'Singh', 'Yadav', 'Kumar', 'Sharma',
            'Verma', 'Gupta', 'Patel', 'Jain', 'Agarwal', 'Mishra', 'Pandey', 'Tiwari',
            'Reddy', 'Rao', 'Naidu', 'Nair', 'Menon', 'Iyer', 'Iyengar', 'Bhatt',
            'Joshi', 'Desai', 'Mehta', 'Shah', 'Trivedi', 'Chauhan', 'Rathore', 'Solanki'
        ];
        
        $cities = [
            'Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad',
            'Jaipur', 'Surat', 'Lucknow', 'Kanpur', 'Nagpur', 'Indore', 'Thane', 'Bhopal',
            'Visakhapatnam', 'Pimpri-Chinchwad', 'Patna', 'Vadodara', 'Ghaziabad', 'Ludhiana',
            'Agra', 'Nashik', 'Faridabad', 'Meerut', 'Rajkot', 'Kalyan-Dombivali', 'Vasai-Virar',
            'Varanasi', 'Srinagar', 'Aurangabad', 'Navi Mumbai', 'Solapur', 'Vijayawada', 'Kolhapur'
        ];
        
        $states = [
            'Maharashtra', 'Delhi', 'Karnataka', 'Telangana', 'Tamil Nadu', 'West Bengal',
            'Gujarat', 'Rajasthan', 'Uttar Pradesh', 'Madhya Pradesh', 'Andhra Pradesh',
            'Bihar', 'Punjab', 'Haryana', 'Kerala', 'Odisha', 'Jharkhand', 'Assam',
            'Chhattisgarh', 'Himachal Pradesh', 'Uttarakhand', 'Tripura', 'Meghalaya',
            'Manipur', 'Nagaland', 'Goa', 'Arunachal Pradesh', 'Mizoram', 'Sikkim'
        ];
        
        $statuses = ['active', 'inactive', 'suspended'];
        $kycStatuses = [true, false];
        
        $users = [];
        $referralCodes = ['ADMIN001']; // Start with admin referral code
        
        for ($i = 1; $i <= 100; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $name = $firstName . ' ' . $lastName;
            
            $brokerId = 'SH' . str_pad($i + 1, 6, '0', STR_PAD_LEFT);
            $referralCode = 'REF' . str_pad($i, 6, '0', STR_PAD_LEFT);
            
            // Random referral - 70% chance to be referred by someone
            $referredByUserId = null;
            $referredByCode = null;
            if (count($referralCodes) > 1 && rand(1, 100) <= 70) {
                $referredByCode = $referralCodes[array_rand($referralCodes)];
                $referredByUser = DB::table('users')->where('referral_code', $referredByCode)->first();
                if ($referredByUser) {
                    $referredByUserId = $referredByUser->id;
                }
            }
            
            // If not referred by anyone, refer to admin
            if (!$referredByUserId) {
                $referredByUserId = $adminId;
                $referredByCode = 'ADMIN001';
            }
            
            $city = $cities[array_rand($cities)];
            $state = $states[array_rand($states)];
            
            $user = [
                'name' => $name,
                'email' => strtolower($firstName . '.' . $lastName . $i . '@gmail.com'),
                'password' => Hash::make('password123'),
                'user_type' => 'broker',
                'status' => $statuses[array_rand($statuses)],
                'broker_id' => $brokerId,
                'phone_number' => '9' . rand(100000000, 999999999),
                'referral_code' => $referralCode,
                'referred_by_code' => $referredByCode,
                'referred_by_user_id' => $referredByUserId,
                'slab_id' => $slabIds[array_rand($slabIds)],
                'kyc_verified' => $kycStatuses[array_rand($kycStatuses)],
                'total_business_volume' => rand(0, 5000000) / 100, // 0 to 50,000
                'total_commission_earned' => rand(0, 500000) / 100, // 0 to 5,000
                'total_downline_count' => rand(0, 50),
                'profile_image_path' => '/Users/mac/Documents/1.png',
                'address' => rand(1, 999) . ', ' . $firstName . ' Street, ' . $city,
                'city' => $city,
                'state' => $state,
                'pincode' => rand(100000, 999999),
                'created_at' => Carbon::now()->subDays(rand(0, 365)),
                'updated_at' => Carbon::now()->subDays(rand(0, 30)),
            ];
            
            $users[] = $user;
            $referralCodes[] = $referralCode;
        }
        
        // Insert users in batches
        DB::table('users')->insert($users);
        
        // Create wallets for all users
        $allUsers = DB::table('users')->where('user_type', 'broker')->get();
        $wallets = [];
        
        foreach ($allUsers as $user) {
            $wallets[] = [
                'user_id' => $user->id,
                'balance' => rand(0, 100000) / 100, // 0 to 1,000
                'total_earned' => rand(0, 500000) / 100, // 0 to 5,000
                'total_withdrawn' => rand(0, 200000) / 100, // 0 to 2,000
                'total_deposited' => rand(0, 300000) / 100, // 0 to 3,000
                'is_active' => true,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
        }
        
        DB::table('wallets')->insert($wallets);
        
        // Create KYC documents for some users
        $kycUsers = DB::table('users')->where('user_type', 'broker')->where('kyc_verified', false)->limit(30)->get();
        $kycDocuments = [];
        
        foreach ($kycUsers as $user) {
            $kycDocuments[] = [
                'user_id' => $user->id,
                'pan_number' => 'ABCDE' . rand(1000, 9999) . 'F',
                'aadhaar_number' => rand(100000000000, 999999999999),
                'pan_image_path' => '/Users/mac/Documents/1.png',
                'aadhaar_front_image_path' => '/Users/mac/Documents/1.png',
                'aadhaar_back_image_path' => '/Users/mac/Documents/1.png',
                'status' => ['pending', 'approved', 'rejected'][array_rand([0, 1, 2])],
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
        }
        
        DB::table('kyc_documents')->insert($kycDocuments);
        
        // Create some transactions
        $transactionUsers = DB::table('users')->where('user_type', 'broker')->limit(50)->get();
        $transactions = [];
        
        foreach ($transactionUsers as $user) {
            $transactionCount = rand(1, 10);
            for ($j = 0; $j < $transactionCount; $j++) {
                $amount = rand(100, 50000) / 100; // 1 to 500
                $type = ['commission', 'deposit', 'withdrawal', 'bonus'][array_rand([0, 1, 2, 3])];
                $status = ['pending', 'completed', 'failed'][array_rand([0, 1, 2])];
                
                $transactions[] = [
                    'user_id' => $user->id,
                    'transaction_id' => 'TXN' . time() . rand(1000, 9999) . '_' . $user->id . '_' . $j,
                    'type' => $type,
                    'status' => $status,
                    'amount' => $amount,
                    'balance_before' => rand(0, 10000) / 100,
                    'balance_after' => rand(0, 10000) / 100,
                    'description' => ucfirst($type) . ' transaction for ' . $user->name,
                    'created_at' => Carbon::now()->subDays(rand(0, 30)),
                    'updated_at' => Carbon::now()->subDays(rand(0, 30)),
                ];
            }
        }
        
        DB::table('transactions')->insert($transactions);
        
        $this->command->info('Created 100 test users with wallets, KYC documents, and transactions!');
    }
}
