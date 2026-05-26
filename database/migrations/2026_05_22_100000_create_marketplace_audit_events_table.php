<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 64);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['subject_user_id', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_audit_events');
    }
};
