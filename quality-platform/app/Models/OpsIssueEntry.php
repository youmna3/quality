<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsIssueEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'tutor_code',
        'session_date',
        'slot',
        'issue_text',
    ];

    protected $casts = [
        'session_date' => 'date',
    ];
}
