<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResumeIntakeVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_sees_resume_intake_in_the_nav_and_on_the_dashboard(): void
    {
        $this->actingAs(User::factory()->hr()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('履歴書取込')
            ->assertSee('確認待ちの履歴書')
            ->assertSee(route('resumes.create'), false);
    }

    public function test_interviewers_do_not_see_resume_intake(): void
    {
        $this->actingAs(User::factory()->interviewer()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('履歴書取込')
            ->assertDontSee('確認待ちの履歴書');
    }
}
