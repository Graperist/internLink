<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentReview extends Model
{
    protected $fillable = ['document_id', 'instructor_id', 'decision', 'remarks', 'reviewed_at'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }
}
