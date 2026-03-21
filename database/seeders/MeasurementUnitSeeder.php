<?php

namespace Database\Seeders;

use App\Models\MeasurementUnit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MeasurementUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['name' => 'Square Feet', 'symbol' => 'sqft'],
            ['name' => 'Square Meter', 'symbol' => 'sqm'],
            ['name' => 'Square Yard', 'symbol' => 'sqyd'],
            ['name' => 'Bigha', 'symbol' => 'bigha'],
            ['name' => 'Acre', 'symbol' => 'acre'],
        ];

        foreach ($units as $unit) {
            MeasurementUnit::firstOrCreate(
                ['name' => $unit['name']],
                ['symbol' => $unit['symbol']]
            );
        }
    }
}

