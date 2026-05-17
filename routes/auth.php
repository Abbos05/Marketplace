<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\YandexController;
use App\Http\Controllers\Auth\GitHubController;
use App\Http\Controllers\Auth\PhoneAuthController;
use App\Http\Controllers\HomeController;
use Inertia\Inertia;

use App\Http\Controllers\PDFController;

Route::get('/download-certificate/{tx}', [PDFController::class, 'download']);
Route::middleware('guest')->group(function () {

    Route::get('login', [AuthenticatedSessionController::class, 'showLogin'])->name('login');
    Route::get('register', [AuthenticatedSessionController::class, 'showRegister'])->name('register');

    // Телефонная авторизация
    Route::post('/auth/phone/send-code',        [PhoneAuthController::class, 'sendCode'])->name('phone.send-code');
    Route::post('/auth/phone/verify-code',       [PhoneAuthController::class, 'verifyCode'])->name('phone.verify-code');
    Route::post('/auth/phone/send-email-code',   [PhoneAuthController::class, 'sendEmailCode'])->name('phone.send-email-code');
    Route::post('/auth/phone/verify-email-code', [PhoneAuthController::class, 'verifyEmailCode'])->name('phone.verify-email-code');
    Route::post('/auth/phone/login-password',    [PhoneAuthController::class, 'loginWithPassword'])->name('phone.login-password');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
}); 

Route::middleware('auth')->group(function () {
    // Индекс профиля
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::get('/profiles', [ProfileController::class, 'filter'])->name('profile.filter');
    // Блокировка/разблокировка пользователя
    Route::patch('/profile/{user}/block', [ProfileController::class, 'block'])->name('profile.block')->whereNumber('user');

    // Редактирование профиля
    Route::post('/profile', [ProfileController::class, 'index'])->name('profile.update');
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/default-pickup', [ProfileController::class, 'updateDefaultPickup'])->name('profile.default-pickup');
    Route::delete('/profile/sessions/{session}', [ProfileController::class, 'destroySession'])->name('profile.sessions.destroy');
    Route::post('/profile/phone/send-code', [ProfileController::class, 'sendPhoneCode'])->name('profile.phone.send-code');
    Route::post('/profile/phone/verify-code', [ProfileController::class, 'verifyPhoneCode'])->name('profile.phone.verify-code');
    Route::post('/profile/update-phone', [ProfileController::class, 'updatePhone'])->name('profile.update.phone');
    // Удаление профиля
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Обновление пароля
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    Route::delete('/user/delete', [ProfileController::class, 'destroy']);

});

Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback'); // Используем GET вместо POST



Route::get('/auth/yandex', [YandexController::class, 'redirectToYandex'])->name('auth.yandex');
Route::get('/auth/yandex/callback', [YandexController::class, 'handleYandexCallback'])->name('auth.yandex.callback');


// Перенаправление на GitHub для авторизации
Route::get('/auth/github', [GitHubController::class, 'redirectToProvider'])->name('auth.github');
;

// Обратный вызов после авторизации
Route::get('/auth/github/callback', [GitHubController::class, 'handleCallback'])->name('auth.github.callback');
;

Route::middleware('web')->group(function () {
    Route::post('/update', [HomeController::class, 'update']);
});
