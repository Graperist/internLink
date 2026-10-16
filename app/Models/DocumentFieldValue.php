<?php

namespace App\Models;

use App\Support\TextCase;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class DocumentFieldValue extends Model
{
    /**
     * The student's own address fields. Typed in ALL CAPS they are saved
     * and shown in normal capitalization, like the student's name
     * ("Baybay Norte, Sual, Pangasinan").
     */
    public const ADDRESS_KEYS = ['address', 'current_address', 'home_address'];

    protected $fillable = ['document_id', 'field_key', 'value'];

    protected function value(): Attribute
    {
        $fix = fn ($value, array $attributes) => in_array($attributes['field_key'] ?? null, self::ADDRESS_KEYS, true)
            ? TextCase::fixCaps($value)
            : $value;

        return Attribute::make(get: $fix, set: $fix);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
