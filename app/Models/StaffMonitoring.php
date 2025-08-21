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
        'mentee_id',
        'csi_id',
        'rt_no',
        'alamat_baru',
        'huraian_alamat',
        'baru_lat',
        'baru_long',
        'laporan_pemantauan',
        'mentor_id',
        'gambar',
    ];

    // Relationships
    public function mentee()
    {
        return $this->belongsTo(Mentee::class, 'mentee_id', 'user_id'); // individu (mentee)
    }

    public function csi()
    {
        return $this->belongsTo(Csi::class, 'csi_id'); // kelompok
    }

    public function mentor()
    {
        return $this->belongsTo(Mentor::class, 'mentor_id', 'user_id'); // mentor user
    }
}
