<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffMonitoring extends Model
{
    use HasFactory;

    protected $table = 'staff_monitorings';

    protected $fillable = [
        'kategori',
        'prospek_id',
        'csi_id',
        'rt_no',
        'alamat_baru',
        'baru_lat',
        'baru_long',
        'laporan_pemantauan',
        'mentor_id',
        'gambar',
    ];

    // Relationships
    public function prospek()
    {
        return $this->belongsTo(User::class, 'prospek_id'); // individu (mentee)
    }

    public function csi()
    {
        return $this->belongsTo(Csi::class, 'csi_id'); // kelompok
    }

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id'); // mentor user
    }
}
