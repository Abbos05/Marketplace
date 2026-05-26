<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\HomeSlideController;
use App\Http\Controllers\Admin\PickupPointController;
use App\Http\Controllers\Admin\ReviewModerationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewVoteController;
use App\Http\Controllers\Seller\SellerProductController;
use App\Http\Controllers\SellerProfileController;
use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\SellerOrderController;
use App\Http\Controllers\Seller\SellerStatisticsController;
use App\Http\Controllers\Seller\SellerPromocodesController;
use App\Http\Controllers\Seller\SellerPromotionController;
use App\Http\Controllers\Admin\AdminPromotionController;
use App\Http\Controllers\Seller\SellerSettingsController;
use App\Http\Controllers\PromocodeController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CommissionDocumentController;
use App\Http\Controllers\PickupCooperateController;
use App\Http\Controllers\SearchSuggestionController;
use App\Http\Controllers\SessionHeartbeatController;
use App\Http\Controllers\PickupPartnerController;
use App\Http\Controllers\Pvz\PvzDashboardController;
use App\Http\Middleware\CheckRole;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;


Route::get('/api/catalog/search-suggestions', SearchSuggestionController::class)
    ->middleware('throttle:90,1')
    ->name('catalog.search-suggestions');

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
Route::get('/article/{sku}', [ArticleController::class, 'show'])->name('article.show')->where('sku', '[0-9]+');

// о нас
Route::get('/about', function () {
    return Inertia::render('About');
})->name('about');

Route::get('/contacts', function () {
    return Inertia::render('Contacts');
})->name('contacts');

Route::get('/help', fn () => Inertia::render('Info/Help'))->name('help');
Route::get('/delivery', fn () => Inertia::render('Info/Delivery'))->name('delivery');
Route::get('/returns', fn () => Inertia::render('Info/Returns'))->name('returns');
Route::get('/privacy', fn () => Inertia::render('Info/Privacy'))->name('privacy');
Route::get('/terms', fn () => Inertia::render('Info/Terms'))->name('terms');

Route::get('/pickup/cooperate', [PickupCooperateController::class, 'show'])->name('pickup.cooperate');
Route::get('/pickup/partner', [PickupPartnerController::class, 'landing'])->name('pickup.partner');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/api/session/heartbeat', SessionHeartbeatController::class)
        ->middleware(['throttle:20,1'])
        ->name('session.heartbeat');

    // Корзина
    Route::middleware(['auth'])->group(function () {
        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('/cart/update/{id}', [CartController::class, 'update'])->name('cart.update');
        Route::delete('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove');
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
    Route::get('/order/{order}/receipt', [\App\Http\Controllers\OrderDocumentController::class, 'receipt'])->name('order.receipt');
    Route::get('/order/{order}/document/{docType}', [\App\Http\Controllers\OrderDocumentController::class, 'financial'])->name('order.document');
    Route::post('/order/create', [OrderController::class, 'create'])->name('order.create');

    // Действия с заказом
    Route::post('/promo/validate', [PromocodeController::class, 'validate'])->name('promo.validate');

    Route::post('/order/{order}/cancel', [OrderController::class, 'cancel'])->name('order.cancel');
    Route::get('/order/{order}/refund/checkout', [OrderController::class, 'refundCheckout'])->name('order.refund.checkout');
    Route::post('/order/{order}/refund/complete', [OrderController::class, 'refundComplete'])->name('order.refund.complete');
    Route::post('/order/{order}/refund', [OrderController::class, 'refund'])->name('order.refund');
    Route::post('/order/{order}/repeat', [OrderController::class, 'repeat'])->name('order.repeat');

    // Оплата
    Route::post('/payment/order-wallet', [StripePaymentController::class, 'orderWallet'])->name('payment.order.wallet');
    Route::get('/checkout/cancel', function () {
        return view('checkout.cancel');
    })->name('checkout.cancel');

    Route::post('/stripe/checkout', [StripePaymentController::class, 'createCheckoutSession']);
    Route::post('/stripe/order-checkout', [StripePaymentController::class, 'createOrderCheckoutSession']);
    Route::get('/stripe/success', [StripePaymentController::class, 'success'])->name('stripe.success');

    Route::get('/messages', [ChatController::class, 'index'])->name('messages.index');
    Route::get('/messages/embed', [ChatController::class, 'embed'])->name('messages.embed');
    Route::post('/messages/open', [ChatController::class, 'open'])->name('messages.open');
    Route::get('/messages/poll', [ChatController::class, 'poll'])->middleware('throttle:120,1')->name('messages.poll');
    Route::post('/messages/{conversation}/messages', [ChatController::class, 'storeMessage'])->name('messages.messages.store');
    Route::patch('/messages/{conversation}/messages/{message}', [ChatController::class, 'updateMessage'])->name('messages.messages.update');
    Route::delete('/messages/{conversation}/messages/{message}', [ChatController::class, 'destroyMessage'])->name('messages.messages.destroy');
    Route::post('/messages/{conversation}/hide', [ChatController::class, 'hide'])->name('messages.hide');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});

