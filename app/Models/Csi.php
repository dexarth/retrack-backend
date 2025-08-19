<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Csi extends Model
{
    protected $table = 'csi';

    protected $fillable = [
        'nama_syarikat',
        'syarikat_lat',
        'syarikat_long',
        'alamat_syarikat',
    ];
}
