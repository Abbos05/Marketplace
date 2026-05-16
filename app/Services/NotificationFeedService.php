<?php

namespace App\Services;

use App\Models\User;

class NotificationFeedService
{
    /**
     * @return list<array{id: string, read: bool, title: string, body: string, action_url: ?string, created_at: ?string}>
     */
    public function feedFor(User $user): array
    {
        return $user->notifications()
            ->orderByDesc('created_at')
            ->limit(80)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'read' => $n->read_at !== null,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'action_url' => $n->data['action_url'] ?? null,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values()->all();
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }
}
