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
        Schema::create('seller_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('shop_name', 120);
            $table->text('description')->nullable();
            $table->string('inn', 12)->nullable();
            $table->string('legal_address', 300)->nullable();
            $table->string('pickup_address', 300);
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedBigInteger('total_sales')->default(0);
            $table->json('working_hours')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_profiles');
    }
};
