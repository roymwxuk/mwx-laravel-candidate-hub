<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResumeIntakeVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_resume_intake_is_hidden_when_switched_off(): void
    {
        config(['app.resume_intake' => false]);

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('履歴書取込')
            ->assertDontSee('確認待ちの履歴書')
            ->assertDontSee(route('resumes.create'), false);
    }

    public function test_resume_intake_is_shown_when_switched_on(): void
    {
        config(['app.resume_intake' => true]);

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('履歴書取込')
            ->assertSee('確認待ちの履歴書');
    }
}
