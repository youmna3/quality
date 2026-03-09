<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flag extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'review_id',
        'tutor_id',
        'color',
        'initial_color',
        'subcategory',
        'reason',
        'objection_text',
        'duration_text',
        'screenshot_path',
        'status',
        'objection_status',
        'objection_response',
        'objection_submitted_at',
        'objection_reviewed_by',
        'objection_reviewed_at',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'created_at' => 'datetime',
        'objection_submitted_at' => 'datetime',
        'objection_reviewed_at' => 'datetime',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function objectionReviewer()
    {
        return $this->belongsTo(User::class, 'objection_reviewed_by');
    }
}
