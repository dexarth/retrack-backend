<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LaporanSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('laporan')->insert([
            [
                'alamat' => 'Kampung Ayer, Sabah',
                'alamat_lat' => 5.9787,
                'alamat_long' => 116.0735,
                'tujuan' => 'Misi bantuan pendidikan digital',
                'bukti_audio' => 'audio/recording1.mp3',
                'bukti_gambar' => 'images/proof1.jpg',
                'mentor_id' => 2,
                'mentee_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'alamat' => 'Kg. Simpangan, Kota Belud',
                'alamat_lat' => 6.3362,
                'alamat_long' => 116.4332,
                'tujuan' => 'Sesi bimbingan usahawan muda',
                'bukti_audio' => null,
                'bukti_gambar' => 'images/proof2.jpg',
                'mentor_id' => 2,
                'mentee_id' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
