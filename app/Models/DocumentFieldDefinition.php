<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentFieldDefinition extends Model
{
    protected $fillable = ['document_requirement_id', 'field_key', 'label', 'group_label', 'field_type', 'validation_rule', 'is_required', 'sort_order'];

    protected $casts = ['is_required' => 'boolean'];

    public function requirement()
    {
        return $this->belongsTo(DocumentRequirement::class, 'document_requirement_id');
    }
}
