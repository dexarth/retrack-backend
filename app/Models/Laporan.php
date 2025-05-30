<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Users;

class Laporan extends Model
{
    use HasFactory;
    protected $table = 'laporan';
    protected $fillable = [
        'alamat',
        'alamat_lat',
        'alamat_long',
        'tujuan',
        'bukti_audio',
        'bukti_gambar',
        'mentor_id',
        'mentee_id',
        'status',
        'ulasan'
    ];

    /**
     * Relationship to the Mentor
     */
    public function mentor()
    {
        return $this->belongsTo(Mentor::class, 'mentor_id', 'user_id');
    }

    /**
     * Relationship to the Mentee
     */
    public function mentee()
    {
        return $this->belongsTo(Mentee::class, 'mentee_id', 'user_id');
    }
}
