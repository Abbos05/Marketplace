@extends('mail.layout')

@php
    $actionHref = null;
    if ($actionUrl) {
        $actionHref = str_starts_with($actionUrl, 'http') ? $actionUrl : url($actionUrl);
    }

    $isOrder = str_contains(mb_strtolower($title), 'заказ');
    $badge = $isOrder ? '📦' : '🔔';
    $ctaLabel = $isOrder ? 'Открыть заказ' : 'Перейти';
@endphp

@section('preheader')
    {{ $body }}
@endsection

@section('title')
    {{ $title }} — {{ config('app.name') }}
@endsection

@section('content')
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td style="padding-bottom:16px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td style="vertical-align:middle;padding-right:12px;">
                            <span style="display:inline-block;width:44px;height:44px;line-height:44px;text-align:center;border-radius:12px;background:{{ $isOrder ? '#fff0f4' : '#fff7ed' }};font-size:20px;">{{ $badge }}</span>
                        </td>
                        <td style="vertical-align:middle;">
                            <h1 style="margin:0;font-size:20px;font-weight:700;color:#0f172a;line-height:1.35;">
                                {{ $title }}
                            </h1>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding-bottom:24px;">
                <p style="margin:0;font-size:15px;line-height:1.6;color:#475569;">
                    {{ $body }}
                </p>
            </td>
        </tr>
        @if($actionHref)
            <tr>
                <td align="center" style="padding-bottom:8px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                        <tr>
                            <td align="center" style="border-radius:12px;background:#FF2E63;">
                                <a href="{{ $actionHref }}" target="_blank" style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:12px;">
                                    {{ $ctaLabel }} →
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td align="center" style="padding-top:12px;">
                    <p style="margin:0;font-size:12px;color:#94a3b8;word-break:break-all;">
                        или скопируйте ссылку:<br>
                        <a href="{{ $actionHref }}" style="color:#FF2E63;text-decoration:underline;">{{ $actionHref }}</a>
                    </p>
                </td>
            </tr>
        @endif
    </table>
@endsection

@section('footer')
    Уведомление также доступно в личном кабинете в разделе «Сообщения».
@endsection
