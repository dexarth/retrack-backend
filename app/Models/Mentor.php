<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Mentor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nama_penuh',
        'pangkat',
        'parol_daerah',
    ];

    /**
     * Relationship to User model
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
