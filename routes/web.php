<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SellerProfileController;
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



    // Корзина
    Route::middleware(['auth'])->group(function () {
        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('/cart/update/{id}', [CartController::class, 'update'])->name('cart.update');
        Route::delete('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove');
        Route::post('/order/create', [CartController::class, 'create'])->name('order.create');
        Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
        Route::delete('/cart/{id}', [CartController::class, 'destroy'])->name('cart.destroy');
    });
    // -- Корзина

    // Избранное
    Route::post('/favorites/{product}', [HomeController::class, 'favorites'])
        ->name('favorites.toggle');
    Route::get('/favorites', [ProfileController::class, 'favorites'])
        ->name('favorites.index');

    // routes/web.php

    Route::get('/orders', [OrderController::class, 'index'])->name('profile.orders');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('order.show');
    Route::post('/order/create', [OrderController::class, 'create']);

    // Создание заказов
    Route::post('/order/create-from-product', [OrderController::class, 'createFromProduct'])->name('order.create.from.product');
    Route::post('/order/create-from-cart', [OrderController::class, 'createFromCart'])->name('order.create.from.cart');

    // Действия с заказом
    Route::post('/order/{order}/cancel', [OrderController::class, 'cancel'])->name('order.cancel');
    Route::post('/order/{order}/repeat', [OrderController::class, 'repeat'])->name('order.repeat');

    // Оплата
    Route::post('/payment/order-wallet', [StripePaymentController::class, 'orderWallet'])->name('payment.order.wallet');
    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/cancel', function () {
        return view('checkout.cancel');
    })->name('checkout.cancel');

    Route::post('/stripe/checkout', [StripePaymentController::class, 'createCheckoutSession']);
    Route::post('/stripe/order-checkout', [StripePaymentController::class, 'createOrderCheckoutSession']);
    Route::get('/stripe/success', [StripePaymentController::class, 'success'])->name('stripe.success');
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
Route::middleware(['auth'])->group(function () {
    Route::post('/seller-profile/store', [SellerProfileController::class, 'store'])->name('seller-profile.store');
    Route::get('/seller-profile', [SellerProfileController::class, 'getProfile'])->name('seller-profile.get');
    Route::put('/seller-profile/update', [SellerProfileController::class, 'update'])->name('seller-profile.update');
});
// Отзыв
Route::post('/reviews', [ReviewController::class, 'store'])
    ->name('reviews.store')
    ->middleware('auth');
Route::put('/reviews/{review}', [ReviewController::class, 'update'])
    ->middleware('auth');
Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);

require __DIR__ . '/auth.php';
