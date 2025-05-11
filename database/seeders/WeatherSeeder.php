<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Weather;

class WeatherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        Weather::create([
            'city' => 'New York',
            'temperature' => 28.5,
            'description' => 'Clear sky',
        ]);

        Weather::create([
            'city' => 'Los Angeles',
            'temperature' => 22.0,
            'description' => 'Sunny',
        ]);

        Weather::create([
            'city' => 'Chicago',
            'temperature' => 15.0,
            'description' => 'Rainy',
        ]);
    }
}
