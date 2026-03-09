<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsGroupMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'tutor_code',
        'slot',
        'session_date',
        'group_code',
    ];

    protected $casts = [
        'session_date' => 'date',
    ];
}
