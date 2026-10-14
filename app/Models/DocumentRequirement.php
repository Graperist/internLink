<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequirement extends Model
{
    protected $fillable = ['campus_id', 'code', 'form_number', 'name', 'display_order', 'audience', 'workflow_mode', 'is_active', 'stage', 'is_required', 'is_repeating', 'entry_cadence', 'reviewer_role', 'source_reference'];

    protected $casts = ['is_required' => 'boolean', 'is_repeating' => 'boolean', 'is_active' => 'boolean'];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function fieldDefinitions()
    {
        return $this->hasMany(DocumentFieldDefinition::class)->orderBy('sort_order');
    }

    /**
     * Whether this requirement uses the in-system fill -> print -> sign ->
     * mark complete -> single upload workflow, rather than a plain file
     * upload. Determined purely by whether any field definitions exist,
     * so adding the workflow to another form later is just a seeder
     * change, not a code change.
     */
    public function hasStructuredFields(): bool
    {
        return $this->fieldDefinitions()->exists();
    }

    /**
     * Field definitions bucketed by group_label, in insertion order, for
     * forms with grouped rating-scale criteria (e.g., Form 23c's
     * "Communication" / "Ethical Dealings" sections). Fields with no
     * group_label fall under a single null-keyed bucket, rendered as
     * ungrouped fields on the fill form.
     */
    public function groupedFieldDefinitions()
    {
        return $this->fieldDefinitions->groupBy('group_label');
    }
}
