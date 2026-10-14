<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentEntry extends Model
{
    protected $fillable = ['document_id', 'entry_date', 'entry_end_date'];

    protected $casts = [
        'entry_date' => 'date',
        'entry_end_date' => 'date',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function values()
    {
        return $this->hasMany(DocumentEntryValue::class);
    }

    public function value(string $key): ?string
    {
        return $this->values->firstWhere('field_key', $key)?->value;
    }
}
