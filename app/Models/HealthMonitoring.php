<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HealthMonitoring extends Model
{
    protected $table = 'health_monitorings';

    protected $fillable = [
        'mentee_id',
        'mood',
        'stress',
        'sleep_quality',
        'substance_use',           // stored as JSON
        'substance_use_other',     // free-text for "lain-lain"
        'craving_score',
        'meaningful_activity',
        'motivation',
        'support_need',
        'weekly_challenge',
        'total_score',
        'risk_zone',
    ];


}
