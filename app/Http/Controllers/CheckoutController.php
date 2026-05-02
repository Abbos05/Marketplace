<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class CheckoutController extends Controller
{
    public function createCheckoutSession(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'rub',
                        'product_data' => [
                            'name' => $request->nft_title ?? 'NFT Покупка',
                            'images' => [$request->nft_image ?? ''],
                        ],
                        'unit_amount' => $request->amount * 100,  // Цена NFT в копейках
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'success_url' => route('checkout.success', [], true) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('checkout.cancel', [], true),
            'metadata' => [
                'user_id' => auth()->id(),
                'product_id' => $request->product_id,
            ],
        ]);

        // Верни session ID для фронта (React редиректит на session.url)
        return response()->json(['id' => $session->id]);
    }

    public function success(Request $request)
    {
        $session = Session::retrieve($request->get('session_id'));
        // Проверь статус, обнови БД: NFT status = 'sold', создай transaction
        return view('checkout.success');  // Твоя success страница
    }
}