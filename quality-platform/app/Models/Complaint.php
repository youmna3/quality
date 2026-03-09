<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tutor_id',
        'project_id',
        'session_date',
        'slot',
        'group_code',
        'issue_type',
        'complaint_text',
        'status',
    ];

    protected $casts = [
        'session_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
