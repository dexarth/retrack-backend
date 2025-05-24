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
        'mentee_id'
    ];

    /**
     * Relationship to the Mentor
     */
    public function mentor()
    {
        return $this->belongsTo(Users::class, 'mentor_id');
    }

    /**
     * Relationship to the Mentee
     */
    public function mentee()
    {
        return $this->belongsTo(Users::class, 'mentee_id');
    }
}
