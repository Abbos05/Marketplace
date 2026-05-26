<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PickupPoint;
use App\Models\PickupPointStaff;
use App\Models\PvzAccrual;
use App\Models\User;

class PvzAdminOverviewService
{
    public function forUser(User $user): ?array
    {
        $staffHistory = $user->pickupPointStaff()
            ->with(['proposedRegion', 'pickupPoint'])
            ->orderByDesc('created_at')
            ->get();

        if ($staffHistory->isEmpty()) {
            return null;
        }

        $approvedStaff = $user->approvedPickupPointStaff()->with('pickupPoint')->first()
            ?? $staffHistory->firstWhere('status', PickupPointStaff::STATUS_APPROVED);

        $point = $approvedStaff?->pickupPoint
            ?? $staffHistory->first()?->pickupPoint;

        if (! $point && ! $approvedStaff?->proposed_title) {
            return null;
        }

        $pickupPointId = $point?->id;
        $pvzService = app(PvzOrderService::class);

        $pointPayload = $point ? [
            'id' => $point->id,
            'title' => $point->title,
            'address' => $point->address,
            'is_active' => $point->is_active,
            'closure_status' => $point->closure_status ?? PickupPoint::CLOSURE_NONE,
            'closure_requested_at' => $point->closure_requested_at,
            'closure_reason' => $point->closure_reason,
            'closure_admin_reject_reason' => $point->closure_admin_reject_reason,
            'closure_admin_rejected_at' => $point->closure_admin_rejected_at,
        ] : null;

        $stats = null;
        $queue = [];
        $ordersAtPoint = [];
        $periodSummaries = [];

        if ($pickupPointId && $user->role === 'pvz') {
            $stats = $pvzService->monthlyStats($user, $pickupPointId);
            $queue = $pvzService->queueForPickup($pickupPointId, 15);
            $periodSummaries = $pvzService->periodSummaries($user, $pickupPointId, 6, false);

            $ordersAtPoint = Order::query()
                ->with(['buyer', 'items.variant.product.images'])
                ->where('pickup_point_id', $pickupPointId)
                ->orderByDesc('created_at')
                ->limit(40)
                ->get()
                ->map(fn (Order $o) => $this->mapOrderForAdmin($o, $user->id));
        } elseif ($pickupPointId) {
            $ordersAtPoint = Order::query()
                ->with(['buyer', 'items.variant.product.images'])
                ->where('pickup_point_id', $pickupPointId)
                ->orderByDesc('created_at')
                ->limit(40)
                ->get()
                ->map(fn (Order $o) => $this->mapOrderForAdmin($o, null));
        }

        $counts = $pickupPointId ? [
            'delivered' => Order::query()->where('pickup_point_id', $pickupPointId)->where('status', Order::STATUS_DELIVERED)->count(),
            'in_transit' => Order::query()->where('pickup_point_id', $pickupPointId)->where('status', Order::STATUS_INTRANSIT)->count(),
            'issued_total' => Order::query()->where('pickup_point_id', $pickupPointId)->where('status', Order::STATUS_ISSUED)->count(),
            'refused_total' => Order::query()->where('pickup_point_id', $pickupPointId)->where('status', Order::STATUS_REFUSED)->count(),
            'issued_by_operator' => Order::query()
                ->where('pickup_point_id', $pickupPointId)
                ->where('issued_by_user_id', $user->id)
                ->where('status', Order::STATUS_ISSUED)
                ->count(),
            'refused_by_operator' => Order::query()
                ->where('pickup_point_id', $pickupPointId)
                ->where('refused_by_user_id', $user->id)
                ->where('status', Order::STATUS_REFUSED)
                ->count(),
            'earnings_total' => (float) PvzAccrual::query()->where('user_id', $user->id)->where('pickup_point_id', $pickupPointId)->sum('amount'),
        ] : null;

        return [
            'point' => $pointPayload,
            'staff_history' => $staffHistory->map(fn (PickupPointStaff $s) => PickupPointStaff::mapForUserDetail($s))->values()->all(),
            'stats' => $stats,
            'counts' => $counts,
            'queue' => $queue,
            'orders_at_point' => $ordersAtPoint,
            'period_summaries' => $periodSummaries,
        ];
    }

    protected function mapOrderForAdmin(Order $o, ?int $operatorUserId): array
    {
        $buyerName = trim(($o->buyer?->name ?? '').' '.($o->buyer?->last_name ?? '')) ?: 'Покупатель';

        return [
            'id' => $o->id,
            'number' => $o->number,
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'total' => $o->total,
            'buyer_name' => $buyerName,
            'created_at' => $o->created_at,
            'issued_at' => $o->issued_at,
            'handled_by_operator' => $operatorUserId && (
                ((int) $o->issued_by_user_id === $operatorUserId && $o->status === Order::STATUS_ISSUED)
                || ((int) $o->refused_by_user_id === $operatorUserId && $o->status === Order::STATUS_REFUSED)
            ),
            'items' => $o->items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->variant?->product?->title ?? '—',
                'product_image' => $item->variant?->product?->images?->firstWhere('is_main', true)?->url,
                'quantity' => $item->quantity,
                'price_at_purchase' => $item->price_at_purchase,
            ]),
        ];
    }
}
