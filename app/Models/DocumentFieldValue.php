<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentFieldValue extends Model
{
    protected $fillable = ['document_id', 'field_key', 'value'];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
