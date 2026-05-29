<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pvz_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('pickup_point_id')->constrained('pickup_points')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('order_total', 12, 2)->nullable();
            $table->string('type', 20)->default('issued');
            $table->char('period', 7)->comment('YYYY-MM');
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['user_id', 'period']);
            $table->index(['pickup_point_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pvz_accruals');
    }
};
