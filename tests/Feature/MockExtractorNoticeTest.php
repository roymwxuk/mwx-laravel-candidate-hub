<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockExtractorNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_upload_page_says_so_when_the_mock_extractor_is_in_use(): void
    {
        config(['services.extractor.mock_notice' => true]);

        $this->actingAs(User::factory()->create(['role' => Role::Hr]))
            ->get(route('resumes.create'))
            ->assertOk()
            ->assertSee('ルールベースの抽出（モック）');
    }

    public function test_the_note_is_hidden_by_default(): void
    {
        config(['services.extractor.mock_notice' => false]);

        $this->actingAs(User::factory()->create(['role' => Role::Hr]))
            ->get(route('resumes.create'))
            ->assertOk()
            ->assertDontSee('ルールベースの抽出（モック）');
    }
}
