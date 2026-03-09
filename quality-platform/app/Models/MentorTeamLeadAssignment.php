<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MentorTeamLeadAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mentor_name',
        'team_lead_user_id',
    ];

    public function teamLead()
    {
        return $this->belongsTo(User::class, 'team_lead_user_id');
    }
}

