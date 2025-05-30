<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaporDiri extends Model
{
    use HasFactory;

    protected $table = 'lapordiri'; // explicitly define the table name

    protected $fillable = [
        'tarikh',
        'masa',
        'tempat',
        'mentor_id',
        'mentee_id',
        'status_kehadiran',
    ];

    // Relationships
    public function mentor()
    {
        return $this->belongsTo(Mentor::class, 'mentor_id', 'user_id');
    }

    public function mentee()
    {
        return $this->belongsTo(Mentee::class, 'mentee_id', 'user_id');
    }

}
