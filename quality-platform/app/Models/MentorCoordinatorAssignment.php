<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MentorCoordinatorAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mentor_name',
        'coordinator_user_id',
    ];

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_user_id');
    }
}
