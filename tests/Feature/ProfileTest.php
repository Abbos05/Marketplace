<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTestModeAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_user_can_update_profile_name_and_email(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email' => 'old@example.com',
            'name' => 'Old Name',
        ]);

        $this->actingAs($user)
            ->post('/profile/update', [
                'name' => 'Updated Name',
                'email' => 'new@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_user_cannot_update_profile_with_taken_email(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create([
            'role' => 'user',
            'email' => 'taken@example.com',
        ]);

        $this->actingAs($user)
            ->from('/profile')
            ->post('/profile/update', [
                'name' => 'Name',
                'email' => $otherUser->email,
            ])
            ->assertSessionHasErrors('email')
            ->assertRedirect('/profile');
    }

    public function test_user_can_open_orders_page(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get('/orders')
            ->assertOk();
    }
}