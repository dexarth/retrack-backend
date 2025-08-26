<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'mentor_id',   // stores users.id of the mentor
        'mentee_id',   // stores users.id of the mentee
        'status',
        'ulasan',
    ];

    /**
     * Domain relations (Mentor / Mentee)
     * ------------------------------------------------------------
     * Your domain tables use `user_id` as the primary link back to users.
     * In Laporan, we store the users.id as FK (mentor_id / mentee_id).
     * So:
     *   laporan.mentor_id  -> mentors.user_id
     *   laporan.mentee_id  -> mentees.user_id
     */

    public function mentor()
    {
        // laporan.mentor_id (users.id) -> mentors.user_id
        return $this->belongsTo(Mentor::class, 'mentor_id', 'user_id');
    }

    public function mentee()
    {
        // laporan.mentee_id (users.id) -> mentees.user_id
        return $this->belongsTo(Mentee::class, 'mentee_id', 'user_id');
    }

    /**
     * Direct accounts (User) for convenience
     * ------------------------------------------------------------
     * If you need the raw User model of the mentee/mentor quickly.
     */

    public function mentorAccount()
    {
        // laporan.mentor_id -> users.id
        return $this->belongsTo(User::class, 'mentor_id', 'id');
    }

    public function menteeAccount()
    {
        // laporan.mentee_id -> users.id
        return $this->belongsTo(User::class, 'mentee_id', 'id');
    }
}
