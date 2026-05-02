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
            $table->enum('status', [
                'new', 'paid', 'processing', 'ready_for_pickup', 'in_transit',
                'at_pvz', 'issued', 'canceled', 'returned'
            ])->default('new');
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
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};