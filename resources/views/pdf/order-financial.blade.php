<!DOCTYPE html>
<html lang="ru">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif !important; }
        body { font-size: 12px; color: #1e293b; margin: 0; padding: 20px; }
        .doc { border: 1px solid #cbd5e1; border-radius: 8px; padding: 24px; }
        .header { text-align: center; border-bottom: 2px solid #d30035; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; margin: 0 0 4px; color: #FF2E63; }
        .header p { margin: 0; font-size: 11px; color: #64748b; }
        .row { display: flex; justify-content: space-between; margin: 6px 0; }
        .label { color: #64748b; }
        .value { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; font-size: 11px; }
        th { background: #f1f5f9; }
        .total { margin-top: 16px; text-align: right; font-size: 14px; font-weight: bold; }
        .footer { margin-top: 24px; font-size: 9px; color: #94a3b8; text-align: center; border-top: 1px dashed #cbd5e1; padding-top: 12px; }
        .badge {  font-size: 11px; font-weight: bold; }
        .badge--payment { color: #ecfdf5; color: #047857; }
        .badge--refund { color: #eff6ff; color: #1d4ed8; }
        .badge--cancel { color: #fef2f2; color: #b91c1c; }
    </style>
</head>
<body>
@php
    $total = $transactions->sum('amount');
    $commissionTotal = $transactions->sum('commission_amount');
    $payoutTotal = $transactions->sum('seller_payout_amount');
    $badgeClass = 'badge--' . $docType;
@endphp
<div class="doc">
    <div class="header">
        <h1>Маркетплейс Alvora</h1>
        <h2>{{ $title }}</h2>
        <p>Маркетплейс · электронный документ</p>
    </div>

    <div class="row">
        <span class="label">Заказ:</span>
        <span class="value">№ {{ $order->number }}</span>
    </div>
    <div class="row">
        <span class="label">Дата:</span>
        <span class="value">{{ now()->format('d.m.Y H:i') }}</span>
    </div>
    <div class="row">
        <span class="label">Покупатель:</span>
        <span class="value">{{ trim(($order->buyer->name ?? '') . ' ' . ($order->buyer->last_name ?? '')) ?: 'Аккаунт удален' }}</span>
    </div>
    <div class="row">
        <span class="label">Операция:</span>
        <span class="badge {{ $badgeClass }}">
            @if($docType === 'refund') Возврат
            @elseif($docType === 'cancel') Отмена
            @else Оплата
            @endif
        </span>
    </div>
    <div class="row">
        <span class="label">Статус оплаты заказа:</span>
        <span class="value">
        @if($order->payment_status === 'refunded') Возврат уже оформлен
            @elseif($order->payment_status === 'pending') Не оплачен
            @elseif($order->payment_status === 'paid') Оплачена
            @else Не оплачено
        @endif    </span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Товар</th>
                <th>Продавец ID</th>
                <th>Сумма</th>
                <th>Комиссия</th>
                <th>К выплате</th>
                <th>Статус</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $tx)
                <tr>
                    <td>{{ $tx->product?->title ?? '—' }}</td>
                    <td>{{ $tx->seller_id }}</td>
                    <td>{{ number_format($tx->amount, 2, ',', ' ') }} ₽</td>
                    <td>{{ number_format($tx->commission_amount, 2, ',', ' ') }} ₽</td>
                    <td>{{ number_format($tx->seller_payout_amount, 2, ',', ' ') }} ₽</td>
                    <td>{{ $tx->statusLabel() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">
        Итого: {{ number_format($total, 2, ',', ' ') }} ₽ ·
        комиссия: {{ number_format($commissionTotal, 2, ',', ' ') }} ₽ ·
        к выплате: {{ number_format($payoutTotal, 2, ',', ' ') }} ₽
    </div>

    <div class="footer">
        Документ сфорерирован {{ now()->format('d.m.Y H:i') }} · Не является фискальным чеком
    </div>
</div>
</body>
</html>
