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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedTinyInteger('rating')->check('rating BETWEEN 1 AND 5');
            $table->text('comment')->nullable();
            $table->boolean('is_moderated')->default(false);
            $table->softDeletes();
            $table->timestamps();
    
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
