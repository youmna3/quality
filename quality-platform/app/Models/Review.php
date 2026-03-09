<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'reviewer_assignment_id',
        'tutor_id',
        'reviewer_name',
        'project_id',
        'session_id',
        'tutor_role',
        'session_date',
        'slot',
        'group_code',
        'recorded_link',
        'issue_text',
        'positive_note',
        'negative_note',
        'positive_comments_json',
        'negative_comments_json',
        'score_breakdown_json',
        'total_score',
        'submitted_at',
    ];

    protected $casts = [
        'session_date' => 'date',
        'submitted_at' => 'datetime',
        'total_score' => 'decimal:2',
        'positive_comments_json' => 'array',
        'negative_comments_json' => 'array',
        'score_breakdown_json' => 'array',
    ];

    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }

    public function reviewerAssignment()
    {
        return $this->belongsTo(ReviewerAssignment::class, 'reviewer_assignment_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function flags()
    {
        return $this->hasMany(Flag::class);
    }

    public function editLogs()
    {
        return $this->hasMany(ReviewEditLog::class);
    }

    public function editRequests()
    {
        return $this->hasMany(ReviewEditRequest::class);
    }
}
