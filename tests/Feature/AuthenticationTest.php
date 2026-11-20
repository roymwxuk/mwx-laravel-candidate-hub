<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_screen_renders(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('ログイン');
    }

    public function test_users_can_log_in_and_see_the_dashboard(): void
    {
        $user = User::factory()->hr()->create();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk()->assertSee('ダッシュボード');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_only_admins_can_manage_users(): void
    {
        $this->actingAs(User::factory()->hr()->create())->get(route('users.index'))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('users.store'), [
                'name' => '新規 面接官',
                'email' => 'new@example.com',
                'role' => 'interviewer',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'role' => 'interviewer']);
    }
}
