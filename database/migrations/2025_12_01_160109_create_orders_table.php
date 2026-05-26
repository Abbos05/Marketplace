<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('order_code', 10)->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pickup_point_id')->nullable()->constrained('pickup_points')->nullOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->enum('status', [
                'NEW', 'INTRANSIT', 'DELIVERED', 'ISSUED', 'CANCELED', 'REFUSED',
            ])->default('NEW');
            $table->decimal('total', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('delivery_address', 400)->nullable();
            // $table->foreignId('pvz_id')->nullable()->constrained('pvz_points')->nullOnDelete();
            // $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->unsignedBigInteger('region_id')->nullable();
            $table->string('delivery_method', 50)->default('pvz');
            $table->string('payment_method', 50)->nullable();
            $table->text('comment')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('region_id')->references('id')->on('regions')->onDelete('set null');
            $table->index('status');
            // $table->index('pvz_id');
            // $table->index('courier_id');
        });

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
        Schema::dropIfExists('pvz_accruals');
        Schema::dropIfExists('orders');
    }
};