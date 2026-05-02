<?php

// use App\Models\User;

// test('profile page is displayed', function () {
//     $user = User::factory()->create();

//     $response = $this
//         ->actingAs($user)
//         ->get('/profile');

//     $response->assertOk();
// });

// test('profile information can be updated', function () {
//     $user = User::factory()->create();

//     $response = $this
//         ->actingAs($user)
//         ->patch('/profile', [
//             'name' => 'Test User',
//             'email' => 'test@example.com',
//         ])
//         ->assertSessionHasNoErrors()
//         ->assertRedirect('/profile');

//     $response
//         ->assertSessionHasNoErrors()
//         ->assertRedirect('/profile');

//     $user->refresh();

//     $this->assertSame('Test User', $user->name);
//     $this->assertSame('test@example.com', $user->email);
//     $this->assertNull($user->email_verified_at);
// });

// test('email verification status is unchanged when the email address is unchanged', function () {
//     $user = User::factory()->create();

//     $response = $this
//         ->actingAs($user)
//         ->patch('/profile', [
//             'name' => 'Test User',
//             'email' => $user->email,
//         ]);

//     $response
//         ->assertSessionHasNoErrors()
//         ->assertRedirect('/profile');

//     $this->assertNotNull($user->refresh()->email_verified_at);
// });

// test('user can delete their account', function () {
//     $user = User::factory()->create();

//     $response = $this
//         ->actingAs($user)
//         ->delete('/profile', [
//             'password' => 'password',
//         ]);

//     $response
//         ->assertSessionHasNoErrors()
//         ->assertRedirect(route('/'));

//     $this->assertGuest();
//     $this->assertNull($user->fresh());
// });

// test('correct password must be provided to delete account', function () {
//     $user = User::factory()->create();

//     $response = $this
//         ->actingAs($user)
//         ->from('/profile')
//         ->delete('/profile', [
//             'password' => 'wrong-password',
//         ]);

//     $response
//         ->assertSessionHasErrors('password')
//         ->assertRedirect('/profile');

//     $this->assertNotNull($user->fresh());
// });




namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile()
    {
        $user = User::factory()->create(['role' => 'user']);

        $profileData = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'email' => $user->email
        ];

        $response = $this->actingAs($user)
            ->post('/profile/update', $profileData);

        $response->assertStatus(302);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'description' => 'Updated description'
        ]);
    }

    public function test_user_can_delete_profile()
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)
            ->delete('/profile', ['_token' => csrf_token()]);

        $response->assertStatus(302);
        $this->assertSoftDeleted('users', [
            'id' => $user->id
        ]);
    }
}