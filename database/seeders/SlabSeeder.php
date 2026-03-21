<?php

namespace Database\Seeders;

use App\Models\MeasurementUnit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SlabSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultUnit = MeasurementUnit::firstOrCreate(
            ['name' => 'Square Feet'],
            ['symbol' => 'sqft']
        );

        $slabs = [
            [
                'name' => 'Bronze',
                'minimum_target' => 0,
                'maximum_target' => 2500,
                'commission_ratio' => 2.00,
                'bonus_percentage' => 0,
                'description' => 'Entry level slab for new brokers',
                'color_code' => '#CD7F32',
                'sort_order' => 1,
                'measurement_unit_id' => $defaultUnit->id,
            ],
            [
                'name' => 'Silver',
                'minimum_target' => 100000,
                'maximum_target' => 500000,
                'commission_ratio' => 3.00,
                'bonus_percentage' => 5,
                'description' => 'Achieved after ₹1L business volume',
                'color_code' => '#C0C0C0',
                'sort_order' => 2,
                'measurement_unit_id' => $defaultUnit->id,
            ],
            [
                'name' => 'Gold',
                'minimum_target' => 300000,
                'maximum_target' => 1000000,
                'commission_ratio' => 4.00,
                'bonus_percentage' => 10,
                'description' => 'Achieved after ₹3L business volume',
                'color_code' => '#FFD700',
                'sort_order' => 3,
                'measurement_unit_id' => $defaultUnit->id,
            ],
            [
                'name' => 'Diamond',
                'minimum_target' => 1000000,
                'maximum_target' => null,
                'commission_ratio' => 5.00,
                'bonus_percentage' => 15,
                'description' => 'Achieved after ₹10L business volume',
                'color_code' => '#B9F2FF',
                'sort_order' => 4,
                'measurement_unit_id' => $defaultUnit->id,
            ],
        ];

        foreach ($slabs as $slab) {
            DB::table('slabs')->insert(array_merge($slab, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
