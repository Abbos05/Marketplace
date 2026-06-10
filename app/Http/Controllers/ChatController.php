<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatService;
use App\Services\NotificationFeedService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function __construct(
        protected ChatService $chat,
        protected NotificationFeedService $notificationFeed,
    ) {
    }

    protected function hubUnreadCount(User $user): int
    {
        return $this->chat->unreadCountFor($user) + $this->notificationFeed->unreadCount($user);
    }

    /**
     * @return array<string, mixed>
     */
    protected function notificationProps(User $user, Request $request): array
    {
        $activeId = (int) $request->query('conversation', 0);
        $show = $request->boolean('notifications') && $activeId <= 0;

        return [
            'notificationsFeed' => $this->notificationFeed->feedFor($user),
            'notificationsUnreadCount' => $this->notificationFeed->unreadCount($user),
            'showNotifications' => $show,
        ];
    }

    /**
     * @return array{threads: \Illuminate\Support\Collection, active: ?array, messages: array}
     */
    protected function resolveChatPage(User $user, int $activeId, bool $adminSupportQueue, string $supportFilter = 'all'): array
    {
        $active = null;
        $messages = [];

        if ($activeId > 0) {
            $conversation = Conversation::query()
                ->with(['buyer', 'assignedStaff', 'seller', 'product', 'order', 'latestMessage'])
                ->find($activeId);

            if ($conversation && $this->chat->canAccess($user, $conversation)) {
                $this->chat->clearHiddenForUser($conversation, $user);
                $this->chat->markRead($conversation, $user);
                $conversation->refresh();
                $activeQueue = $adminSupportQueue
                    || $this->chat->isStaffSupportQueueContext($conversation, $user, false);
                $active = $this->chat->serializeThread($conversation, $user, $activeQueue);
                $messages = $this->chat->messagesPayload($conversation, $user);
            }
        }

        $threads = $adminSupportQueue
            ? $this->chat->threadsForStaffInbox($user, true, $supportFilter)
            : ($user->isStaff()
                ? $this->chat->threadsForStaffMergedInbox($user, $supportFilter)
                : $this->chat->threadsForStaffInbox($user, false, $supportFilter));

        return [
            'threads' => $threads,
            'active' => $active,
            'messages' => $messages,
        ];
    }

    protected function supportFilterFromRequest(Request $request): string
    {
        return $this->chat->normalizeSupportFilter($request->query('filter'));
    }

    /**
     * @return array<string, int|string>
     */
    protected function adminSupportRouteParams(Request $request, ?Conversation $conversation = null): array
    {
        $params = [];
        if ($conversation) {
            $params['conversation'] = $conversation->id;
        }
        $filter = $this->chat->normalizeSupportFilter(
            $request->query('filter') ?? $request->input('filter')
        );
        if ($filter !== 'all') {
            $params['filter'] = $filter;
        }

        return $params;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $activeId = (int) $request->query('conversation', 0);

        if ($activeId > 0) {
            $peek = Conversation::query()->find($activeId);
            if (
                $peek
                && $user->isStaff()
                && $peek->type === Conversation::TYPE_SUPPORT
                && (int) $peek->buyer_id === (int) $user->id
            ) {
                return redirect()->route('messages.index');
            }
        }

        $supportFilter = $this->supportFilterFromRequest($request);
        $state = $this->resolveChatPage($user, $activeId, false, $supportFilter);
        $supportUnread = $this->chat->supportInboxUnreadFor($user);

        $props = [
            'threads' => $state['threads'],
            'activeConversation' => $state['active'],
            'messages' => $state['messages'],
            'isAdminSupport' => false,
            'embed' => false,
            'supportInboxUnreadCount' => $supportUnread,
            'supportFilter' => $supportFilter,
            ...$this->notificationProps($user, $request),
        ];

        if ($user->isStaff()) {
            $props['staffMergedInbox'] = true;
            $props['staffForTransfer'] = $this->chat->staffMembersForTransfer($user)
                ->map(fn(User $u) => [
                    'id' => $u->id,
                    'name' => trim(($u->name ?? '') . ' ' . ($u->last_name ?? '')),
                    'role' => $u->role,
                ])
                ->values()
                ->all();
        }

        return Inertia::render('Messages/Index', $props);
    }

    /**
     * Тот же чат в компактной вёрстке для iframe (плавающее окно на сайте).
     */
    public function embed(Request $request)
    {
        $user = $request->user();
        $activeId = (int) $request->query('conversation', 0);
        $supportFilter = $this->supportFilterFromRequest($request);
        $state = $this->resolveChatPage($user, $activeId, false, $supportFilter);
        $supportUnread = $user->isStaff() ? $this->chat->supportInboxUnreadFor($user) : 0;

        $staffForTransfer = [];
        if ($user->isStaff()) {
            $staffForTransfer = $this->chat->staffMembersForTransfer($user)
                ->map(fn(User $u) => [
                    'id' => $u->id,
                    'name' => trim(($u->name ?? '') . ' ' . ($u->last_name ?? '')),
                    'role' => $u->role,
                ])
                ->values()
                ->all();
        }

        return Inertia::render('Messages/Index', [
            'threads' => $state['threads'],
            'activeConversation' => $state['active'],
            'messages' => $state['messages'],
            'isAdminSupport' => false,
            'embed' => true,
            'staffMergedInbox' => $user->isStaff(),
            'supportInboxUnreadCount' => $supportUnread,
            'staffForTransfer' => $staffForTransfer,
            'supportFilter' => $supportFilter,
            ...$this->notificationProps($user, $request),
        ]);
    }

    public function adminSupport(Request $request)
    {
        return $this->renderSupportInbox($request, false);
    }

    public function adminSupportEmbed(Request $request)
    {
        return $this->renderSupportInbox($request, true);
    }

    protected function shouldRenderStaffSupportInbox(User $user, ?Conversation $conversation): bool
    {
        return $conversation
            && $conversation->type === Conversation::TYPE_SUPPORT
            && $user->isStaff()
            && (int) $conversation->buyer_id !== (int) $user->id;
    }

    protected function supportInboxRouteName(bool $embed): string
    {
        return $embed ? 'admin.support.embed' : 'admin.support';
    }

    /**
     * @return \Inertia\Response|\Illuminate\Http\RedirectResponse
     */
    protected function renderSupportInbox(Request $request, bool $embed)
    {
        $user = $request->user();
        if (!$user->isStaff()) {
            abort(403);
        }

        $activeId = (int) $request->query('conversation', 0);

        if ($activeId > 0) {
            $peek = Conversation::query()->find($activeId);
            if (
                $peek
                && $peek->type === Conversation::TYPE_SUPPORT
                && (int) $peek->buyer_id === (int) $user->id
            ) {
                return redirect()->route($embed ? 'messages.embed' : 'admin.support');
            }
        }

        $supportFilter = $this->supportFilterFromRequest($request);
        $state = $this->resolveChatPage($user, $activeId, true, $supportFilter);
        $supportUnread = $this->chat->supportInboxUnreadFor($user);

        $staffForTransfer = $this->chat->staffMembersForTransfer($user)
            ->map(fn(User $u) => [
                'id' => $u->id,
                'name' => trim(($u->name ?? '') . ' ' . ($u->last_name ?? '')),
                'role' => $u->role,
            ])
            ->values()
            ->all();

        return Inertia::render('Messages/Index', [
            'threads' => $state['threads'],
            'activeConversation' => $state['active'],
            'messages' => $state['messages'],
            'isAdminSupport' => true,
            'embed' => $embed,
            'supportInboxUnreadCount' => $supportUnread,
            'staffForTransfer' => $staffForTransfer,
            'supportFilter' => $supportFilter,
            ...$this->notificationProps($user, $request),
        ]);
    }

    public function assignSupport(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        if (!$user->isStaff() || $conversation->type !== Conversation::TYPE_SUPPORT) {
            abort(403);
        }

        $this->chat->assignSupport($conversation, $user);

        $embed = $request->boolean('embed');

        if ($embed) {
            return redirect()->route('messages.embed', [
                'conversation' => $conversation->id,
                'filter' => 'mine',
            ]);
        }

        return redirect()->route('admin.support', [
            'conversation' => $conversation->id,
            'filter' => 'mine',
        ]);
    }

    public function transferSupport(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        if (!$user->isStaff() || $conversation->type !== Conversation::TYPE_SUPPORT) {
            abort(403);
        }

        $data = $request->validate([
            'staff_id' => 'required|integer|exists:users,id',
        ], [
            'staff_id.required' => 'Необходимо указать сотрудника.',
            'staff_id.integer' => 'ID сотрудника должен быть числом.',
            'staff_id.exists' => 'Выбранный сотрудник не существует в системе.',
        ]);

        $newAssignee = User::query()->findOrFail($data['staff_id']);
        $this->chat->transferSupport($conversation, $user, $newAssignee);

        $embed = $request->boolean('embed');

        if ($embed) {
            return redirect()->route('messages.embed', [
                'conversation' => $conversation->id,
                'filter' => 'transferred',
            ]);
        }

        return redirect()->route('admin.support', [
            'conversation' => $conversation->id,
            'filter' => 'transferred',
        ]);
    }

    public function poll(Request $request)
    {
        $user = $request->user();
        $activeId = (int) $request->query('conversation', 0);
        $adminQueueOnly = $request->boolean('admin_queue_only') && $user->isStaff();
        $merged = $request->boolean('merged') && $user->isStaff();
        $admin = $request->boolean('admin') && $user->isStaff();
        $supportFilter = $this->chat->normalizeSupportFilter($request->query('filter'));

        $active = null;
        $messages = [];
        $conversation = null;

        if ($activeId > 0) {
            $conversation = Conversation::query()
                ->with(['buyer', 'assignedStaff', 'seller', 'product', 'order', 'latestMessage'])
                ->find($activeId);
            if (!$conversation || !$this->chat->canAccess($user, $conversation)) {
                $conversation = null;
            } elseif ($admin && !$merged && $conversation->type !== Conversation::TYPE_SUPPORT) {
                $conversation = null;
            } elseif (
                $admin
                && $conversation
                && $conversation->type === Conversation::TYPE_SUPPORT
                && (int) $conversation->buyer_id === (int) $user->id
            ) {
                $conversation = null;
            }
            if ($conversation) {
                $this->chat->clearHiddenForUser($conversation, $user);
                $this->chat->markRead($conversation, $user);
                $conversation->refresh();
                $activeQueue = $admin || $this->chat->isStaffSupportQueueContext($conversation, $user, false);
                $active = $this->chat->serializeThread($conversation, $user, $activeQueue);
                $messages = $this->chat->messagesPayload($conversation, $user);
            }
        }

        if ($user->isStaff() && !$adminQueueOnly) {
            $threads = $this->chat->threadsForStaffMergedInbox($user, $supportFilter);
        } elseif ($user->isStaff() && $adminQueueOnly) {
            $threads = $this->chat->threadsFor($user, true, $supportFilter);
        } else {
            $threads = $this->chat->threadsForStaffInbox($user, $admin, $supportFilter);
        }
        $supportUnread = $this->chat->supportInboxUnreadFor($user);

        return response()->json([
            'threads' => $threads,
            'activeConversation' => $active,
            'messages' => $messages,
            'supportFilter' => $supportFilter,
            'chatUnreadCount' => $this->chat->unreadCountFor($user),
            'supportInboxUnreadCount' => $supportUnread,
            'notificationsFeed' => $this->notificationFeed->feedFor($user),
            'notificationsUnreadCount' => $this->notificationFeed->unreadCount($user),
            'hubUnreadCount' => $this->hubUnreadCount($user),
        ]);
    }

    public function open(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|in:support,seller_shop,seller_product,order',
            'seller_id' => 'nullable|integer|exists:users,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'order_id' => 'nullable|integer|exists:orders,id',
            'draft' => 'nullable|string|max:500',
            'embed' => 'nullable|boolean',
        ], [
            'type.required' => 'Необходимо указать тип обращения.',
            'type.string' => 'Тип обращения должен быть текстовым значением.',
            'type.in' => 'Недопустимый тип обращения. Доступные типы: поддержка, продавец (магазин), продавец (товар), заказ.',
            'seller_id.integer' => 'ID продавца должен быть числом.',
            'seller_id.exists' => 'Выбранный продавец не существует в системе.',
            'product_id.integer' => 'ID товара должен быть числом.',
            'product_id.exists' => 'Выбранный товар не существует.',
            'order_id.integer' => 'ID заказа должен быть числом.',
            'order_id.exists' => 'Выбранный заказ не существует.',
            'draft.max' => 'Черновик не должен превышать 500 символов.',
            'embed.boolean' => 'Поле embed должно быть true или false.',
        ]);

        $user = $request->user();
        $type = match ($data['type']) {
            'support' => Conversation::TYPE_SUPPORT,
            'seller_shop' => Conversation::TYPE_SELLER_SHOP,
            'seller_product' => Conversation::TYPE_SELLER_PRODUCT,
            'order' => Conversation::TYPE_ORDER,
        };

        $payload = ['type' => $type];
        if ($type === Conversation::TYPE_SELLER_SHOP) {
            $request->validate(['seller_id' => 'required|integer|exists:users,id']);
            $payload['seller_id'] = (int) $data['seller_id'];
        }
        if ($type === Conversation::TYPE_SELLER_PRODUCT) {
            $request->validate(['product_id' => 'required|integer|exists:products,id']);
            $payload['product_id'] = (int) $data['product_id'];
        }
        if ($type === Conversation::TYPE_ORDER) {
            $request->validate(['order_id' => 'required|integer|exists:orders,id']);
            $payload['order_id'] = (int) $data['order_id'];
        }

        $conversation = $this->chat->openOrCreate($payload, $user);

        $routeParams = ['conversation' => $conversation->id];
        if (!empty($data['draft'])) {
            $routeParams['draft'] = trim((string) $data['draft']);
        }

        if ($request->boolean('embed')) {
            return redirect()->route('messages.embed', $routeParams);
        }

        return redirect()->route('messages.index', $routeParams);
    }

    public function storeMessage(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        if (!$this->chat->canSend($user, $conversation)) {
            abort(403);
        }

        $request->validate([
            'message' => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx',
        ], [
            'message.max' => 'Сообщение не должно превышать 5000 символов.',
            'attachment.file' => 'Загруженный файл повреждён или не является файлом.',
            'attachment.max' => 'Размер файла не должен превышать 10 МБ.',
            'attachment.mimes' => 'Допустимые форматы: JPG, JPEG, PNG, GIF, WEBP, PDF, DOC, DOCX.',
            'attachment.uploaded' => 'Не удалось загрузить файл. Возможно, файл слишком большой или повреждён.',
        ]);

        $file = $request->file('attachment');
        $hasFile = $file && $file->isValid();
        $text = trim((string) $request->input('message', ''));

        if (!$hasFile && $text === '') {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Введите текст или прикрепите файл'], 422);
            }

            return back()->withErrors(['message' => 'Введите текст или прикрепите файл']);
        }

        try {
            $this->chat->sendMessage($conversation, $user, $text, $hasFile ? $file : null);
        } catch (\InvalidArgumentException) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Пустое сообщение'], 422);
            }

            return back()->withErrors(['message' => 'Пустое сообщение']);
        }

        if ($request->wantsJson()) {
            return $this->jsonChatStateAfterMutation($request, $conversation, $user);
        }

        if ($request->boolean('embed')) {
            return redirect()->route('messages.embed', ['conversation' => $conversation->id]);
        }

        $to = route('messages.index', ['conversation' => $conversation->id]);
        if ($this->shouldRenderStaffSupportInbox($user, $conversation)) {
            $to = route('admin.support', ['conversation' => $conversation->id]);
        }

        return redirect()->to($to);
    }

    public function hide(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        if (!$this->chat->canAccess($user, $conversation)) {
            abort(403);
        }

        if (
            $user->isStaff()
            && $conversation->type === Conversation::TYPE_SUPPORT
            && (int) $conversation->buyer_id !== (int) $user->id
        ) {
            $this->chat->closeSupportTicket($conversation, $user);
        } else {
            $this->chat->hideForUser($conversation, $user);
        }

        if ($request->boolean('embed')) {
            return redirect()->route('messages.embed');
        }

        $fallback = $this->shouldRenderStaffSupportInbox($user, $conversation)
            ? route('admin.support')
            : route('messages.index');

        return redirect($fallback);
    }

    public function updateMessage(Request $request, Conversation $conversation, Message $message)
    {
        $user = $request->user();
        if ((int) $message->conversation_id !== (int) $conversation->id) {
            abort(404);
        }
        if (!$this->chat->canSend($user, $conversation)) {
            abort(403);
        }

        $request->validate([
            'message' => 'required|string|max:5000',
        ], [
            'message.required' => 'Необходимо ввести сообщение.',
            'message.string' => 'Сообщение должно быть текстом.',
            'message.max' => 'Сообщение не должно превышать 5000 символов.',
        ]);

        try {
            $this->chat->updateOwnMessage($conversation, $message, $user, $request->input('message'));
        } catch (\InvalidArgumentException) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Пустое сообщение'], 422);
            }

            return back()->withErrors(['message' => 'Пустое сообщение']);
        }

        if ($request->wantsJson()) {
            return $this->jsonChatStateAfterMutation($request, $conversation, $user);
        }

        if ($request->boolean('embed')) {
            return redirect()->route('messages.embed', ['conversation' => $conversation->id]);
        }

        $to = route('messages.index', ['conversation' => $conversation->id]);
        if ($user->isStaff() && $conversation->type === Conversation::TYPE_SUPPORT && (int) $conversation->buyer_id !== (int) $user->id) {
            $to = route('admin.support', ['conversation' => $conversation->id]);
        }

        return redirect()->to($to);
    }

    public function destroyMessage(Request $request, Conversation $conversation, Message $message)
    {
        $user = $request->user();
        if ((int) $message->conversation_id !== (int) $conversation->id) {
            abort(404);
        }
        if (!$this->chat->canSend($user, $conversation)) {
            abort(403);
        }

        $this->chat->deleteOwnMessage($conversation, $message, $user);

        if ($request->wantsJson()) {
            return $this->jsonChatStateAfterMutation($request, $conversation, $user);
        }

        if ($request->boolean('embed')) {
            return redirect()->route('messages.embed', ['conversation' => $conversation->id]);
        }

        $to = route('messages.index', ['conversation' => $conversation->id]);
        if ($user->isStaff() && $conversation->type === Conversation::TYPE_SUPPORT && (int) $conversation->buyer_id !== (int) $user->id) {
            $to = route('admin.support', ['conversation' => $conversation->id]);
        }

        return redirect()->to($to);
    }

    protected function jsonChatStateAfterMutation(Request $request, Conversation $conversation, User $user): \Illuminate\Http\JsonResponse
    {
        $conversation->refresh()->load(['buyer', 'seller', 'product', 'order', 'latestMessage']);

        $supportFilter = $this->chat->normalizeSupportFilter(
            $request->input('filter', $request->query('filter'))
        );
        $adminQueueOnly = $request->boolean('admin_queue_only') && $user->isStaff();
        $merged = $request->boolean('merged') && $user->isStaff();
        $adminQueue = $user->isStaff()
            && $conversation->type === Conversation::TYPE_SUPPORT
            && (int) $conversation->buyer_id !== (int) $user->id;

        if ($user->isStaff() && $adminQueueOnly) {
            $threads = $this->chat->threadsFor($user, true, $supportFilter);
        } elseif ($user->isStaff() && $merged) {
            $threads = $this->chat->threadsForStaffMergedInbox($user, $supportFilter);
        } else {
            $threads = $this->chat->threadsForStaffInbox($user, $adminQueue, $supportFilter);
        }
        $active = $this->chat->serializeThread($conversation, $user, $adminQueue);
        $messages = $this->chat->messagesPayload($conversation, $user);

        return response()->json([
            'threads' => $threads->values()->all(),
            'activeConversation' => $active,
            'messages' => $messages,
            'supportFilter' => $supportFilter,
            'chatUnreadCount' => $this->chat->unreadCountFor($user),
            'supportInboxUnreadCount' => $this->chat->supportInboxUnreadFor($user),
            'notificationsFeed' => $this->notificationFeed->feedFor($user),
            'notificationsUnreadCount' => $this->notificationFeed->unreadCount($user),
            'hubUnreadCount' => $this->hubUnreadCount($user),
        ]);
    }
}
