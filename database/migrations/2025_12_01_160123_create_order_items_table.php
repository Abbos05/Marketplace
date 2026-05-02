<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // create_order_items_table.php — ИСПРАВЛЕННАЯ ВЕРСИЯ
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
            $table->decimal('commission_percent', 5, 2)->default(10.00);
            $table->timestamps();
           
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
