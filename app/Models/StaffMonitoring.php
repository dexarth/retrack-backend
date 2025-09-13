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
        'current_alamat',
        'current_lat',
        'current_long',
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

    public function user()   
    { 
        return $this->belongsTo(User::class, 'user_id'); 
    }

    public function menteeAccount()
    {
        // mentee_id stores the users.id of the mentee
        return $this->belongsTo(User::class, 'mentee_id', 'id');
    }

    public function mentorAccount()
    {
        return $this->belongsTo(User::class, 'mentor_id', 'id');
    }
}
