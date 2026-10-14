<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentEntryValue extends Model
{
    protected $fillable = ['document_entry_id', 'field_key', 'value'];

    public function entry()
    {
        return $this->belongsTo(DocumentEntry::class, 'document_entry_id');
    }
}
