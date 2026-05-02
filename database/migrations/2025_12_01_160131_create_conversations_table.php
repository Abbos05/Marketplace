<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('subject')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
    
            $table->foreign('seller_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
