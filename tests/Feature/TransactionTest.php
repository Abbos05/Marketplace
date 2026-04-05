<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Nft;
use App\Models\Transaction;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_complete_transaction()
    {
        $buyer = User::factory()->create(['role' => 'user', 'balance' => 1000]);
        $seller = User::factory()->create(['role' => 'user', 'balance' => 500]);
        
        $nft = Nft::factory()->create([
            'status' => 'relevant',
            'price' => 100,
            'user_id' => $seller->id
        ]);

        $response = $this->actingAs($buyer)
            ->post('/payment/wallet', [
                'nft_id' => $nft->id
            ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('transactions', [
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'nft_id' => $nft->id,
            'amount' => $nft->price,
            'status' => 'completed'
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $buyer->id,
            'balance' => 900
        ]);
        
        $this->assertDatabaseHas('users', [
            'id' => $seller->id,
            'balance' => 600
        ]);

        $this->assertDatabaseHas('nfts', [
            'id' => $nft->id,
            'status' => 'sold'
        ]);
    }

    public function test_transaction_fails_with_insufficient_balance()
    {
        $buyer = User::factory()->create(['role' => 'user', 'balance' => 50]);
        $seller = User::factory()->create(['role' => 'user']);
        
        $nft = Nft::factory()->create([
            'status' => 'relevant',
            'price' => 100,
            'user_id' => $seller->id
        ]);

        $response = $this->actingAs($buyer)
            ->post('/payment/wallet', [
                'nft_id' => $nft->id
            ]);

        $response->assertStatus(302);

        $this->assertDatabaseMissing('transactions', [
            'buyer_id' => $buyer->id,
            'nft_id' => $nft->id
        ]);
    }
}