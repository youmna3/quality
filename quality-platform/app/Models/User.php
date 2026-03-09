<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'reviewer_type',
        'project_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function tutor()
    {
        return $this->hasOne(Tutor::class);
    }

    public function decidedFlags()
    {
        return $this->hasMany(Flag::class, 'decided_by');
    }

    public function reviewerAssignments()
    {
        return $this->hasMany(ReviewerAssignment::class, 'reviewer_id');
    }

    public function reviewEditLogs()
    {
        return $this->hasMany(ReviewEditLog::class, 'actor_id');
    }

    public function reviewEditRequests()
    {
        return $this->hasMany(ReviewEditRequest::class, 'requester_id');
    }

    public function coordinatorMentorAssignments()
    {
        return $this->hasMany(MentorCoordinatorAssignment::class, 'coordinator_user_id');
    }

    public function teamLeadMentorAssignments()
    {
        return $this->hasMany(MentorTeamLeadAssignment::class, 'team_lead_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTutor(): bool
    {
        return $this->role === 'tutor';
    }

    public function isReviewer(): bool
    {
        return $this->role === 'reviewer';
    }

    public function isTeamLead(): bool
    {
        return $this->role === 'team_lead';
    }

    public function isMentorReviewer(): bool
    {
        return $this->isReviewer() && $this->reviewer_type === 'mentor';
    }

    public function isCoordinatorReviewer(): bool
    {
        return $this->isReviewer() && $this->reviewer_type === 'coordinator';
    }
}
