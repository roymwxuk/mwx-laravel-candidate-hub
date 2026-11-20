<?php

namespace Tests\Feature;

use App\Enums\Stage;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\ResumeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.demo_mode' => true]);
        Storage::fake('local');
        $this->seed();
    }

    public function test_reset_restores_the_seeded_data_and_keeps_the_visitor_signed_in(): void
    {
        $hr = User::query()->where('email', 'hr@example.com')->sole();

        // Simulate a visitor playing with the demo.
        Candidate::factory()->count(3)->create();
        $extra = User::factory()->create(['email' => 'added-during-demo@example.com']);
        Storage::disk('local')->put('resumes/uploaded.pdf', 'pdf');
        ResumeDocument::factory()->create(['path' => 'resumes/uploaded.pdf']);

        $this->actingAs($hr)
            ->post(route('demo.reset'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $this->assertSame(19, Candidate::count());
        $this->assertSame(19, AuditLog::count());
        $this->assertSame(0, ResumeDocument::count());
        $this->assertSame(4, User::count());
        $this->assertDatabaseMissing('users', ['email' => $extra->email]);
        Storage::disk('local')->assertMissing('resumes/uploaded.pdf');

        $this->assertAuthenticatedAs(User::query()->where('email', 'hr@example.com')->sole());
    }

    public function test_reset_puts_changed_stages_back(): void
    {
        $hr = User::query()->where('email', 'hr@example.com')->sole();
        $candidate = Candidate::query()->where('email', 'role-a-01@example.com')->sole();
        $candidate->transitionTo(Stage::FirstInterview, $hr);

        $this->actingAs($hr)->post(route('demo.reset'));

        $this->assertSame(Stage::DocumentScreening, Candidate::query()->where('email', 'role-a-01@example.com')->sole()->stage);
    }

    public function test_a_visitor_whose_account_no_longer_exists_is_signed_out(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->sole();
        $extraAdmin = User::factory()->create(['email' => 'temp-admin@example.com', 'role' => $admin->role]);

        $this->actingAs($extraAdmin)
            ->post(route('demo.reset'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_interviewers_cannot_reset(): void
    {
        $this->actingAs(User::query()->where('email', 'interviewer@example.com')->sole())
            ->post(route('demo.reset'))
            ->assertForbidden();
    }

    public function test_reset_is_unavailable_outside_demo_mode(): void
    {
        config(['app.demo_mode' => false]);

        $this->actingAs(User::query()->where('email', 'admin@example.com')->sole())
            ->post(route('demo.reset'))
            ->assertNotFound();

        $this->actingAs(User::query()->where('email', 'admin@example.com')->sole())
            ->get(route('dashboard'))
            ->assertDontSee('デモデータをリセット');
    }

    public function test_the_button_is_shown_to_hr_in_demo_mode(): void
    {
        $this->actingAs(User::query()->where('email', 'hr@example.com')->sole())
            ->get(route('dashboard'))
            ->assertSee('デモデータをリセット');
    }
}
