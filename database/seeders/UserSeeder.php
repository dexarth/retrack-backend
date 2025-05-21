<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Mentor;
use App\Models\Admin;
use App\Models\Mentee;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $parolList = ['SANDAKAN', 'TAWAU', 'BEAUFORT', 'KUDAT', 'PANTAI BARAT', 'PEDALAMAN'];

        // // Seed mentors
        // for ($i = 1; $i <= 20; $i++) {
        //     $user = User::create([
        //         'name' => "mentor$i",
        //         'email' => "mentor$i@retrack.com",
        //         'password' => Hash::make('Retrack@2025'),
        //         'role' => 'mentor',
        //     ]);

        //     Mentor::create([
        //         'user_id' => $user->id,
        //         'nama_penuh' => "Mentor $i",
        //         'pangkat' => 'SARJAN',
        //         'parol_daerah' => $parolList[array_rand($parolList)],
        //     ]);
        // }

        // // Seed admins
        // for ($i = 1; $i <= 20; $i++) {
        //     User::create([
        //         'name' => "admin$i",
        //         'email' => "admin$i@retrack.com",
        //         'password' => Hash::make('Retrack@2025'),
        //         'role' => 'admin',
        //     ]);

        //     Admin::create([
        //         'user_id' => $user->id,
        //         'nama_penuh' => "Mentor $i",
        //         'pangkat' => 'SARJAN',
        //         'parol_daerah' => $parolList[array_rand($parolList)],
        //     ]);
        // }

        // Seed mentees
        for ($i = 1; $i <= 20; $i++) {
            $user = User::create([
                'name' => "mentee$i",
                'email' => "mentee$i@retrack.com",
                'password' => Hash::make('Retrack@2025'),
                'role' => 'mentee',
            ]);

             Mentee::create([
                'user_id' => $user->id,
                'id_prospek' => 'IDP' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'daerah' => 'Sandakan',
                'jantina' => $i % 2 === 0 ? 'L' : 'P',
                'no_tel' => '01300000' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'alamat_rumah' => 'Alamat Contoh ' . $i,
                'rumah_lat' => 5.8398 + $i * 0.001,
                'rumah_long' => 118.1178 + $i * 0.001,
                'tarikh_bebas' => now()->subDays($i * 10)->toDateString(),
                'mentor_id' => rand(6, 25), // assuming 20 mentors exist
                'kategori_prospek' => ['ODB', 'OBB', 'PBL', 'PKL'][array_rand(['ODB', 'OBB', 'PBL', 'PKL'])],
                'jenis_penamatan' => ['TAMAT HUKUMAN', 'LANGGAR SYARAT', 'TEKNIKAL'][array_rand(['TAMAT HUKUMAN', 'LANGGAR SYARAT', 'TEKNIKAL'])],
                'nama_waris_1' => 'Waris Utama ' . $i,
                'no_tel_waris_1' => '01950000' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'nama_waris_2' => 'Waris Kedua ' . $i,
                'no_tel_waris_2' => '01960000' . str_pad($i, 2, '0', STR_PAD_LEFT),
            ]);
        }
    }
}
    