Route::middleware(['auth', CheckRole::class . ':admin,moderator'])->group(function () {
    Route::get('/admins', [AdminController::class, 'index'])->name('admins');
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/admin/users/{user}/detail', [AdminController::class, 'userDetail'])->name('admin.users.detail');
    Route::post('/admin/sellers/{user}/approve', [AdminController::class, 'approveSeller'])->name('admin.sellers.approve');
    Route::post('/admin/sellers/{user}/reject', [AdminController::class, 'rejectSeller'])->name('admin.sellers.reject');
    Route::post('/admin/sellers/{user}/approve-shop-changes', [AdminController::class, 'approveShopChanges'])->name('admin.sellers.approve-shop-changes');
    Route::post('/admin/sellers/{user}/reject-shop-changes', [AdminController::class, 'rejectShopChanges'])->name('admin.sellers.reject-shop-changes');
    Route::post('/admin/pickup-staff/{pickupPointStaff}/approve', [AdminController::class, 'approvePickupStaff'])->name('admin.pickup-staff.approve');
    Route::post('/admin/pickup-staff/{pickupPointStaff}/reject', [AdminController::class, 'rejectPickupStaff'])->name('admin.pickup-staff.reject');
    Route::post('/admin/products/{product}/status', [AdminController::class, 'updateProductStatus'])->name('admin.products.status');
    Route::post('/admin/orders/{order}/status', [AdminController::class, 'updateOrderStatus'])->name('admin.orders.status');
    Route::post('/admin/users/{userId}/restore', [AdminController::class, 'restoreUser'])->name('admin.users.restore');
    Route::get('/admin/products', [AdminController::class, 'products'])->name('admin.products.index');

    Route::get('/admin/home-slides', [HomeSlideController::class, 'index'])->name('admin.home-slides.index');
    Route::post('/admin/home-slides', [HomeSlideController::class, 'store'])->name('admin.home-slides.store');
    Route::patch('/admin/home-slides/{homeSlide}', [HomeSlideController::class, 'update'])->name('admin.home-slides.update');
    Route::delete('/admin/home-slides/{homeSlide}', [HomeSlideController::class, 'destroy'])->name('admin.home-slides.destroy');

    Route::get('/admin/pickup-points', [PickupPointController::class, 'index'])->name('admin.pickup-points.index');
    Route::post('/admin/pickup-points', [PickupPointController::class, 'store'])->name('admin.pickup-points.store');
    Route::patch('/admin/pickup-points/{pickupPoint}', [PickupPointController::class, 'update'])->name('admin.pickup-points.update');
    Route::delete('/admin/pickup-points/{pickupPoint}', [PickupPointController::class, 'destroy'])->name('admin.pickup-points.destroy');
    Route::post('/admin/pickup-points/{pickupPoint}/assign-operator', [PickupPointController::class, 'assignOperator'])->name('admin.pickup-points.assign-operator');
    Route::post('/admin/pickup-points/{pickupPoint}/approve-closure', [PickupPointController::class, 'approveClosure'])->name('admin.pickup-points.approve-closure');
    Route::post('/admin/pickup-points/{pickupPoint}/reject-closure', [PickupPointController::class, 'rejectClosure'])->name('admin.pickup-points.reject-closure');

    Route::get('/admin/promotions', [AdminPromotionController::class, 'index'])->name('admin.promotions.index');
    Route::post('/admin/promotions', [AdminPromotionController::class, 'store'])->name('admin.promotions.store');
    Route::post('/admin/promotions/{promotion}/toggle', [AdminPromotionController::class, 'toggle'])->name('admin.promotions.toggle');
    Route::delete('/admin/promotions/{promotion}', [AdminPromotionController::class, 'destroy'])->name('admin.promotions.destroy');

    Route::get('/admin/reviews', [ReviewModerationController::class, 'index'])->name('admin.reviews.index');
    Route::post('/admin/reviews/{review}/approve', [ReviewModerationController::class, 'approve'])->name('admin.reviews.approve');
    Route::post('/admin/reviews/{review}/reject', [ReviewModerationController::class, 'reject'])->name('admin.reviews.reject');
    Route::post('/admin/reviews/{reviewId}/restore', [ReviewModerationController::class, 'restore'])->name('admin.reviews.restore');

    Route::get('/admin/reports/revenue',        [AdminController::class, 'exportRevenue'])->name('admin.reports.revenue');
    Route::get('/admin/reports/revenue/chart',  [AdminController::class, 'revenueChartData'])->name('admin.reports.revenue.chart');
    Route::get('/admin/reports/revenue/pdf',    [AdminController::class, 'exportRevenuePdf'])->name('admin.reports.revenue.pdf');
    Route::get('/admin/reports/users',          [AdminController::class, 'exportUsers'])->name('admin.reports.users');
    Route::get('/admin/reports/user/{userId}',  [AdminController::class, 'exportUserReport'])->name('admin.reports.user');
    Route::get('/admin/reports/order/{order}',  [AdminController::class, 'exportOrderReceipt'])->name('admin.reports.order');
    Route::get('/admin/reports/order/{order}/commission', [CommissionDocumentController::class, 'orderReceipt'])->name('admin.reports.commission');

    Route::delete('/admin/users/{user}', [ProfileController::class, 'destroy'])->name('admin.users.destroy');
    Route::put('/admin/users/{user}/block', [ProfileController::class, 'block'])->name('admin.users.block');
    Route::delete('/admin/sessions/{session}', [AdminController::class, 'destroy'])
        ->name('admin.sessions.destroy');
    Route::get('/users/{user}', [AdminController::class, 'showUser'])->name('admin.users.show');

    Route::patch('/admin/users/{user}/role', [ProfileController::class, 'changeRole'])->name('admin.users.role');

 
    Route::get('/admin/support', [ChatController::class, 'adminSupport'])->name('admin.support');
    Route::get('/admin/support/embed', [ChatController::class, 'adminSupportEmbed'])->name('admin.support.embed');
    Route::post('/admin/support/{conversation}/assign', [ChatController::class, 'assignSupport'])->name('admin.support.assign');
    Route::post('/admin/support/{conversation}/transfer', [ChatController::class, 'transferSupport'])->name('admin.support.transfer');
});

