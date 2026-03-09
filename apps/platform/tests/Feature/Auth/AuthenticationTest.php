<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('status', null)
            );
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'username' => 'admin_user',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'username' => 'ADMIN_USER',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user->fresh());
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'username' => 'student_user',
        ]);

        $response = $this->from('/login')->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_inactive_users_can_not_authenticate(): void
    {
        $user = User::factory()->create([
            'username' => 'inactive_user',
            'is_active' => false,
        ]);

        $response = $this->from('/login')->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
