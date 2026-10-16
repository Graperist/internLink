<?php

namespace Tests\Feature;

use App\Models\DocumentFieldValue;
use App\Support\TextCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Addresses typed in ALL CAPS read like the student's name
 * ("Baybay Norte Sual Pangasinan"); signatory names print in capitals.
 */
class NameCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_caps_text_gets_normal_capitalization(): void
    {
        $this->assertSame('Baybay Norte Sual Pangasinan', TextCase::fixCaps('BAYBAY NORTE SUAL PANGASINAN'));
        $this->assertSame('Brgy. Poblacion, Sual, Pangasinan', TextCase::fixCaps('BRGY. POBLACION, SUAL, PANGASINAN'));
        $this->assertSame('Zone II, Lucap', TextCase::fixCaps('ZONE II, LUCAP'));
        // Mixed case is left exactly as typed.
        $this->assertSame('Baybay Norte, Sual, Pangasinan', TextCase::fixCaps('Baybay Norte, Sual, Pangasinan'));
        $this->assertSame('123', TextCase::fixCaps('123'));
        $this->assertNull(TextCase::fixCaps(null));
    }

    public function test_only_the_students_address_fields_are_changed(): void
    {
        $address = new DocumentFieldValue(['field_key' => 'current_address', 'value' => 'BAYBAY NORTE SUAL PANGASINAN']);
        $this->assertSame('Baybay Norte Sual Pangasinan', $address->value);

        // Existing all-caps rows read normally too.
        $old = (new DocumentFieldValue)->setRawAttributes(['field_key' => 'home_address', 'value' => 'SITIO MASAMEREY']);
        $this->assertSame('Sitio Masamerey', $old->value);

        // Other fields (e.g. the surname on Form 1) keep what was typed.
        $surname = new DocumentFieldValue(['field_key' => 'surname', 'value' => 'BEJERANO']);
        $this->assertSame('BEJERANO', $surname->value);
    }

    public function test_every_printable_puts_signatory_names_in_capitals(): void
    {
        $files = glob(resource_path('views/instructor/documents/printable-*.blade.php'));
        $withSignatures = array_filter($files, fn ($f) => ! str_contains($f, 'printable-ojt-resume'));

        foreach ($withSignatures as $file) {
            $this->assertMatchesRegularExpression('/\{\s*text-transform:\s*uppercase;\s*\}|text-transform: uppercase; \}/', file_get_contents($file), basename($file));
        }
    }
}
