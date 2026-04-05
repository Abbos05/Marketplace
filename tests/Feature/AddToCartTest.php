<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Nft;

class AddToCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_nft_to_cart()
    {
        $user = User::factory()->create(['role' => 'user']);
        $nft = Nft::factory()->create(['status' => 'relevant']);

        $response = $this->actingAs($user)
            ->post('/carts', ['nft' => ['id' => $nft->id]]); 

        $response->assertSessionHas('success'); 
        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id,
            'nft_id' => $nft->id,
        ]);
    }
}
