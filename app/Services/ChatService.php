<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatService
{
    /** Окно, в течение которого отправитель может изменить или удалить сообщение (секунды). */
    public const MESSAGE_MUTATE_WINDOW_SECONDS = 900;

    /** Макс. размер вложения в чат (КБ). */
    public const CHAT_ATTACHMENT_MAX_KB = 10240;

    public function unreadCountFor(User $user): int
    {
        return $this->unreadQuery($user, false)->count() + $this->supportInboxUnreadFor($user);
    }

    /** Непрочитанные обращения клиентов в поддержку (только для админа). */
    public function supportInboxUnreadFor(User $user): int
    {
        if (! $user->isStaff()) {
            return 0;
        }

        return $this->unreadQuery($user, true)->count();
    }

    /** @param bool $adminSupportOnly only conversations.type = support for admin queue */
    protected function unreadQuery(User $user, bool $adminSupportOnly): Builder
    {
        return Message::query()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->whereHas('conversation', function (Builder $cq) use ($user, $adminSupportOnly) {
                if ($adminSupportOnly) {
                    $cq->where('type', Conversation::TYPE_SUPPORT)
                        ->where('buyer_id', '!=', $user->id)
                        ->where(fn (Builder $w) => $this->applySupportVisibilityFilter($w, $user))
                        ->whereNotExists(fn ($sq) => $this->hiddenSubquery($sq, $user->id));
                } else {
                    $cq->where(function (Builder $w) use ($user) {
                        $w->where('buyer_id', $user->id)
                            ->orWhere('seller_id', $user->id);
                    })->whereNotExists(fn ($sq) => $this->hiddenSubquery($sq, $user->id));
                    if ($user->isStaff()) {
                        $cq->where(function (Builder $x) use ($user) {
                            $x->where('type', '!=', Conversation::TYPE_SUPPORT)
                                ->orWhere('buyer_id', '!=', $user->id);
                        });
                    }
                }
            });
    }

    protected function hiddenSubquery($sub, int $userId): void
    {
        $sub->select(DB::raw(1))
            ->from('conversation_user')
            ->whereColumn('conversation_user.conversation_id', 'conversations.id')
            ->where('conversation_user.user_id', $userId)
            ->whereNotNull('conversation_user.hidden_at');
    }

    /** Чат убран из общей очереди поддержки (архив для всей команды staff). */
    protected function supportArchivedSubquery($sub): void
    {
        $sub->select(DB::raw(1))
            ->from('conversation_user')
            ->whereColumn('conversation_user.conversation_id', 'conversations.id')
            ->whereNotNull('conversation_user.hidden_at')
            ->whereIn('conversation_user.user_id', function ($q) {
                $q->select('id')->from('users')->whereIn('role', ['admin', 'moderator']);
            });
    }

    /** @return list<int> */
    public function staffUserIds(): array
    {
        return User::query()
            ->whereIn('role', ['admin', 'moderator'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isSupportArchivedForStaff(Conversation $c): bool
    {
        if ($c->type !== Conversation::TYPE_SUPPORT) {
            return false;
        }

        $staffIds = $this->staffUserIds();
        if ($staffIds === []) {
            return false;
        }

        return DB::table('conversation_user')
            ->where('conversation_id', $c->id)
            ->whereIn('user_id', $staffIds)
            ->whereNotNull('hidden_at')
            ->exists();
    }

    public function hideSupportForAllStaff(Conversation $c): void
    {
        if ($c->type !== Conversation::TYPE_SUPPORT) {
            return;
        }

        $now = now();
        foreach ($this->staffUserIds() as $staffId) {
            DB::table('conversation_user')->updateOrInsert(
                [
                    'conversation_id' => $c->id,
                    'user_id'         => $staffId,
                ],
                [
                    'hidden_at'  => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function clearHiddenSupportForAllStaff(Conversation $c): void
    {
        if ($c->type !== Conversation::TYPE_SUPPORT) {
            return;
        }

        $this->clearHiddenForUser($c, $c->buyer);

        if ($c->assigned_staff_id) {
            $assignee = User::query()->find($c->assigned_staff_id);
            if ($assignee) {
                $this->clearHiddenForUser($c, $assignee);
            }
        }
    }

    public function normalizeSupportFilter(?string $filter): string
    {
        return in_array($filter, ['all', 'new', 'mine', 'transferred'], true) ? $filter : 'all';
    }

    /** Диалоги, которые сотрудник может видеть в очереди (новые, свои, переданные с историей). */
    protected function applySupportVisibilityFilter(Builder $q, User $staff): void
    {
        $q->where(function (Builder $w) use ($staff) {
            $w->whereNull('assigned_staff_id')
                ->orWhere('assigned_staff_id', $staff->id)
                ->orWhereExists(fn ($sq) => $this->staffMessagedSubquery($sq, $staff->id));
        });
    }

    protected function applySupportInboxFilter(Builder $q, User $staff, string $filter): void
    {
        match ($this->normalizeSupportFilter($filter)) {
            'new' => $q->whereNull('assigned_staff_id')
                ->whereExists(fn ($sq) => $this->unreadBuyerSupportMessageSubquery($sq)),
            'mine' => $q->where('assigned_staff_id', $staff->id),
            'transferred' => $q->whereNotNull('assigned_staff_id')
                ->where('assigned_staff_id', '!=', $staff->id)
                ->whereExists(fn ($sq) => $this->staffMessagedSubquery($sq, $staff->id)),
            default => $this->applySupportVisibilityFilter($q, $staff),
        };
    }

    protected function unreadBuyerSupportMessageSubquery($sub): void
    {
        $sub->select(DB::raw(1))
            ->from('messages')
            ->whereColumn('messages.conversation_id', 'conversations.id')
            ->whereColumn('messages.sender_id', 'conversations.buyer_id')
            ->where('messages.is_read', false);
    }

    protected function staffMessagedSubquery($sub, int $staffId): void
    {
        $sub->select(DB::raw(1))
            ->from('messages')
            ->whereColumn('messages.conversation_id', 'conversations.id')
            ->where('messages.sender_id', $staffId);
    }

    public function staffParticipatedInSupport(Conversation $c, User $staff): bool
    {
        return Message::query()
            ->where('conversation_id', $c->id)
            ->where('sender_id', $staff->id)
            ->exists();
    }

    public function isSupportAssignedTo(Conversation $c, User $staff): bool
    {
        return $c->type === Conversation::TYPE_SUPPORT
            && $c->assigned_staff_id !== null
            && (int) $c->assigned_staff_id === (int) $staff->id;
    }

    public function canStaffViewSupport(Conversation $c, User $staff): bool
    {
        if ((int) $c->buyer_id === (int) $staff->id) {
            return false;
        }

        if ($c->assigned_staff_id === null) {
            return true;
        }

        if ((int) $c->assigned_staff_id === (int) $staff->id) {
            return true;
        }

        return $this->staffParticipatedInSupport($c, $staff);
    }

    public function assignSupport(Conversation $c, User $staff): void
    {
        if ($c->type !== Conversation::TYPE_SUPPORT) {
            abort(422, 'Недопустимый тип диалога');
        }

        if ((int) $c->buyer_id === (int) $staff->id) {
            abort(422, 'Нельзя назначить себя клиентом');
        }

        if (
            $c->assigned_staff_id !== null
            && (int) $c->assigned_staff_id !== (int) $staff->id
            && ! $this->staffParticipatedInSupport($c, $staff)
        ) {
            abort(403, 'Обращение уже ведёт другой сотрудник');
        }

        $c->update(['assigned_staff_id' => $staff->id]);
        $this->clearHiddenForUser($c, $staff);
        $c->refresh();
    }

    public function transferSupport(Conversation $c, User $actor, User $newAssignee): void
    {
        if ($c->type !== Conversation::TYPE_SUPPORT) {
            abort(422, 'Недопустимый тип диалога');
        }

        if (! $this->isSupportAssignedTo($c, $actor)) {
            abort(403, 'Передать может только текущий оператор');
        }

        if ((int) $newAssignee->id === (int) $c->buyer_id || ! $newAssignee->isStaff()) {
            abort(422, 'Некорректный получатель');
        }

        $c->update(['assigned_staff_id' => $newAssignee->id]);
        $this->clearHiddenForUser($c, $newAssignee);
        $c->refresh();
    }

    public function closeSupportTicket(Conversation $c, User $staff): void
    {
        if ($c->type !== Conversation::TYPE_SUPPORT) {
            return;
        }

        if (
            $c->assigned_staff_id !== null
            && (int) $c->assigned_staff_id !== (int) $staff->id
        ) {
            abort(403, 'Закрыть может только текущий оператор');
        }

        $this->hideForUser($c, $staff);
        $c->update(['assigned_staff_id' => null]);
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    public function staffMembersForTransfer(User $except): \Illuminate\Support\Collection
    {
        return User::query()
            ->whereIn('role', ['admin', 'moderator'])
            ->where('id', '!=', $except->id)
            ->orderBy('name')
            ->get(['id', 'name', 'last_name', 'role']);
    }

    public function canAccess(User $user, Conversation $conversation): bool
    {
        if ($conversation->type === Conversation::TYPE_SUPPORT && $user->isStaff()) {
            return $this->canStaffViewSupport($conversation, $user);
        }

        if ($conversation->type === Conversation::TYPE_SUPPORT) {
            return (int) $conversation->buyer_id === (int) $user->id;
        }

        return $conversation->buyer_id === $user->id
            || $conversation->seller_id === $user->id;
    }

    public function canSend(User $user, Conversation $conversation): bool
    {
        if (! $this->canAccess($user, $conversation)) {
            return false;
        }

        if ($conversation->type === Conversation::TYPE_SUPPORT) {
            if ($user->isStaff() && (int) $conversation->buyer_id === (int) $user->id) {
                return false;
            }

            if ($user->isStaff()) {
                return $this->isSupportAssignedTo($conversation, $user);
            }

            return (int) $conversation->buyer_id === (int) $user->id;
        }

        return $conversation->buyer_id === $user->id
            || $conversation->seller_id === $user->id;
    }

    public function threadsFor(User $user, bool $adminSupportQueue, string $supportFilter = 'all'): \Illuminate\Support\Collection
    {
        $q = Conversation::query()
            ->with([
                'buyer',
                'assignedStaff',
                'seller',
                'product',
                'order',
                'latestMessage',
            ]);

        if ($adminSupportQueue) {
            $q->where('type', Conversation::TYPE_SUPPORT)
                ->where('buyer_id', '!=', $user->id)
                ->where(fn (Builder $w) => $this->applySupportInboxFilter($w, $user, $supportFilter))
                ->whereNotExists(fn ($sq) => $this->hiddenSubquery($sq, $user->id));
        } else {
            $q->where(function (Builder $w) use ($user) {
                $w->where('buyer_id', $user->id)
                    ->orWhere('seller_id', $user->id);
            })->whereNotExists(fn ($sq) => $this->hiddenSubquery($sq, $user->id));
            if ($user->isStaff()) {
                $q->where(function (Builder $x) use ($user) {
                    $x->where('type', '!=', Conversation::TYPE_SUPPORT)
                        ->orWhere('buyer_id', '!=', $user->id);
                });
            }
        }

        return $q->orderByDesc(DB::raw('COALESCE(last_message_at, conversations.updated_at)'))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Conversation $c) => $this->serializeThread($c, $user, $adminSupportQueue));
    }

    /**
     * Список чатов для staff: очередь поддержки + личные переписки (на /messages и в poll).
     */
    public function threadsForStaffInbox(User $user, bool $adminSupportPage, string $supportFilter = 'all'): \Illuminate\Support\Collection
    {
        if ($adminSupportPage) {
            return $this->threadsFor($user, true, $supportFilter);
        }

        if (! $user->isStaff()) {
            return $this->threadsFor($user, false);
        }

        return $this->threadsForStaffMergedInbox($user, 'all');
    }

    /** Очередь поддержки (с фильтром) + личные чаты продавца/покупателя. */
    public function threadsForStaffMergedInbox(User $user, string $supportFilter = 'all'): \Illuminate\Support\Collection
    {
        $supportFilter = $this->normalizeSupportFilter($supportFilter);
        if ($supportFilter !== 'all') {
            return $this->threadsFor($user, true, $supportFilter);
        }

        return $this->threadsFor($user, true, $supportFilter)
            ->concat($this->threadsFor($user, false))
            ->unique(fn (array $row) => $row['id'])
            ->sortByDesc(fn (array $row) => $row['last_message_at'] ?? '')
            ->values();
    }

    public function isStaffSupportQueueContext(Conversation $c, User $viewer, bool $adminSupportQueue): bool
    {
        if ($adminSupportQueue) {
            return true;
        }

        return $c->type === Conversation::TYPE_SUPPORT
            && $viewer->isStaff()
            && (int) $c->buyer_id !== (int) $viewer->id;
    }

    public function serializeThread(Conversation $c, User $viewer, bool $adminSupportQueue): array
    {
        $last = $c->latestMessage;
        $unread = Message::query()
            ->where('conversation_id', $c->id)
            ->where('sender_id', '!=', $viewer->id)
            ->where('is_read', false)
            ->count();

        $supportContext = $this->isStaffSupportQueueContext($c, $viewer, $adminSupportQueue);

        $counterpart = $this->counterpart($c, $viewer, $supportContext);
        $title = $this->threadTitle($c, $viewer, $supportContext);

        $previewText = $this->threadPreviewSnippet($last);
        $fromYou = $last && $last->sender_id === $viewer->id;
        $previewPrefix = $fromYou ? 'Вы' : ($counterpart['short'] ?? 'Собеседник');

        $cpMeta = $this->counterpartProfileMeta($c, $viewer, $supportContext);

        $assignedStaff = $c->assignedStaff;
        $assignedName = $assignedStaff
            ? trim(($assignedStaff->name ?? '').' '.($assignedStaff->last_name ?? ''))
            : '';

        $supportQueueStatus = null;
        $supportCanReply = null;
        if ($c->type === Conversation::TYPE_SUPPORT && $viewer->isStaff() && $supportContext) {
            if ($c->assigned_staff_id === null) {
                $supportQueueStatus = 'unassigned';
            } elseif ((int) $c->assigned_staff_id === (int) $viewer->id) {
                $supportQueueStatus = 'mine';
            } elseif ($this->staffParticipatedInSupport($c, $viewer)) {
                $supportQueueStatus = 'transferred';
            } else {
                $supportQueueStatus = 'other';
            }
            $supportCanReply = $this->isSupportAssignedTo($c, $viewer);
        }

        return [
            'id'              => $c->id,
            'type'            => $c->type,
            'title'           => $title,
            'preview'         => $previewText,
            'preview_prefix'  => $previewPrefix,
            'avatar_url'      => $counterpart['avatar'],
            'last_message_at' => $last?->created_at?->toIso8601String(),
            'unread_count'    => $unread,
            'can_attach_files'=> $this->counterpartHasMessaged($c, $viewer),
            'assigned_staff_id' => $c->assigned_staff_id,
            'assigned_staff_name' => $assignedName !== '' ? $assignedName : null,
            'support_queue_status' => $supportQueueStatus,
            'support_can_reply' => $supportCanReply,
            ...$cpMeta,
        ];
    }

    /**
     * Собеседник в шапке чата: id пользователя и куда можно перейти (профиль / витрина / никуда).
     *
     * @return array{counterpart_user_id: int|null, counterpart_profile_kind: string}
     */
    public function counterpartProfileMeta(Conversation $c, User $viewer, bool $adminSupportQueue): array
    {
        if ($c->type === Conversation::TYPE_SUPPORT) {
            if (
                $viewer->isStaff()
                && $c->buyer_id
                && (int) $c->buyer_id !== (int) $viewer->id
            ) {
                return $this->profileKindPayload($c->buyer_id, $viewer);
            }

            return ['counterpart_user_id' => null, 'counterpart_profile_kind' => 'none'];
        }

        if ($c->buyer_id === $viewer->id) {
            $otherId = $c->seller_id;
        } else {
            $otherId = $c->buyer_id;
        }

        return $this->profileKindPayload($otherId, $viewer);
    }

    /**
     * @return array{counterpart_user_id: int|null, counterpart_profile_kind: string}
     */
    public function profileKindPayload(?int $userId, User $viewer): array
    {
        if ($userId === null || $userId === 0) {
            return ['counterpart_user_id' => null, 'counterpart_profile_kind' => 'none'];
        }

        if ($userId === $viewer->id) {
            return ['counterpart_user_id' => $viewer->id, 'counterpart_profile_kind' => 'self'];
        }

        $u = User::query()->find($userId);
        if (! $u) {
            return ['counterpart_user_id' => null, 'counterpart_profile_kind' => 'none'];
        }

        if ($u->isSeller()) {
            return ['counterpart_user_id' => $userId, 'counterpart_profile_kind' => 'seller_store'];
        }

        if ($viewer->isStaff()) {
            return ['counterpart_user_id' => $userId, 'counterpart_profile_kind' => 'member'];
        }

        return ['counterpart_user_id' => $userId, 'counterpart_profile_kind' => 'none'];
    }

    /**
     * @return array{sender_name: string, sender_display_name: string, sender_role: string, sender_is_staff: bool}
     */
    protected function senderDisplayMeta(?User $sender, ?User $viewer = null): array
    {
        if (! $sender) {
            return [
                'sender_name'           => '',
                'sender_display_name'   => 'Пользователь',
                'sender_role'           => 'user',
                'sender_is_staff'       => false,
            ];
        }

        $name = trim(($sender->name ?? '').' '.($sender->last_name ?? ''));
        $role = (string) ($sender->role ?? 'user');
        $isStaff = $sender->isStaff();

        if ($isStaff) {
            $display = 'Поддержка';
        } else {
            $display = $name !== '' ? $name : 'Пользователь';
        }

        return [
            'sender_name'         => $name,
            'sender_display_name' => $display,
            'sender_role'         => $role,
            'sender_is_staff'     => $isStaff,
        ];
    }

    /**
     * @return array{short: string, avatar: ?string}
     */
    protected function counterpart(Conversation $c, User $viewer, bool $adminSupportQueue): array
    {
        if ($c->type === Conversation::TYPE_SUPPORT) {
            if ($viewer->isStaff() && $adminSupportQueue) {
                $b = $c->buyer;

                return [
                    'short'   => $b ? ($b->name . ($b->last_name ? ' ' . $b->last_name : '')) : 'Клиент',
                    'avatar'  => $b?->avatar ? $this->publicAvatar($b->avatar) : null,
                ];
            }

            if ((int) $c->buyer_id === (int) $viewer->id && $c->assigned_staff_id) {
                $agent = $c->assignedStaff;

                return [
                    'short'  => $agent ? trim(($agent->name ?? '').' '.($agent->last_name ?? '')) : 'Поддержка',
                    'avatar' => $agent?->avatar ? $this->publicAvatar($agent->avatar) : null,
                ];
            }

            return ['short' => 'Поддержка', 'avatar' => null];
        }

        if ($c->buyer_id === $viewer->id) {
            $s = $c->seller;

            return [
                'short'  => $s ? ($s->name . ($s->last_name ? ' ' . $s->last_name : '')) : 'Продавец',
                'avatar' => $s?->avatar ? $this->publicAvatar($s->avatar) : null,
            ];
        }

        $b = $c->buyer;

        return [
            'short'  => $b ? ($b->name . ($b->last_name ? ' ' . $b->last_name : '')) : 'Покупатель',
            'avatar' => $b?->avatar ? $this->publicAvatar($b->avatar) : null,
        ];
    }

    protected function threadTitle(Conversation $c, User $viewer, bool $adminSupportQueue): string
    {
        return match ($c->type) {
            Conversation::TYPE_SUPPORT => $this->supportThreadTitle($c, $viewer, $adminSupportQueue),
            default => ($this->counterpart($c, $viewer, $adminSupportQueue)['short'] ?? 'Собеседник'),
        };
    }

    protected function supportThreadTitle(Conversation $c, User $viewer, bool $adminSupportQueue): string
    {
        if ($viewer->isStaff() && $adminSupportQueue) {
            $label = 'Клиент: '.($c->buyer?->name ?? '#'.$c->buyer_id);
            if ($c->assigned_staff_id === null) {
                return $label.' · новое';
            }
            if ((int) $c->assigned_staff_id === (int) $viewer->id) {
                return $label.' · у вас';
            }
            if ($this->staffParticipatedInSupport($c, $viewer)) {
                $agent = $c->assignedStaff;
                $who = $agent
                    ? trim(($agent->name ?? '').' '.($agent->last_name ?? ''))
                    : '';
                $who = $who !== '' ? $who : 'другого';

                return $label.' · у '.$who;
            }

            return $label;
        }

        if ((int) $c->buyer_id === (int) $viewer->id && $c->assigned_staff_id && $c->assignedStaff) {
            $agent = trim(($c->assignedStaff->name ?? '').' '.($c->assignedStaff->last_name ?? ''));

            return $agent !== '' ? ('Поддержка: '.$agent) : 'Поддержка';
        }

        return 'Поддержка';
    }

    protected function buyerShortLabel(?User $buyer, int $buyerId): string
    {
        if ($buyer) {
            return trim(($buyer->name ?? '').' '.($buyer->last_name ?? '')) ?: ('#'.$buyerId);
        }

        return '#'.$buyerId;
    }

    /** Заголовок в списке: у продавца — кто покупатель; у админа как покупателя — метка, чтобы не путать с чатами клиентов. */
    protected function sellerProductThreadTitle(Conversation $c, User $viewer): string
    {
        $pt = $c->product?->title ?? 'Товар';
        if ((int) $c->seller_id === (int) $viewer->id) {
            return $this->buyerShortLabel($c->buyer, (int) $c->buyer_id).' — '.$pt;
        }
        if ($viewer->isStaff() && (int) $c->buyer_id === (int) $viewer->id) {
            return $pt.' · к продавцу (админ)';
        }

        return $pt;
    }

    protected function sellerShopThreadTitle(Conversation $c, User $viewer): string
    {
        $shop = $c->seller?->name ?? 'Магазин';
        if ((int) $c->seller_id === (int) $viewer->id) {
            return $this->buyerShortLabel($c->buyer, (int) $c->buyer_id).' — '.$shop;
        }
        if ($viewer->isStaff() && (int) $c->buyer_id === (int) $viewer->id) {
            return $shop.' · к продавцу (админ)';
        }

        return $shop;
    }

    protected function publicAvatar(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return '/'.ltrim($path, '/');
    }

    public function counterpartHasMessaged(Conversation $c, User $viewer): bool
    {
        return Message::query()
            ->where('conversation_id', $c->id)
            ->where('sender_id', '!=', $viewer->id)
            ->exists();
    }

    protected function threadPreviewSnippet(?Message $last): string
    {
        if (! $last) {
            return '';
        }
        if ($last->attachment_path) {
            $isImg = $last->attachment_mime && str_starts_with((string) $last->attachment_mime, 'image/');
            $lead = $isImg ? '📷 Фото' : '📎 Файл';
            $name = $last->attachment_original_name ? mb_strimwidth((string) $last->attachment_original_name, 0, 48, '…') : '';
            $snippet = $name !== '' ? ($lead.' '.$name) : $lead;
            $cap = trim((string) $last->message);
            if ($cap !== '') {
                $snippet .= ' · '.mb_strimwidth($cap, 0, 72, '…');
            }

            return mb_strimwidth($snippet, 0, 120, '…');
        }

        return mb_strimwidth((string) ($last->message ?? ''), 0, 120, '…');
    }

    public function messagesPayload(Conversation $c, User $viewer): array
    {
        $rows = $c->messages()
            ->with('sender')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(200)
            ->get();

        return $rows->map(function (Message $m) use ($viewer) {
            $sender = $m->sender;
            $kind = $this->profileKindPayload($m->sender_id, $viewer);
            $senderMeta = $this->senderDisplayMeta($sender, $viewer);

            $hasAtt = $m->attachment_path !== null && $m->attachment_path !== '';

            return [
                'id'         => $m->id,
                'body'       => $m->message,
                'sender_id'  => $m->sender_id,
                'created_at' => $m->created_at->toIso8601String(),
                'is_edited'  => $m->edited_at !== null,
                'is_own'     => $m->sender_id === $viewer->id,
                'sender_name'=> $senderMeta['sender_name'],
                'sender_display_name' => $senderMeta['sender_display_name'],
                'sender_role' => $senderMeta['sender_role'],
                'sender_is_staff' => $senderMeta['sender_is_staff'],
                'sender_avatar' => $sender?->avatar ? $this->publicAvatar($sender->avatar) : null,
                'sender_profile_user_id'   => $kind['counterpart_user_id'],
                'sender_profile_kind'      => $kind['counterpart_profile_kind'],
                'attachment_url' => $hasAtt ? $this->publicAvatar($m->attachment_path) : null,
                'attachment_mime' => $hasAtt ? (string) $m->attachment_mime : null,
                'attachment_name' => $hasAtt ? (string) ($m->attachment_original_name ?? '') : null,
                'is_attachment_image' => $hasAtt && $m->attachment_mime && str_starts_with((string) $m->attachment_mime, 'image/'),
            ];
        })->values()->all();
    }

    public function markRead(Conversation $c, User $reader): void
    {
        Message::query()
            ->where('conversation_id', $c->id)
            ->where('sender_id', '!=', $reader->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function hideForUser(Conversation $c, User $user): void
    {
        DB::table('conversation_user')->updateOrInsert(
            [
                'conversation_id' => $c->id,
                'user_id'         => $user->id,
            ],
            [
                'hidden_at' => now(),
                'updated_at'=> now(),
                'created_at'=> now(),
            ]
        );
    }

    public function clearHiddenForUser(Conversation $c, User $user): void
    {
        DB::table('conversation_user')->updateOrInsert(
            [
                'conversation_id' => $c->id,
                'user_id'         => $user->id,
            ],
            [
                'hidden_at'  => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function ensureParticipantRows(Conversation $c): void
    {
        foreach (array_filter([$c->buyer_id, $c->seller_id]) as $uid) {
            $exists = DB::table('conversation_user')
                ->where('conversation_id', $c->id)
                ->where('user_id', $uid)
                ->exists();
            if (! $exists) {
                DB::table('conversation_user')->insert([
                    'conversation_id' => $c->id,
                    'user_id'         => $uid,
                    'hidden_at'       => null,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }

    /**
     * Снять скрытие у всех участников pivot, кроме отправителя — чат снова появится в списке у тех, кто скрывал.
     */
    protected function reopenHiddenForRecipientsExceptSender(Conversation $c, User $sender): void
    {
        DB::table('conversation_user')
            ->where('conversation_id', $c->id)
            ->where('user_id', '!=', $sender->id)
            ->update(['hidden_at' => null, 'updated_at' => now()]);
    }

    /**
     * @return list<string>
     */
    protected function chatAttachmentAllowedExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];
    }

    protected function deleteAttachmentFromDisk(Message $message): void
    {
        if (! $message->attachment_path || ! str_starts_with($message->attachment_path, 'img/chat/')) {
            return;
        }
        $full = public_path($message->attachment_path);
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public function sendMessage(Conversation $c, User $sender, string $text, ?UploadedFile $file = null): Message
    {
        $text = trim($text);
        $hasFile = $file instanceof UploadedFile && $file->isValid();

        if (! $hasFile && $text === '') {
            throw new \InvalidArgumentException('empty');
        }

        if ($hasFile && ! $this->counterpartHasMessaged($c, $sender)) {
            abort(422, 'Файл можно отправить только после ответа собеседника в этом чате.');
        }

        $relPath = null;
        $mime = null;
        $origName = null;

        if ($hasFile) {
            $ext = strtolower($file->getClientOriginalExtension() ?: '');
            if (! in_array($ext, $this->chatAttachmentAllowedExtensions(), true)) {
                abort(422, 'Допустимы изображения (JPEG, PNG, GIF, WEBP), PDF или Word (DOC, DOCX).');
            }
            if ($file->getSize() > self::CHAT_ATTACHMENT_MAX_KB * 1024) {
                abort(422, 'Файл не больше '.(int) (self::CHAT_ATTACHMENT_MAX_KB / 1024).' МБ.');
            }
            $origName = $file->getClientOriginalName();
            $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
            $dir = public_path('img/chat/'.$c->id);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $safeFile = Str::random(10).'_'.time().'.'.$ext;
            $file->move($dir, $safeFile);
            $relPath = 'img/chat/'.$c->id.'/'.$safeFile;
        }

        $m = $c->messages()->create([
            'sender_id' => $sender->id,
            'message'   => $text,
            'is_read'   => false,
            'attachment_path' => $relPath,
            'attachment_mime' => $mime,
            'attachment_original_name' => $origName,
        ]);
        $c->update(['last_message_at' => now()]);

        if ($c->type === Conversation::TYPE_SUPPORT) {
            if ($sender->isStaff() && ! $this->isSupportAssignedTo($c, $sender)) {
                abort(403, 'Сначала возьмите обращение в работу');
            }
            $this->clearHiddenSupportForAllStaff($c->fresh());
            $this->reopenHiddenForRecipientsExceptSender($c, $sender);
        } else {
            $this->reopenHiddenForRecipientsExceptSender($c, $sender);
        }

        return $m;
    }

    public function messageWithinMutateWindow(Message $message): bool
    {
        return $message->created_at->copy()->addSeconds(self::MESSAGE_MUTATE_WINDOW_SECONDS)->isFuture();
    }

    public function updateOwnMessage(Conversation $conversation, Message $message, User $user, string $text): Message
    {
        if ((int) $message->conversation_id !== (int) $conversation->id) {
            abort(404);
        }
        if (! $this->canSend($user, $conversation)) {
            abort(403);
        }
        if ((int) $message->sender_id !== (int) $user->id) {
            abort(403);
        }
        if (! $this->messageWithinMutateWindow($message)) {
            abort(422, 'Время редактирования истекло (15 минут).');
        }
        $text = trim($text);
        if ($text === '' && ! $message->attachment_path) {
            throw new \InvalidArgumentException('empty');
        }
        $message->update([
            'message'   => $text,
            'edited_at' => now(),
        ]);

        return $message->fresh(['sender']);
    }

    public function deleteOwnMessage(Conversation $conversation, Message $message, User $user): void
    {
        if ((int) $message->conversation_id !== (int) $conversation->id) {
            abort(404);
        }
        if (! $this->canSend($user, $conversation)) {
            abort(403);
        }
        if ((int) $message->sender_id !== (int) $user->id) {
            abort(403);
        }
        if (! $this->messageWithinMutateWindow($message)) {
            abort(422, 'Удалить можно только в течение 1 мин 30 с после отправки.');
        }
        $this->deleteAttachmentFromDisk($message);
        $message->delete();
        $conversation->refresh();
        $this->syncConversationLastMessageAt($conversation);
    }

    protected function syncConversationLastMessageAt(Conversation $conversation): void
    {
        $last = $conversation->messages()->orderByDesc('id')->first();
        $conversation->update(['last_message_at' => $last?->created_at]);
    }

    /**
     * @param  array{type: string, seller_id?: int, product_id?: int, order_id?: int}  $data
     */
    public function openOrCreate(array $data, User $user): Conversation
    {
        $type = $data['type'];

        $conversation = match ($type) {
            Conversation::TYPE_SUPPORT => $this->openSupport($user),
            Conversation::TYPE_SELLER_SHOP => $this->openSellerShop($user, (int) $data['seller_id']),
            Conversation::TYPE_SELLER_PRODUCT => $this->openSellerProduct($user, (int) $data['product_id']),
            Conversation::TYPE_ORDER => $this->openOrder($user, (int) $data['order_id']),
            default => throw new \InvalidArgumentException('bad_type'),
        };

        $this->clearHiddenForUser($conversation, $user);
        $this->ensureParticipantRows($conversation);

        return $conversation->fresh(['buyer', 'seller', 'product', 'order']);
    }

    protected function openSupport(User $user): Conversation
    {
        if ($user->isStaff()) {
            abort(422, 'Администратор не создаёт личное обращение в поддержку. Ответы клиентам — в разделе «Поддержка (чаты)».');
        }

        return Conversation::firstOrCreate(
            [
                'buyer_id' => $user->id,
                'type'     => Conversation::TYPE_SUPPORT,
            ],
            [
                'seller_id'       => null,
                'order_id'        => null,
                'product_id'      => null,
                'subject'         => null,
                'last_message_at' => null,
            ]
        );
    }

    protected function openSellerShop(User $user, int $sellerId): Conversation
    {
        if ($sellerId === $user->id) {
            abort(422, 'Нельзя написать самому себе');
        }

        $existing = $this->findDirectConversationByPair($user->id, $sellerId);
        if ($existing) {
            return $existing;
        }

        return Conversation::firstOrCreate(
            [
                'buyer_id'   => $user->id,
                'seller_id'  => $sellerId,
                'type'       => Conversation::TYPE_SELLER_SHOP,
                'product_id' => null,
            ],
            [
                'order_id'        => null,
                'subject'         => null,
                'last_message_at' => null,
            ]
        );
    }

    protected function openSellerProduct(User $user, int $productId): Conversation
    {
        $product = Product::query()->findOrFail($productId);
        $sellerId = (int) $product->seller_id;
        if ($sellerId === $user->id) {
            abort(422, 'Нельзя написать самому себе');
        }

        $existing = $this->findDirectConversationByPair($user->id, $sellerId);
        if ($existing) {
            return $existing;
        }

        return Conversation::create([
            'buyer_id'        => $user->id,
            'seller_id'       => $sellerId,
            'type'            => Conversation::TYPE_SELLER_SHOP,
            'product_id'      => null,
            'order_id'        => null,
            'subject'         => null,
            'last_message_at' => null,
        ]);
    }

    protected function openOrder(User $user, int $orderId): Conversation
    {
        $order = Order::query()->findOrFail($orderId);
        if ($order->buyer_id !== $user->id) {
            abort(403);
        }

        $sellerId = $order->items()
            ->with('variant.product')
            ->get()
            ->pluck('variant.product.seller_id')
            ->filter()
            ->first();

        if ($sellerId) {
            $existing = $this->findDirectConversationByPair($user->id, (int) $sellerId);
            if ($existing) {
                return $existing;
            }

            return Conversation::create([
                'buyer_id'        => $user->id,
                'seller_id'       => (int) $sellerId,
                'type'            => Conversation::TYPE_SELLER_SHOP,
                'product_id'      => null,
                'order_id'        => null,
                'subject'         => null,
                'last_message_at' => null,
            ]);
        }

        return Conversation::firstOrCreate(
            [
                'buyer_id' => $user->id,
                'order_id' => $order->id,
                'type'     => Conversation::TYPE_ORDER,
            ],
            [
                'seller_id'       => null,
                'product_id'      => null,
                'subject'         => null,
                'last_message_at' => null,
            ]
        );
    }

    protected function findDirectConversationByPair(int $buyerId, int $sellerId): ?Conversation
    {
        return Conversation::query()
            ->where('buyer_id', $buyerId)
            ->where('seller_id', $sellerId)
            ->whereIn('type', [
                Conversation::TYPE_SELLER_SHOP,
                Conversation::TYPE_SELLER_PRODUCT,
                Conversation::TYPE_ORDER,
            ])
            ->orderByDesc(DB::raw('COALESCE(last_message_at, conversations.updated_at)'))
            ->orderByDesc('id')
            ->first();
    }
}
