<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //$this->call(UserSeeder::class);
        //$this->call(BlogSeeder::class);
        $this->call(LaporanSeeder::class);
    }
}
