<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTestModeAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureTestModeAccess::class);
    }

    public function test_guest_is_redirected_from_profile_page(): void
    {
        $this->get('/profile')
            ->assertRedirect('/login');
    }

    public function test_user_can_update_profile_name_without_email_field(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email' => 'old@example.com',
            'name' => 'Old Name',
        ]);

        $this->actingAs($user)
            ->post('/profile/update', [
                'name' => 'Updated Name',
                'email' => 'ignored@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'old@example.com',
        ]);
    }

    public function test_user_can_verify_profile_email_with_code(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email' => 'old@example.com',
        ]);

        $otp = '123456';

        $this->actingAs($user)
            ->postJson('/profile/email/send-code', [
                'email' => 'new@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($user)
            ->postJson('/profile/email/verify-code', [
                'code' => $otp,
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->withSession([
                'profile_email_pending' => 'new@example.com',
                'profile_email_otp_hash' => Hash::make($otp),
                'profile_email_otp_expires' => now()->addMinutes(10)->timestamp,
            ])
            ->postJson('/profile/email/verify-code', [
                'code' => $otp,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
        ]);
    }

    public function test_user_cannot_send_profile_email_code_for_taken_address(): void
    {
        $user = User::factory()->create(['role' => 'user', 'email' => 'mine@example.com']);
        User::factory()->create(['role' => 'user', 'email' => 'taken@example.com']);

        $this->actingAs($user)
            ->postJson('/profile/email/send-code', [
                'email' => 'taken@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_open_orders_page(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get('/orders')
            ->assertOk();
    }
}
