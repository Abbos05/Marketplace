<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            
            // КТО КУПИЛ
            $table->foreignId('buyer_id')
                  ->constrained('users')
                  ->onDelete('cascade');
                  
            // КТО ПРОДАЛ
            $table->foreignId('seller_id')
                  ->constrained('users')
                  ->onDelete('cascade');
                  
            // КАКОЙ NFT
            $table->foreignId('nft_id')
                  ->constrained()
                  ->onDelete('cascade');
                  
            // СУММА
            $table->decimal('amount', 12, 2);
            
            // СТАТУС
            $table->enum('status', ['pending', 'completed', 'failed'])
                  ->default('completed');
                  
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};