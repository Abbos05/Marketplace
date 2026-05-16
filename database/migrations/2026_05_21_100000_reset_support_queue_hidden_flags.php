<?php

use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $chat = app(ChatService::class);

        Conversation::query()
            ->where('type', Conversation::TYPE_SUPPORT)
            ->each(fn (Conversation $c) => $chat->clearHiddenSupportForAllStaff($c));
    }

    public function down(): void
    {
        // no rollback
    }
};
