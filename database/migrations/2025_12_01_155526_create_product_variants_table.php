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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 100)->unique();
            $table->json('options');
            $table->decimal('price', 12, 2);
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0.00);
            $table->dateTime('action_start')->nullable();
            $table->dateTime('action_end')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
    
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
