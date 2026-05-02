<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;

class AddToCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_nft_to_cart()
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = Product::factory()->create(['status' => 'relevant']);

        $response = $this->actingAs($user)
            ->post('/carts', ['nft' => ['id' => $product->id]]); 

        $response->assertSessionHas('success'); 
        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }
}
