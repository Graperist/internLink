<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\DocumentRequirement;
use Database\Seeders\OfficialOjtFormsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialOjtFormConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_2024_catalog_stages_repeat_rules_and_reviewers_are_synchronized(): void
    {
        $campus = Campus::forceCreate(['code' => 'AAA', 'name' => 'Test Campus', 'status' => 'active']);
        $this->seed(OfficialOjtFormsSeeder::class);

        $form1 = DocumentRequirement::where('campus_id', $campus->id)->where('code', 'APPLICATION_FOR_OJT')->firstOrFail();
        $this->assertSame('Form 1 – Application for Internship', $form1->name);
        $this->assertSame('pre_deployment', $form1->stage);
        $this->assertTrue($form1->is_required);
        $this->assertSame('instructor', $form1->reviewer_role);
        $this->assertNotNull($form1->source_reference);

        $form17 = DocumentRequirement::where('campus_id', $campus->id)->where('code', 'INTERNSHIP_TIME_FRAME')->firstOrFail();
        $this->assertSame('active', $form17->stage);
        $this->assertTrue($form17->is_repeating);
        $this->assertSame('daily', $form17->entry_cadence);

        $form19 = DocumentRequirement::where('campus_id', $campus->id)->where('code', 'WEEKLY_REPORT')->firstOrFail();
        $this->assertSame('active', $form19->stage);
        $this->assertTrue($form19->is_repeating);
        $this->assertSame('daily', $form19->entry_cadence);

        $moa = DocumentRequirement::where('campus_id', $campus->id)->where('code', 'MOA')->firstOrFail();
        $this->assertFalse($moa->is_active);
        $this->assertFalse($moa->is_required);
    }
}
