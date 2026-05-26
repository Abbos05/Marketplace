<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            return;
        }

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('payment_id', 191);
            $table->string('provider', 32)->default('stripe');
            $table->decimal('amount', 12, 2);
            $table->string('status', 32)->default('succeeded');
            $table->json('provider_response')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->unique(['order_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
