<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisabledFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_and_recovery_routes_are_unavailable(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/reset-password/test-token')->assertNotFound();
    }

    public function test_verification_and_password_confirmation_routes_are_unavailable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/verify-email')->assertNotFound();
        $this->actingAs($user)->get('/confirm-password')->assertNotFound();
    }
}
