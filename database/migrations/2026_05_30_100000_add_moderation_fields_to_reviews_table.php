<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('moderation_comment')->nullable()->after('is_moderated');
            $table->timestamp('moderated_at')->nullable()->after('moderation_comment');
            $table->foreignId('moderated_by')->nullable()->after('moderated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['moderated_by']);
            $table->dropColumn(['moderation_comment', 'moderated_at', 'moderated_by']);
        });
    }
};
