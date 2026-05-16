<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PickupPoint;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PickupPointController extends Controller
{
    public function index(): Response
    {
        $points = PickupPoint::query()
            ->with('region')
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
            ]);

        $regions = Region::query()->orderBy('name')->get(['id', 'name', 'delivery_hours']);

        return Inertia::render('Admin/PickupPoints', [
            'pickupPoints' => $points,
            'regions' => $regions,
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

        $pickupPoint->update($data);

        return redirect()->route('admin.pickup-points.index')->with('success', 'Пункт выдачи обновлён.');
    }

    public function destroy(PickupPoint $pickupPoint): RedirectResponse
    {
        $pickupPoint->update(['is_active' => false]);

        return redirect()->route('admin.pickup-points.index')->with('success', 'Пункт выдачи отключён.');
    }
}
