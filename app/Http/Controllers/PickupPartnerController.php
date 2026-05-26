<?php

namespace App\Http\Controllers;

use App\Models\PickupPointStaff;
use App\Models\Region;
use App\Services\PvzNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PickupPartnerController extends Controller
{
    public function landing(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user?->isPvz()) {
            return redirect()->route('pvz.dashboard');
        }

        return Inertia::render('Pickup/Partner', [
            'pendingApplication' => $this->pendingApplicationFor($user),
            'needsVerification' => $user && ! $user->isProfileVerified(),
        ]);
    }

    public function applyForm(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isPvz()) {
            return redirect()->route('pvz.dashboard');
        }

        if (PickupPointStaff::query()->where('user_id', $user->id)->where('status', PickupPointStaff::STATUS_PENDING)->exists()) {
            return redirect()->route('pickup.partner')->with('info', 'Заявка уже на рассмотрении.');
        }

        if (in_array($user->role, ['seller', 'admin', 'moderator', 'pvz'], true)) {
            return redirect()->route('pickup.partner')->withErrors([
                'form' => 'Для этой роли нельзя подать заявку. Используйте отдельный аккаунт.',
            ]);
        }

        if (! $user->isProfileVerified()) {
            return redirect()->route('pickup.partner')->withErrors([
                'form' => 'Сначала пройдите верификацию в профиле: укажите имя, email и подтвердите телефон.',
            ]);
        }

        $regions = Region::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Pickup/Apply', [
            'regions' => $regions,
            'prefill' => [
                'contact_name' => trim($user->name.' '.($user->last_name ?? '')),
                'contact_phone' => $user->phone ?? '',
            ],
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isPvz()) {
            return redirect()->route('pvz.dashboard');
        }

        if (in_array($user->role, ['seller', 'admin', 'moderator', 'pvz'], true)) {
            return back()->withErrors(['form' => 'Для этой роли нельзя подать заявку.']);
        }

        if (! $user->isProfileVerified()) {
            return back()->withErrors([
                'form' => 'Пройдите верификацию в профиле (имя, email и телефон), чтобы подать заявку на ПВЗ.',
            ]);
        }

        if (PickupPointStaff::query()->where('user_id', $user->id)->where('status', PickupPointStaff::STATUS_PENDING)->exists()) {
            return back()->withErrors(['form' => 'У вас уже есть заявка на рассмотрении.']);
        }

        $data = $request->validate([
            'consent' => 'accepted',
            'contact_name' => 'required|string|max:120',
            'contact_phone' => 'required|string|min:10|max:30',
            'inn' => 'required|string|min:10|max:12',
            'org_type' => 'required|in:ip,ooo,self',
            'legal_name' => 'required|string|max:200',
            'proposed_title' => 'required|string|max:120',
            'proposed_address' => 'required|string|max:500',
            'proposed_region_id' => 'nullable|exists:regions,id',
            'premises_info' => 'nullable|string|max:300',
            'application_comment' => 'nullable|string|max:2000',
            'working_hours' => 'nullable|array',
        ], [
            'consent.accepted' => 'Необходимо принять условия соглашения.',
        ], [
            'contact_name' => 'ФИО ответственного',
            'contact_phone' => 'телефон',
            'inn' => 'ИНН',
            'org_type' => 'форма организации',
            'legal_name' => 'юридическое наименование',
            'proposed_title' => 'название пункта',
            'proposed_address' => 'адрес пункта',
            'proposed_region_id' => 'регион',
            'premises_info' => 'помещение',
            'application_comment' => 'комментарий к заявке',
        ]);

        $staff = PickupPointStaff::query()->create([
            'user_id' => $user->id,
            'pickup_point_id' => null,
            'type' => PickupPointStaff::TYPE_OPEN,
            'status' => PickupPointStaff::STATUS_PENDING,
            'contact_name' => $data['contact_name'],
            'contact_phone' => $data['contact_phone'],
            'inn' => $data['inn'],
            'org_type' => $data['org_type'],
            'legal_name' => $data['legal_name'],
            'proposed_title' => $data['proposed_title'],
            'proposed_address' => $data['proposed_address'],
            'proposed_region_id' => $data['proposed_region_id'] ?? null,
            'premises_info' => $data['premises_info'] ?? null,
            'application_comment' => $data['application_comment'] ?? null,
            'working_hours' => $data['working_hours'] ?? null,
            'consent_accepted_at' => now(),
        ]);

        app(PvzNotificationService::class)->notifyApplicationSubmitted($staff->load('user'));

        return redirect()->route('pickup.partner')->with('success', 'Заявка отправлена. Мы проверим данные и свяжемся с вами после одобрения.');
    }

    protected function pendingApplicationFor($user): ?array
    {
        if (! $user) {
            return null;
        }

        $pending = PickupPointStaff::query()
            ->where('user_id', $user->id)
            ->where('status', PickupPointStaff::STATUS_PENDING)
            ->with('proposedRegion')
            ->first();

        if (! $pending) {
            return null;
        }

        return [
            'id' => $pending->id,
            'created_at' => $pending->created_at,
            'contact_name' => $pending->contact_name,
            'proposed_title' => $pending->proposed_title,
            'proposed_address' => $pending->proposed_address,
            'proposed_region_name' => $pending->proposedRegion?->name,
            'legal_name' => $pending->legal_name,
            'inn' => $pending->inn,
        ];
    }
}
