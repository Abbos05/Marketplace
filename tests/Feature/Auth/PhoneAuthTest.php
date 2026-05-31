<?php

use App\Models\LoginChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    Config::set('marketplace.auth.sms_provider', null);
    Config::set('marketplace.auth.sms_login_otp_required', false);
});

test('new phone login skips otp when sms provider is not configured', function () {
    $phone = '79991112233';

    $response = $this->postJson('/auth/phone/send-code', ['phone' => $phone]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'skip_otp' => true,
        ])
        ->assertJsonStructure(['redirect']);

    $this->assertAuthenticated();
    expect(User::where('phone', '7' . substr($phone, 1))->orWhere('phone', $phone)->exists())->toBeTrue();
});

test('user with 2fa skips phone otp and goes to password step', function () {
    $phone = '79992223344';
    User::factory()->create([
        'phone' => $phone,
        'newPassw' => false,
        'password' => Hash::make('secret'),
    ]);

    $response = $this->postJson('/auth/phone/send-code', ['phone' => $phone]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'skip_otp' => true,
            'requires_password' => true,
        ])
        ->assertJsonStructure(['challenge_id'])
        ->assertJsonMissing(['requires_otp' => true]);

    $this->assertGuest();
});

test('user with 2fa and active session still requires notification otp', function () {
    $phone = '79994445566';
    $user = User::factory()->create([
        'phone' => $phone,
        'newPassw' => false,
        'password' => Hash::make('secret'),
    ]);

    DB::table('sessions')->insert([
        'id' => 'test-session-2fa-active',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode(''),
        'last_activity' => now()->timestamp,
    ]);

    $response = $this->postJson('/auth/phone/send-code', ['phone' => $phone]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'delivery_channel' => LoginChallenge::CHANNEL_NOTIFICATION,
            'requires_otp' => true,
            'requires_password' => true,
        ])
        ->assertJsonMissing(['skip_otp' => true]);

    $this->assertGuest();
});

test('forgot password masks email and does not expose test code hint', function () {
    $user = User::factory()->create([
        'email' => 'ivan.petrov@gmail.com',
        'phone' => '79995556677',
        'newPassw' => false,
        'password' => Hash::make('secret'),
    ]);

    $challenge = LoginChallenge::create([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'code_hash' => Hash::make('123456'),
        'channel' => LoginChallenge::CHANNEL_SMS,
        'purpose' => LoginChallenge::PURPOSE_LOGIN,
        'phone_verified_at' => now(),
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/auth/phone/forgot-password/send', [
        'challenge_id' => $challenge->id,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'masked_target' => 'iv***ov@gmail.com',
            'email_sent' => true,
        ]);

    expect($response->json('message'))
        ->toContain('iv***ov@gmail.com')
        ->not->toContain('g***')
        ->not->toContain('Тестовый');
});

test('forgot password uses fallback code hint when email cannot be sent', function () {
    config([
        'marketplace.notifications.email_enabled' => false,
    ]);

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'phone' => '79995556678',
        'newPassw' => false,
        'password' => Hash::make('secret'),
    ]);

    $challenge = LoginChallenge::create([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'code_hash' => Hash::make('123456'),
        'channel' => LoginChallenge::CHANNEL_SMS,
        'purpose' => LoginChallenge::PURPOSE_LOGIN,
        'phone_verified_at' => now(),
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/auth/phone/forgot-password/send', [
        'challenge_id' => $challenge->id,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'email_sent' => false,
            'reset_channel' => 'fallback',
        ]);

    expect($response->json('message'))
        ->toContain('Не удалось отправить код на почту')
        ->toContain('000000');

    $challenge->refresh();
    expect(Hash::check('000000', $challenge->reset_code_hash))->toBeTrue();
});

test('active session on another device requires notification otp', function () {
    $phone = '79993334455';
    $user = User::factory()->create([
        'phone' => $phone,
        'newPassw' => true,
        'password' => Hash::make(uniqid('phone_', true)),
    ]);

    DB::table('sessions')->insert([
        'id' => 'test-session-active',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode(''),
        'last_activity' => now()->timestamp,
    ]);

    $response = $this->postJson('/auth/phone/send-code', ['phone' => $phone]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'delivery_channel' => LoginChallenge::CHANNEL_NOTIFICATION,
            'requires_otp' => true,
        ])
        ->assertJsonMissing(['skip_otp' => true]);

    $this->assertGuest();
});
