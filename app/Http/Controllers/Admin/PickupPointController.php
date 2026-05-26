<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PickupPoint;
use App\Models\PickupPointStaff;
use App\Models\Region;
use App\Models\User;
use App\Services\PvzClosureService;
use App\Services\PvzNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PickupPointController extends Controller
{
    public function index(): Response
    {
        $points = PickupPoint::query()
            ->with(['region', 'approvedStaff.user'])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (PickupPoint $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'address' => $p->address,
                'region_id' => $p->region_id,
                'region_name' => $p->region?->name,
                'is_active' => $p->is_active,
                'sort_order' => $p->sort_order,
                'operator' => $p->approvedStaff?->user ? [
                    'id' => $p->approvedStaff->user->id,
                    'name' => trim($p->approvedStaff->user->name.' '.($p->approvedStaff->user->last_name ?? '')),
                    'email' => $p->approvedStaff->user->email,
                ] : null,
                'closure_status' => $p->closure_status ?? PickupPoint::CLOSURE_NONE,
                'closure_requested_at' => $p->closure_requested_at,
                'closure_reason' => $p->closure_reason,
                'closure_admin_reject_reason' => $p->closure_admin_reject_reason,
                'closure_admin_rejected_at' => $p->closure_admin_rejected_at,
            ]);

        $regions = Region::query()->orderBy('name')->get(['id', 'name', 'delivery_hours']);

        $assignableUsers = User::query()
            ->whereIn('role', ['user'])
            ->whereDoesntHave('approvedPickupPointStaff')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'last_name', 'email', 'phone']);

        return Inertia::render('Admin/PickupPoints', [
            'pickupPoints' => $points,
            'regions' => $regions,
            'assignableUsers' => $assignableUsers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'address' => 'required|string|max:500',
            'region_id' => 'nullable|exists:regions,id',
            'sort_order' => 'nullable|integer|min:0|max:65535',
        ]);

        PickupPoint::query()->create([
            'title' => $data['title'],
            'address' => $data['address'],
            'region_id' => $data['region_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        return redirect()->route('admin.pickup-points.index')->with('success', 'Пункт выдачи добавлен.');
    }

    public function update(Request $request, PickupPoint $pickupPoint): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:120',
            'address' => 'sometimes|required|string|max:500',
            'region_id' => 'nullable|exists:regions,id',
            'sort_order' => 'nullable|integer|min:0|max:65535',
            'is_active' => 'sometimes|boolean',
        ]);

        if (! empty($data['is_active'])) {
            $data['closure_status'] = PickupPoint::CLOSURE_NONE;
            $data['closure_requested_at'] = null;
            $data['closure_reason'] = null;
            $data['closure_admin_reject_reason'] = null;
            $data['closure_admin_rejected_at'] = null;
        }

        $pickupPoint->update($data);

        return redirect()->route('admin.pickup-points.index')->with('success', 'Пункт выдачи обновлён.');
    }

    public function destroy(PickupPoint $pickupPoint): RedirectResponse
    {
        $pickupPoint->update([
            'is_active' => false,
            'closure_status' => PickupPoint::CLOSURE_NONE,
            'closure_requested_at' => null,
            'closure_reason' => null,
        ]);

        return redirect()->route('admin.pickup-points.index')->with('success', 'Пункт выдачи отключён.');
    }

    public function assignOperator(Request $request, PickupPoint $pickupPoint): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        if (PickupPointStaff::pickupPointHasApprovedStaff($pickupPoint->id)) {
            return back()->with('error', 'На этом пункте уже назначен оператор.');
        }

        $user = User::query()->findOrFail($data['user_id']);

        if ($user->isPvz()) {
            return back()->with('error', 'Пользователь уже является оператором другого ПВЗ.');
        }

        PickupPointStaff::query()->create([
            'user_id' => $user->id,
            'pickup_point_id' => $pickupPoint->id,
            'type' => PickupPointStaff::TYPE_JOIN,
            'status' => PickupPointStaff::STATUS_APPROVED,
            'contact_name' => trim($user->name.' '.($user->last_name ?? '')),
            'contact_phone' => $user->phone,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'consent_accepted_at' => now(),
        ]);

        $pickupPoint->update([
            'is_active' => true,
            'closure_status' => PickupPoint::CLOSURE_NONE,
            'closure_requested_at' => null,
            'closure_reason' => null,
            'closure_admin_reject_reason' => null,
            'closure_admin_rejected_at' => null,
        ]);

        $user->update(['role' => 'pvz']);

        return back()->with('success', 'Оператор назначен, пункт активирован.');
    }

    public function approveClosure(PickupPoint $pickupPoint): RedirectResponse
    {
        if ($pickupPoint->closure_status !== PickupPoint::CLOSURE_PENDING) {
            return back()->with('error', 'Нет активного запроса на закрытие.');
        }

        $check = app(PvzClosureService::class)->canRequestClosure($pickupPoint);
        if (! $check['ok'] && str_contains($check['message'], 'заказ')) {
            return back()->with('error', $check['message']);
        }

        $operator = $pickupPoint->approvedStaff?->user;

        $pickupPoint->update([
            'is_active' => false,
            'closure_status' => PickupPoint::CLOSURE_CLOSED,
            'closure_requested_at' => null,
            'closure_admin_reject_reason' => null,
            'closure_admin_rejected_at' => null,
        ]);

        if ($operator) {
            $operator->update(['role' => 'user']);
            $pickupPoint->approvedStaff?->update(['status' => PickupPointStaff::STATUS_REJECTED]);
            app(PvzNotificationService::class)->notifyClosureApproved($operator);
        }

        return back()->with('success', 'Пункт выдачи закрыт.');
    }

    public function rejectClosure(Request $request, PickupPoint $pickupPoint): RedirectResponse
    {
        if ($pickupPoint->closure_status !== PickupPoint::CLOSURE_PENDING) {
            return back()->with('error', 'Нет активного запроса на закрытие.');
        }

        $data = $request->validate([
            'reject_reason' => 'required|string|min:5|max:1000',
        ], [
            'reject_reason.required' => 'Укажите причину отклонения закрытия — оператор увидит её в уведомлениях.',
            'reject_reason.min' => 'Причина должна содержать не менее :min символов.',
        ], [
            'reject_reason' => 'причина отклонения',
        ]);

        $operator = $pickupPoint->approvedStaff?->user;

        $pickupPoint->update([
            'is_active' => true,
            'closure_status' => PickupPoint::CLOSURE_NONE,
            'closure_requested_at' => null,
            'closure_reason' => null,
            'closure_admin_reject_reason' => $data['reject_reason'],
            'closure_admin_rejected_at' => now(),
        ]);

        if ($operator) {
            app(PvzNotificationService::class)->notifyClosureRejected($operator, $data['reject_reason']);
        }

        return back()->with('success', 'Запрос на закрытие отклонён. Пункт продолжает работу, оператор уведомлён.');
    }
}
