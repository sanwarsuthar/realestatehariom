<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = [
            [
                'name' => 'Shree Hari Om Heights',
                'description' => 'Luxury residential project with modern amenities',
                'location' => 'Sector 45, Gurgaon',
                'city' => 'Gurgaon',
                'state' => 'Haryana',
                'pincode' => '122003',
                'type' => 'residential',
                'price_range_min' => 5000000,
                'price_range_max' => 15000000,
                'facilities' => json_encode(['Swimming Pool', 'Gym', 'Park', 'Security', 'Power Backup']),
                'images' => json_encode(['/Users/mac/Documents/1.png', '/Users/mac/Documents/1.png']),
                'videos' => json_encode([]),
                'status' => 'available',
                'latitude' => 28.4595,
                'longitude' => 77.0266,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Hari Om Gardens',
                'description' => 'Premium villa project with spacious plots',
                'location' => 'Greater Noida West',
                'city' => 'Greater Noida',
                'state' => 'Uttar Pradesh',
                'pincode' => '201306',
                'type' => 'residential',
                'price_range_min' => 3000000,
                'price_range_max' => 8000000,
                'facilities' => json_encode(['Club House', 'Garden', 'Security', 'Parking']),
                'images' => json_encode(['/Users/mac/Documents/1.png', '/Users/mac/Documents/1.png']),
                'videos' => json_encode([]),
                'status' => 'available',
                'latitude' => 28.5355,
                'longitude' => 77.3910,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Om Plaza Commercial',
                'description' => 'Modern commercial complex for offices and shops',
                'location' => 'Connaught Place',
                'city' => 'New Delhi',
                'state' => 'Delhi',
                'pincode' => '110001',
                'type' => 'commercial',
                'price_range_min' => 10000000,
                'price_range_max' => 50000000,
                'facilities' => json_encode(['Parking', 'Security', 'Elevator', 'Power Backup', 'Cafeteria']),
                'images' => json_encode(['/Users/mac/Documents/1.png', '/Users/mac/Documents/1.png']),
                'videos' => json_encode([]),
                'status' => 'available',
                'latitude' => 28.6315,
                'longitude' => 77.2167,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Shree Residency',
                'description' => 'Affordable housing project with all basic amenities',
                'location' => 'Noida Extension',
                'city' => 'Noida',
                'state' => 'Uttar Pradesh',
                'pincode' => '201301',
                'type' => 'residential',
                'price_range_min' => 2000000,
                'price_range_max' => 5000000,
                'facilities' => json_encode(['Park', 'Security', 'Water Supply', 'Electricity']),
                'images' => json_encode(['/Users/mac/Documents/1.png', '/Users/mac/Documents/1.png']),
                'videos' => json_encode([]),
                'status' => 'available',
                'latitude' => 28.5355,
                'longitude' => 77.3910,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Hari Om Towers',
                'description' => 'High-rise residential towers with panoramic views',
                'location' => 'Dwarka',
                'city' => 'New Delhi',
                'state' => 'Delhi',
                'pincode' => '110075',
                'type' => 'residential',
                'price_range_min' => 8000000,
                'price_range_max' => 20000000,
                'facilities' => json_encode(['Swimming Pool', 'Gym', 'Club House', 'Security', 'Parking', 'Garden']),
                'images' => json_encode(['/Users/mac/Documents/1.png', '/Users/mac/Documents/1.png']),
                'videos' => json_encode([]),
                'status' => 'available',
                'latitude' => 28.5921,
                'longitude' => 77.0465,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        DB::table('projects')->insert($projects);

        // Get the inserted project IDs
        $projectIds = DB::table('projects')->pluck('id')->toArray();

        // Create plots and villas for each project
        foreach ($projectIds as $projectId) {
            $project = DB::table('projects')->where('id', $projectId)->first();
            
            // Create 10-15 plots/villas for each project
            $plotCount = rand(10, 15);
            $plots = [];
            
            for ($i = 1; $i <= $plotCount; $i++) {
                $isVilla = rand(0, 1); // 50% chance of being a villa
                $basePrice = $project->price_range_min + (($project->price_range_max - $project->price_range_min) * rand(20, 80) / 100);
                
                $plots[] = [
                    'project_id' => $projectId,
                    'plot_number' => $isVilla ? 'V' . str_pad($i, 3, '0', STR_PAD_LEFT) : 'P' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'type' => $isVilla ? 'villa' : 'plot',
                    'size' => $isVilla ? rand(2000, 5000) : rand(1000, 3000),
                    'price' => $basePrice + rand(-100000, 200000),
                    'status' => ['available', 'booked', 'sold'][array_rand([0, 1, 2])],
                    'amenities' => json_encode($isVilla ? ['3 BHK', '2 Bathrooms', 'Balcony', 'Parking'] : ['Plot', 'Water Connection', 'Electricity']),
                    'images' => json_encode(['/Users/mac/Documents/1.png', '/Users/mac/Documents/1.png']),
                    'description' => $isVilla ? 'Beautiful villa with modern amenities' : 'Prime location plot ready for construction',
                    'bedrooms' => $isVilla ? rand(2, 4) : null,
                    'bathrooms' => $isVilla ? rand(2, 3) : null,
                    'carpet_area' => $isVilla ? rand(1500, 4000) : null,
                    'is_active' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
            
            DB::table('plots')->insert($plots);
        }

        $this->command->info('Created 5 projects with plots and villas!');
    }
}
