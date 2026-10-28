<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    public const CATEGORIES = [
        'pre_ojt_seminar' => 'Pre-OJT Seminar',
        'post_ojt_seminar' => 'Post-OJT Seminar',
        'general' => 'General announcement',
    ];

    protected $fillable = [
        'campus_id', 'program_id', 'user_id', 'category', 'title',
        'event_at', 'venue', 'message', 'recipients_count',
    ];

    protected $casts = ['event_at' => 'datetime'];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Announcement';
    }

    /** The program it was sent to; null when it went to every program. */
    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
