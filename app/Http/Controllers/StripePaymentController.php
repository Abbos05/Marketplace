<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use App\Models\Nft;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StripePaymentController extends Controller
{
    public function createCheckoutSession(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'rub',
                    'product_data' => ['name' => $request->title],
                    'unit_amount' => $request->price * 100,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',

            // StripePaymentController.php
            'success_url' => route('nft.show', ['nft' => $request->nft_id]) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('nft.show', ['nft' => $request->nft_id]),
            'metadata' => [
                'nft_id' => $request->nft_id,
            ],
        ]);

        return response()->json(['url' => $session->url]);
    }

    public function wallet(Request $request)
    {
        $nft = Nft::findOrFail($request->nft_id);
        $user = auth()->user();
        $seller = $nft->user;
        if ($user->balance < $nft->price) {
            return back()->with('error', 'Недостаточно средств');
        }

        // Списываем

        $user->decrement('balance', $nft->price);
        $seller->increment('balance', $nft->price);
        \App\Models\Transaction::create([
            'nft_id'    => $nft->id,
            'amount'    => $nft->price,
            'buyer_id'  => auth()->id(),           // ← КТО КУПИЛ
            'seller_id' => $nft->user_id,          // ← КТО ПРОДАЛ
            'status'    => 'completed',

        ]);

        $nft->update([
            'status' => 'sold',
            'user_id' => $user->id,
        ]);

        return back()->with('success', 'Куплено с кошелька!');
    }

    public function topup(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'rub',
                    'product_data' => ['name' => 'Пополнение кошелька'],
                    'unit_amount' => $request->amount * 100,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('topup.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('profile'),
            'metadata' => ['user_id' => auth()->id(), 'amount' => $request->amount],
        ]);

        return response()->json(['url' => $session->url]);
    }
    // PaymentController.php
    public function topupImitatin(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:50'
        ]);
    
        $user = auth()->user();
        $amount = $request->input('amount');
    
        // Логика имитации пополнения - просто добавляем сумму к балансу пользователя
        $user->balance += $amount;
        $user->save();
    
        return response()->json(['success' => true]);
    }
    public function topupSuccess(Request $request)
    {
        $session = Session::retrieve($request->session_id);
        if ($session->payment_status === 'paid') {
            $user = User::find($session->metadata->user_id);
            $amount = $session->metadata->amount;
            $user->increment('balance', $amount);

            return redirect()->intended('/')
                ->with('topup_success', "Кошелёк пополнен на +{$amount} ₽!");
        }
    }

    // Вывод
public function withdraw(Request $request)
{
    $request->validate([
        'amount' => 'required|numeric|min:100',
        'card_number' => 'required|size:16|regex:/^\d+$/',
    ]);

    $user = auth()->user();

    if ($user->balance < $request->amount) {
        return back()->withErrors(['message' => 'Недостаточно средств']);
    }

    $user->decrement('balance', $request->amount);

    // Создай заявку на вывод (опционально)
    // Withdrawal::create([...]);

    return back();
}
}
