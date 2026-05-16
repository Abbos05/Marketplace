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
        .header { text-align: center; border-bottom: 2px solid var(--backgroundBlack); padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; margin: 0 0 4px; color: var(--background); }
        .header p { margin: 0; font-size: 11px; color: #64748b; }
        .row { display: flex; justify-content: space-between; margin: 6px 0; }
        .label { color: #64748b; }
        .value { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; font-size: 11px; }
        th { background: #f1f5f9; }
        .total { margin-top: 16px; text-align: right; font-size: 14px; font-weight: bold; }
        .footer { margin-top: 24px; font-size: 9px; color: #94a3b8; text-align: center; border-top: 1px dashed #cbd5e1; padding-top: 12px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; }
        .badge--payment { background: #ecfdf5; color: #047857; }
        .badge--refund { background: #eff6ff; color: #1d4ed8; }
        .badge--cancel { background: #fef2f2; color: #b91c1c; }
    </style>
</head>
<body>
@php
    $total = $transactions->sum('amount');
    $badgeClass = 'badge--' . $docType;
@endphp
<div class="doc">
    <div class="header">
        <h1>{{ $title }}</h1>
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
        <span class="value">{{ trim(($order->buyer->name ?? '') . ' ' . ($order->buyer->last_name ?? '')) ?: '—' }}</span>
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
        <span class="value">{{ $order->payment_status }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Товар</th>
                <th>Продавец ID</th>
                <th>Сумма</th>
                <th>Статус</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $tx)
                <tr>
                    <td>{{ $tx->product?->title ?? '—' }}</td>
                    <td>{{ $tx->seller_id }}</td>
                    <td>{{ number_format($tx->amount, 2, ',', ' ') }} ₽</td>
                    <td>{{ $tx->statusLabel() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">Итого: {{ number_format($total, 2, ',', ' ') }} ₽</div>

    <div class="footer">
        Документ сфорерирован {{ now()->format('d.m.Y H:i') }} · Не является фискальным чеком
    </div>
</div>
</body>
</html>