// Страница продавца
Route::get('/sellerProfile/{id}', [SellerController::class, 'index'])->name('seller.index');
Route::middleware(['auth'])->group(function () {
    Route::get('/pickup/apply', [PickupPartnerController::class, 'applyForm'])->name('pickup.apply.form');
    Route::post('/pickup/apply', [PickupPartnerController::class, 'apply'])->name('pickup.apply');

    Route::post('/seller-profile/store', [SellerProfileController::class, 'store'])->name('seller-profile.store');
    Route::post('/seller-profile/restore', [SellerProfileController::class, 'restore'])->name('seller-profile.restore');
    Route::get('/seller-profile', [SellerProfileController::class, 'getProfile'])->name('seller-profile.get');
    Route::put('/seller-profile/update', [SellerProfileController::class, 'update'])->name('seller-profile.update');



});
// routes/web.php
Route::middleware(['auth', 'seller'])->group(function () {
    Route::get('/seller/dashboard', [DashboardController::class, 'index'])->name('seller.dashboard');
    Route::get('/seller/products', [ProductController::class, 'index'])->name('seller.products');
    // Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');

    Route::get('/seller/products/create', [SellerProductController::class, 'create'])->name('seller.products.create');

    Route::post('/seller/products/store', [SellerProductController::class, 'store'])->name('seller.products.store');

    Route::get('/seller/products/{product}/edit', [SellerProductController::class, 'edit'])
        ->name('seller.products.edit');

    Route::get('/seller/products/{product}/manage', [SellerProductController::class, 'manage'])
        ->name('seller.products.manage');

    Route::post('/seller/products/{product}/visibility', [SellerProductController::class, 'toggleVisibility'])
        ->name('seller.products.visibility');

    Route::post('/seller/products/{product}/variants/{variant}/toggle', [SellerProductController::class, 'toggleVariant'])
        ->name('seller.products.variant.toggle');

    Route::post('/seller/products/{product}/variants/{variant}/stock', [SellerProductController::class, 'updateVariantStock'])
        ->name('seller.products.variant.stock');


    /* POST: multipart + файлы надёжнее, чем PUT (PHP не всегда парсит multipart для PUT) */
    Route::post('/seller/products/{product}', [SellerProductController::class, 'update'])
        ->name('seller.products.update');

    Route::get('/seller/orders', [SellerOrderController::class, 'index'])->name('seller.orders');
    Route::get('/seller/orders/export', [SellerOrderController::class, 'export'])->name('seller.orders.export');
    Route::get('/seller/orders/{order}', [SellerOrderController::class, 'show'])->name('seller.orders.show');
    Route::get('/seller/orders/{order}/commission-receipt', [CommissionDocumentController::class, 'orderReceipt'])->name('seller.orders.commission-receipt');
    Route::post('/seller/orders/{order}/status', [SellerOrderController::class, 'updateStatus'])->name('seller.orders.status');
    Route::get('/seller/statistics', [SellerStatisticsController::class, 'index'])->name('seller.statistics');
    Route::get('/seller/statistics/commission-breakdown', [CommissionDocumentController::class, 'periodBreakdown'])->name('seller.statistics.commission-breakdown');
    Route::get('/seller/statistics/commission-report', [CommissionDocumentController::class, 'periodReport'])->name('seller.statistics.commission-report');
 
    Route::get('/seller/settings', [SellerSettingsController::class, 'index'])->name('seller.settings');
    Route::post('/seller/settings/shop', [SellerSettingsController::class, 'updateShop'])->name('seller.settings.shop');
    Route::post('/seller/settings/account', [SellerSettingsController::class, 'updateAccount'])->name('seller.settings.account');
    Route::post('/seller/settings/password', [SellerSettingsController::class, 'updatePassword'])->name('seller.settings.password');
    Route::post('/seller/settings/company/close', [SellerSettingsController::class, 'destroyCompany'])->name('seller.settings.company.close');

    Route::get('/seller/promocodes', [SellerPromocodesController::class, 'index'])->name('seller.promocodes');
    Route::post('/seller/promocodes', [SellerPromocodesController::class, 'store'])->name('seller.promocodes.store');
    Route::post('/seller/promocodes/{promo}/toggle', [SellerPromocodesController::class, 'toggle'])->name('seller.promocodes.toggle');
    Route::delete('/seller/promocodes/{promo}', [SellerPromocodesController::class, 'destroy'])->name('seller.promocodes.destroy');

    Route::get('/seller/promotions', [SellerPromotionController::class, 'index'])->name('seller.promotions');
    Route::post('/seller/promotions', [SellerPromotionController::class, 'store'])->name('seller.promotions.store');
    Route::post('/seller/promotions/{promotion}/toggle', [SellerPromotionController::class, 'toggle'])->name('seller.promotions.toggle');
    Route::delete('/seller/promotions/{promotion}', [SellerPromotionController::class, 'destroy'])->name('seller.promotions.destroy');
});

