<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\SellerController;
use App\Http\Middleware\CheckRole;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;


Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/home', [HomeController::class, 'index'])->name('home');
Route::get('/category', [HomeController::class, 'category'])->name('category');
Route::get('/logout', [HomeController::class, 'index'])->name('home');
Route::get('/category/{id}', [CategoryController::class, 'show'])->name('category.show');
Route::middleware('auth')->group(function () {
    Route::post('/product/{product}/stop', [ProductController::class, 'stopSelling'])->name('product.stop');
    Route::resource('product', ProductController::class);
});
Route::get('/product/{product}', [ProductController::class, 'show'])->name('product.show');
Route::get('/product/{product}', [ProductController::class, 'show'])->name('product.show');

// о нас
Route::get('/about', function () {
    return Inertia::render('About');
})->name('about');

Route::get('/contacts', function () {
    return Inertia::render('Contacts');
})->name('contacts');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ping', function () {
        DB::table('sessions')
            ->where('id', request()->session()->getId())
            ->update(['last_activity' => time()]);

        return redirect()->back();
    })->middleware(['throttle:60,1']);

  

Route::middleware(['auth'])->group(function () {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/update/{id}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('/order/create', [CartController::class, 'create'])->name('order.create');
     Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::delete('/cart/{id}', [CartController::class, 'destroy'])->name('cart.destroy');
});
    // Корзина

    // Избранное
    Route::post('/favorites/{product}', [HomeController::class, 'favorites'])
        ->name('favorites.toggle');
    Route::get('/favorites', [ProfileController::class, 'favorites'])
        ->name('favorites.index');

    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/cancel', function () {
        return view('checkout.cancel');
    })->name('checkout.cancel');

    Route::post('/stripe/checkout', [StripePaymentController::class, 'createCheckoutSession'])
        ->name('stripe.checkout');
    Route::post('/payment/wallet', [StripePaymentController::class, 'wallet'])->name('payment.wallet');
    Route::post('/stripe/topup', [StripePaymentController::class, 'topup'])->name('stripe.topup');
    Route::post('/stripe/topupImitatin', [StripePaymentController::class, 'topupImitatin'])->name('stripe.topupImitatin');
    Route::get('/topup/success', [StripePaymentController::class, 'topupSuccess'])->name('topup.success');
    Route::post('/wallet/withdraw', [StripePaymentController::class, 'withdraw'])->middleware('auth');
});

Route::middleware(['auth', CheckRole::class . ':admin'])->group(function () {
    Route::get('/admins', [AdminController::class, 'index'])->name('admins');
    Route::delete('/admin/users/{user}', [ProfileController::class, 'destroy'])->name('admin.users.destroy');
    Route::put('/admin/users/{user}/block', [ProfileController::class, 'block'])->name('admin.users.block');
    Route::delete('/admin/sessions/{session}', [AdminController::class, 'destroy'])
        ->name('admin.sessions.destroy');
    Route::get('/users/{user}', [AdminController::class, 'showUser'])->name('admin.users.show');

    Route::post('/admin/nft/buy', [AdminController::class, 'nftbuy'])->name('admin.nft.buy');
    Route::post('/admin/nft/stop', [AdminController::class, 'nftstop'])->name('admin.nft.stop');
    Route::post('/admin/nft/sold', [AdminController::class, 'nftsold'])->name('admin.nft.sold');
});

// Страница продавца
Route::get('/sellerProfile/{id}', [SellerController::class, 'index'])->name('seller.index');

// routes/web.php

require __DIR__ . '/auth.php';
