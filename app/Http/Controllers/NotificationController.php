<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use App\Services\NotificationFeedService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationFeedService $notificationFeed,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('messages.index', ['notifications' => 1]);
    }

    protected function hubUnread(Request $request): int
    {
        $user = $request->user();

        return app(ChatService::class)->unreadCountFor($user)
            + $this->notificationFeed->unreadCount($user);
    }

    public function markRead(Request $request, string $id)
    {
        $user = $request->user();
        $n = $user->notifications()->where('id', $id)->first();
        if ($n && $n->read_at === null) {
            $n->markAsRead();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'notificationsFeed' => $this->notificationFeed->feedFor($user),
                'notificationsUnreadCount' => $this->notificationFeed->unreadCount($user),
                'hubUnreadCount' => $this->hubUnread($request),
            ]);
        }

        return back();
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json([
                'notificationsFeed' => $this->notificationFeed->feedFor($user),
                'notificationsUnreadCount' => 0,
                'hubUnreadCount' => $this->hubUnread($request),
            ]);
        }

        return back();
    }
}
