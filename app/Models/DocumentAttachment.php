<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A file attached to a form - currently the Medical Certificate and the
 * Psychological Test Result that go with Form 1 (Application for Internship).
 */
class DocumentAttachment extends Model
{
    /** Attachments each form takes, keyed by requirement code. */
    public const FOR_REQUIREMENT = [
        'APPLICATION_FOR_OJT' => [
            'medical_certificate' => 'Medical Certificate (government physician)',
            'psychological_test' => 'Psychological Test Result',
        ],
    ];

    protected $fillable = ['document_id', 'kind', 'file_path', 'original_filename', 'uploaded_by_user_id'];

    public static function kindsFor(?string $requirementCode): array
    {
        return self::FOR_REQUIREMENT[$requirementCode] ?? [];
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function label(): string
    {
        foreach (self::FOR_REQUIREMENT as $kinds) {
            if (isset($kinds[$this->kind])) {
                return $kinds[$this->kind];
            }
        }

        return ucfirst(str_replace('_', ' ', $this->kind));
    }

    public function isPreviewable(): bool
    {
        return (bool) preg_match('/\.(pdf|png|jpe?g|gif|webp)$/i', (string) ($this->original_filename ?: $this->file_path));
    }
}
