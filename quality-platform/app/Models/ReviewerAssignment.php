<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewerAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'week_number',
        'tutor_id',
        'reviewer_id',
        'reviewer_type',
        'assigned_by',
    ];

    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function review()
    {
        return $this->hasOne(Review::class, 'reviewer_assignment_id');
    }
}
