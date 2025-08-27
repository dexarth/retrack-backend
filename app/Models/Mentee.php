<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Mentee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'id_prospek',
        'daerah',
        'jantina',
        'no_tel',
        'alamat_rumah',
        'huraian_alamat',
        'rumah_lat',
        'rumah_long',
        'tarikh_bebas',
        'mentor_id',
        'kategori_prospek',
        'jenis_penamatan',
        'nama_waris_1',
        'no_tel_waris_1',
        'nama_waris_2',
        'no_tel_waris_2',
    ];

    /**
     * Relationship to the user who is this mentee.
     */
    public function user()   
    { 
        return $this->belongsTo(User::class, 'user_id', 'id'); 
    }

    /**
     * Relationship to the mentor (also from the users table).
     */
    public function mentor()
    {
        return $this->belongsTo(Mentor::class, 'mentor_id', 'user_id');
    }

    public function laporan()
    {
        // laporan.mentee_id → mentees.user_id
        return $this->hasMany(\App\Models\Laporan::class, 'mentee_id', 'user_id');
    }
}
