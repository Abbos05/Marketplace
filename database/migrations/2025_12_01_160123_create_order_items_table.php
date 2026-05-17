<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');

            $table->unsignedBigInteger('variant_id')->nullable();
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null');

            $table->unsignedBigInteger('seller_id')->nullable();
            $table->foreign('seller_id')->references('id')->on('users')->onDelete('set null');

            $table->unsignedInteger('quantity');
            $table->decimal('price_at_purchase', 12, 2);

            // Snapshot комиссии на момент покупки (ставка категории + расчёт)
            $table->decimal('commission_percent', 5, 2)->default(10.00);
            $table->decimal('commission_fixed_amount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('seller_payout_amount', 12, 2)->default(0);
            $table->string('commission_status', 20)->default('pending'); // pending | finalized | reversed
            $table->timestamp('commission_finalized_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
