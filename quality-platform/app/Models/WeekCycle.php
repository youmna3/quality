<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeekCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'week_number',
        'starts_at',
        'deadline_at',
        'reports_published_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'deadline_at' => 'datetime',
        'reports_published_at' => 'datetime',
    ];
}
