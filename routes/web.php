<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\NftController;
use App\Http\Controllers\CheckoutController;
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
    Route::post('/nft/{nft}/stop', [NftController::class, 'stopSelling'])->name('nft.stop');
    Route::resource('nft', NftController::class);
});
Route::get('/nft/{nft}', [NftController::class, 'show'])->name('nft.show');
Route::get('/product/{nft}', [NftController::class, 'show'])->name('nft.show');

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

    Route::post('/carts', [NftController::class, 'Cartstore'])->name('cart.store');
    Route::delete('/carts', [NftController::class, 'CartDestroy'])->name('cart.destroy');
    Route::delete('/nft/{id}', [NftController::class, 'destroy'])->name('nft.destroy');
    // Избранное
    Route::post('/favorites/{nft}', [HomeController::class, 'favorites'])
        ->name('favorites.toggle');

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


// routes/web.php

require __DIR__ . '/auth.php';
