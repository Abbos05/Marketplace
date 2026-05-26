<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->decimal('min_price', 12, 2);
            $table->unsignedBigInteger('sales_count')->default(0);
            $table->enum('status', ['draft', 'moderation', 'approved', 'rejected', 'archived', 'hidden'])->default('moderation');
            $table->text('moderation_comment')->nullable();
            $table->boolean('is_on_action')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};