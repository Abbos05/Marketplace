<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_points', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('address', 500);
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('closure_status', 20)->default('none');
            $table->timestamp('closure_requested_at')->nullable();
            $table->text('closure_reason')->nullable();
            $table->text('closure_admin_reject_reason')->nullable();
            $table->timestamp('closure_admin_rejected_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_points');
    }
};