Route::middleware(['auth', 'pvz'])->group(function () {
    Route::get('/pvz', [PvzDashboardController::class, 'index'])->name('pvz.dashboard');
    Route::get('/pvz/queue', [PvzDashboardController::class, 'queue'])->name('pvz.queue');
    Route::get('/pvz/orders', [PvzDashboardController::class, 'orders'])->name('pvz.orders');
    Route::post('/pvz/orders/search', [PvzDashboardController::class, 'searchOrders'])->name('pvz.orders.search');
    Route::get('/pvz/reports', [PvzDashboardController::class, 'reports'])->name('pvz.reports');
    Route::get('/pvz/settings', [PvzDashboardController::class, 'settings'])->name('pvz.settings');
    Route::post('/pvz/settings/closure', [PvzDashboardController::class, 'requestClosure'])->name('pvz.settings.closure');
    Route::post('/pvz/orders/{order}/refusal-code', [PvzDashboardController::class, 'sendRefusalCode'])->name('pvz.orders.refusal-code');
    Route::post('/pvz/orders/{order}/status', [PvzDashboardController::class, 'updateOrderStatus'])->name('pvz.orders.status');
    Route::get('/pvz/reports/export', [PvzDashboardController::class, 'exportReport'])->name('pvz.reports.export');
});
// Отзыв
Route::post('/reviews', [ReviewController::class, 'store'])
    ->name('reviews.store')
    ->middleware('auth');
Route::post('/reviews/{review}/vote', [ReviewVoteController::class, 'store'])
    ->name('reviews.vote')
    ->middleware('auth');
Route::put('/reviews/{review}', [ReviewController::class, 'update'])
    ->middleware('auth');
Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])
    ->middleware('auth');

require __DIR__ . '/auth.php';
