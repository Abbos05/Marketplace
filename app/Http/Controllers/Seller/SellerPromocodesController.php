<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Promocode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SellerPromocodesController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $promocodes = Promocode::where('seller_id', $user->id)
            ->withCount('usages')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => $this->format($p));

        return Inertia::render('Seller/Promocodes/Index', [
            'promocodes' => $promocodes,
            'flash' => session()->only(['success', 'error']),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'code' => 'required|string|max:50|unique:promocodes,code|regex:/^[A-Za-z0-9_\-]+$/',
            'discount_value' => 'required|numeric|min:1|max:100',
            'expires_at' => 'nullable|date|after:now',
            'usage_limit' => 'nullable|integer|min:1',
            'min_order_amount' => 'nullable|numeric|min:0',
        ], [
            'code.required' => 'Необходимо указать код промокода.',
            'code.string' => 'Код промокода должен быть строкой.',
            'code.max' => 'Код промокода не должен превышать 50 символов.',
            'code.unique' => 'Такой промокод уже существует.',
            'code.regex' => 'Код может содержать только буквы, цифры, _ и -.',
            'discount_value.required' => 'Необходимо указать размер скидки.',
            'discount_value.numeric' => 'Скидка должна быть числом.',
            'discount_value.min' => 'Скидка должна быть от 1 до 100.',
            'discount_value.max' => 'Скидка должна быть от 1 до 100.',
            'expires_at.date' => 'Дата окончания должна быть корректной датой.',
            'expires_at.after' => 'Дата окончания должна быть в будущем.',
            'usage_limit.integer' => 'Лимит использований должен быть целым числом.',
            'usage_limit.min' => 'Лимит использований должен быть не менее 1.',
            'min_order_amount.numeric' => 'Минимальная сумма заказа должна быть числом.',
            'min_order_amount.min' => 'Минимальная сумма заказа не может быть отрицательной.',
        ]);

        Promocode::create([
            'seller_id' => $user->id,
            'code' => strtoupper($data['code']),
            'discount_type' => 'percent',
            'discount_value' => $data['discount_value'],
            'expires_at' => $data['expires_at'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_per_user' => 1,
            'min_order_amount' => $data['min_order_amount'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('seller.promocodes')->with('success', 'Промокод создан.');
    }

    public function toggle(Promocode $promo)
    {
        $this->authorize403($promo);

        $promo->update(['is_active' => !$promo->is_active]);

        return back()->with('success', $promo->is_active ? 'Промокод активирован.' : 'Промокод деактивирован.');
    }

    public function destroy(Promocode $promo)
    {
        $this->authorize403($promo);

        if ($promo->usages()->exists()) {
            return back()->with('error', 'Нельзя удалить промокод, который уже был использован.');
        }

        $promo->delete();

        return back()->with('success', 'Промокод удалён.');
    }

    private function authorize403(Promocode $promo): void
    {
        if ($promo->seller_id !== Auth::id()) {
            abort(403);
        }
    }

    private function format(Promocode $p): array
    {
        $expired = $p->expires_at && now()->gt($p->expires_at);

        return [
            'id' => $p->id,
            'code' => $p->code,
            'discount_value' => (float) $p->discount_value,
            'min_order_amount' => $p->min_order_amount ? (float) $p->min_order_amount : null,
            'usage_limit' => $p->usage_limit,
            'usage_per_user' => $p->usage_per_user,
            'usages_count' => $p->usages_count,
            'expires_at' => $p->expires_at?->format('d.m.Y'),
            'expires_at_raw' => $p->expires_at?->toDateString(),
            'is_active' => $p->is_active,
            'is_expired' => $expired,
            'created_at' => $p->created_at->format('d.m.Y'),
        ];
    }
}
