<?php

namespace App\Services;

use App\Models\Order;
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
            ->sortBy('created_at')
            ->values()
            ->map(fn ($n) => [
                'id' => $n->id,
                'read' => $n->read_at !== null,
                'title' => $n->data['title'] ?? '',
                'body' => $this->localizeOrderStatusInBody((string) ($n->data['body'] ?? '')),
                'action_url' => $this->resolveActionUrl($n->data['action_url'] ?? null),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values()->all();
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Оставляем только ссылки на конкретные страницы; «/messages?notifications=1» не даёт действия.
     */
    protected function resolveActionUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $path = rtrim($path, '/') ?: '/';
        $query = parse_url($url, PHP_URL_QUERY) ?? '';
        parse_str($query, $params);

        if (preg_match('#^/messages(/embed)?$#', $path) || preg_match('#^/admin/support(/embed)?$#', $path)) {
            if (isset($params['conversation'])) {
                return $url;
            }

            if (isset($params['notifications']) || $params === []) {
                return null;
            }
        }

        return $url;
    }

    protected function localizeOrderStatusInBody(string $body): string
    {
        if ($body === '') {
            return $body;
        }

        return preg_replace_callback(
            '/(новый статус:\s*)([A-Z_]+)(\s*\.)/u',
            fn (array $m) => $m[1].Order::statusLabel($m[2]).$m[3],
            $body,
        ) ?? $body;
    }
}
