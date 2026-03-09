<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
    ];

    public function tutors()
    {
        return $this->hasMany(Tutor::class);
    }

    public function sessions()
    {
        return $this->hasMany(Session::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }
}
