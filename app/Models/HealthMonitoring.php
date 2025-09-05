<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class HealthMonitoring extends Model
{
    protected $table = 'health_monitorings';

    protected $fillable = [
        'mentee_id',
        'mentor_id',
        'date',
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

    // Relationships
    public function mentor()
    {
        return $this->belongsTo(Mentor::class, 'mentor_id', 'user_id');
    }

    public function mentee()
    {
        return $this->belongsTo(Mentee::class, 'mentee_id', 'user_id');
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
