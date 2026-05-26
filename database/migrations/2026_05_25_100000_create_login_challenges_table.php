<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 15);
            $table->string('code_hash');
            $table->string('reset_code_hash')->nullable();
            $table->string('channel', 20); // sms | notification
            $table->string('purpose', 30)->default('login'); // login | password_reset
            $table->string('reset_channel', 20)->nullable(); // phone | email (for password_reset)
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('reset_verified_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('reset_attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
            $table->index(['phone', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_challenges');
    }
};
