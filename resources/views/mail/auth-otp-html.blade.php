@extends('mail.layout')

@php
    $ttl = (int) config('marketplace.auth.otp_ttl_minutes', 10);
    $digits = preg_split('//u', $code, -1, PREG_SPLIT_NO_EMPTY);
    $emailLogoUrl = rtrim((string) config('app.url'), '/') . '/icons/icon-192.png';
@endphp

@section('preheader')
    Код {{ $code }} для {{ $purposeLabel }}. Действует {{ $ttl }} {{ $ttl === 1 ? 'минуту' : ($ttl < 5 ? 'минуты' : 'минут') }}.
@endsection

@section('title')
    Код подтверждения — {{ config('app.name') }}
@endsection

@section('content')
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center" style="padding-bottom:8px;">
                <img src="{{ $emailLogoUrl }}" alt="{{ config('app.name') }}" width="48" height="48" style="display:block;width:48px;height:48px;border-radius:12px;border:0;">
            </td>
        </tr>
        <tr>
            <td align="center" style="padding-bottom:8px;">
                <h1 style="margin:0;font-size:22px;font-weight:700;color:#0f172a;line-height:1.3;">
                    Код подтверждения
                </h1>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding-bottom:24px;">
                <p style="margin:0;font-size:15px;line-height:1.5;color:#64748b;">
                    Введите код для <strong style="color:#334155;">{{ $purposeLabel }}</strong>
                </p>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding-bottom:20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;">
                    <tr>
                        <td style="background:#f8fafc;border:2px dashed #e2e8f0;border-radius:14px;padding:20px 28px;">
                            @if(count($digits) > 0 && count($digits) <= 8)
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;">
                                    <tr>
                                        @foreach($digits as $d)
                                            <td style="padding:0 4px;">
                                                <span style="display:inline-block;min-width:36px;height:48px;line-height:48px;text-align:center;font-size:28px;font-weight:700;color:#FF2E63;background:#fff;border-radius:10px;border:1px solid #fecdd3;box-shadow:0 2px 8px rgba(255,46,99,0.12);">{{ $d }}</span>
                                            </td>
                                        @endforeach
                                    </tr>
                                </table>
                            @else
                                <span class="email-code" style="font-size:36px;font-weight:700;letter-spacing:10px;color:#FF2E63;font-variant-numeric:tabular-nums;">{{ $code }}</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding-bottom:20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;">
                    <tr>
                        <td style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:10px 16px;">
                            <span style="font-size:13px;color:#9a3412;line-height:1.4;">
                                ⏱ Код действует <strong>{{ $ttl }}</strong> {{ $ttl === 1 ? 'минуту' : ($ttl < 5 ? 'минуты' : 'минут') }}, затем станет недействителен
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="border-top:1px solid #e2e8f0;padding-top:20px;">
                <p style="margin:0;font-size:13px;line-height:1.55;color:#94a3b8;text-align:center;">
                    Если вы не запрашивали код — просто проигнорируйте это письмо.
                    Никому не сообщайте код.
                </p>
            </td>
        </tr>
    </table>
@endsection

@section('footer')
    Это служебное письмо для безопасности вашего аккаунта.
@endsection
