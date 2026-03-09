<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewEditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'actor_id',
        'actor_name',
        'actor_role',
        'changed_fields_json',
        'before_snapshot_json',
        'after_snapshot_json',
    ];

    protected $casts = [
        'changed_fields_json' => 'array',
        'before_snapshot_json' => 'array',
        'after_snapshot_json' => 'array',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
