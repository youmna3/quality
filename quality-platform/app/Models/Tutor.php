<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tutor extends Model
{
    use HasFactory;

    protected $fillable = [
        'tutor_code',
        'name_en',
        'project_id',
        'mentor_name',
        'grade',
        'zoom_email',
        'zoom_password',
        'dashboard_email',
        'dashboard_password',
        'shift',
        'is_active',
        'user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function flags()
    {
        return $this->hasMany(Flag::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function reviewerAssignments()
    {
        return $this->hasMany(ReviewerAssignment::class);
    }
}
