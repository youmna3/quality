<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'group_code',
        'session_date',
        'slot',
        'recording_url',
    ];

    protected $casts = [
        'session_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
