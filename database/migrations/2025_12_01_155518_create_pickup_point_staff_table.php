<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_point_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pickup_point_id')->nullable()->constrained('pickup_points')->nullOnDelete();
            $table->enum('type', ['join', 'open']);
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('inn', 12)->nullable();
            $table->enum('org_type', ['ip', 'ooo', 'self'])->nullable();
            $table->string('legal_name', 200)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('proposed_title', 120)->nullable();
            $table->string('proposed_address', 500)->nullable();
            $table->foreignId('proposed_region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->json('working_hours')->nullable();
            $table->string('premises_info', 300)->nullable();
            $table->text('application_comment')->nullable();
            $table->timestamp('consent_accepted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });

        DB::statement('CREATE UNIQUE INDEX pickup_point_staff_approved_point ON pickup_point_staff (pickup_point_id, status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_point_staff');
    }
};
