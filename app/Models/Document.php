<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected static function booted(): void
    {
        // Any status change settles whatever the previous status was asking for
        // (e.g. "awaiting your review", "returned for correction"), so those
        // notifications are removed. New ones are created after the update.
        static::updated(function (Document $document) {
            if ($document->wasChanged('status')) {
                \App\Models\Notification::resolve('document:'.$document->id);
            }
        });
    }

    protected $fillable = [
        'internship_id', 'hte_id', 'document_requirement_id', 'uploaded_by_user_id',
        'file_path', 'original_filename', 'status',
        'submitted_at', 'printed_at', 'signatures_confirmed_at', 'completed_at', 'packet_number',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'printed_at' => 'datetime',
        'signatures_confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function requirement()
    {
        return $this->belongsTo(DocumentRequirement::class, 'document_requirement_id');
    }

    public function reviews()
    {
        return $this->hasMany(DocumentReview::class);
    }

    /** The agency an agency-level evaluation (Form 23a / 23b) is about. */
    public function hte()
    {
        return $this->belongsTo(Hte::class);
    }

    public function latestReview()
    {
        return $this->hasOne(DocumentReview::class)->latestOfMany();
    }

    public function fieldValues()
    {
        return $this->hasMany(DocumentFieldValue::class);
    }

    /** Files attached to this form (e.g. Form 1's medical certificate). */
    public function attachments()
    {
        return $this->hasMany(DocumentAttachment::class);
    }

    public function attachment(string $kind): ?DocumentAttachment
    {
        return $this->attachments->firstWhere('kind', $kind);
    }

    public function entries()
    {
        return $this->hasMany(DocumentEntry::class)->orderBy('entry_date');
    }

    /**
     * Convenience accessor for a single field's entered value, e.g.
     * $document->fieldValue('hte_name'). Loads fieldValues fresh if not
     * already eager-loaded.
     */
    public function fieldValue(string $key): ?string
    {
        return $this->fieldValues->firstWhere('field_key', $key)?->value;
    }

    /**
     * Print-and-sign: the student may upload the signed copy only after the
     * instructor confirmed the signatures on the physical form. (A form left
     * "completed" without a file by the older flow stays open too.)
     */
    public function signedUploadOpen(): bool
    {
        return ($this->status === 'printed' && $this->signatures_confirmed_at !== null)
            || ($this->status === 'completed' && blank($this->file_path));
    }
}
