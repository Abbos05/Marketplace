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
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('url', 500);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_main')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
