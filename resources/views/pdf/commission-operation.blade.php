<!DOCTYPE html>
<html lang="ru">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif !important; }
        body { font-size: 11px; color: #1e293b; margin: 0; padding: 18px; }
        .doc { border: 1px solid #cbd5e1; border-radius: 8px; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #d30035; padding-bottom: 10px; margin-bottom: 16px; }
        .header h1 { font-size: 17px; margin: 0 0 4px; color: #FF2E63; }
        .header p { margin: 0; font-size: 10px; color: #64748b; }
        .row { margin: 4px 0; }
        .label { color: #64748b; display: inline-block; width: 42%; }
        .value { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 10px; }
        th, td { border: 1px solid #e2e8f0; padding: 6px; text-align: left; }
        th { background: #f1f5f9; }
        .totals { margin-top: 14px; width: 100%; }
        .totals td { border: none; padding: 5px 0; }
        .totals .label { width: 70%; }
        .totals .amount { text-align: right; font-weight: bold; width: 30%; }
        .totals .grand { border-top: 2px solid #0f172a; font-size: 12px; padding-top: 8px; }
        .note { margin-top: 12px; font-size: 9px; color: #64748b; line-height: 1.4; }
        .footer { margin-top: 18px; font-size: 8px; color: #94a3b8; text-align: center; border-top: 1px dashed #cbd5e1; padding-top: 10px; }
    </style>
</head>
<body>
@php $t = $aggregate['totals']; @endphp
<div class="doc">
    <div class="header">
        <h1>Маркетплейс Alvora</h1>
        <h2>{{ $title }}</h2>
        <p>Заказ № {{ $order->number }} · {{ now()->format('d.m.Y H:i') }}</p>
    </div>

    @if($seller)
        <div class="row"><span class="label">Продавец:</span> <span class="value">{{ $seller->name }} {{ $seller->last_name }}</span></div>
    @endif
    <div class="row"><span class="label">Покупатель:</span> <span class="value">{{ trim(($order->buyer->name ?? '') . ' ' . ($order->buyer->last_name ?? '')) ?: '—' }}</span></div>
    <div class="row"><span class="label">Контакты:</span> <span class="value">{{ trim(($order->buyer->phone ?? '') . ' ' . ($order->buyer->email ?? '')) ?: '—' }}</span></div>
    <div class="row"><span class="label">Статус заказа:</span> <span class="value">
        @if($order->status === 'CANCELED') заказ отменён
        @elseif($order->status === 'REFUSED') отказ от получения
        @elseif($order->status === 'NEW') новый заказ
        @elseif($order->status === 'INTRANSIT') в пути
        @elseif($order->status === 'DELIVERED') в пункте выдачи
        @elseif($order->status === 'ISSUED') выдан
        @elseif($order->status === 'CANCELED') отменён
        @elseif($order->status === 'REFUSED') отказ от получения
            @endif
      </span></div>

    <table>
        <thead>
            <tr>
                <th>Товар</th>
                <th>Кол-во</th>
                <th>Валовая сумма</th>
                <th>Комиссия</th>
                <th>К выплате</th>
            </tr>
        </thead>
        <tbody>
            @foreach($aggregate['rows'] as $row)
                <tr>
                    <td>{{ $row['product_title'] }}</td>
                    <td>{{ $row['quantity'] }}</td>
                    <td>{{ number_format($row['gross'], 2, ',', ' ') }} ₽</td>
                    <td>{{ number_format($row['commission_total'], 2, ',', ' ') }} ₽</td>
                    <td>{{ number_format($row['seller_payout'], 2, ',', ' ') }} ₽</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="label">Итого валовая сумма (ваши позиции)</td><td class="amount">{{ number_format($t['gross'], 2, ',', ' ') }} ₽</td></tr>
        <tr><td class="label">Итого комиссия платформы</td><td class="amount">{{ number_format($t['commission_total'], 2, ',', ' ') }} ₽</td></tr>
        <tr><td class="label">— {{ $aggregate['split_labels']['payment_fee'] }}</td><td class="amount">{{ number_format($t['payment_processing_fee'], 2, ',', ' ') }} ₽</td></tr>
        <tr><td class="label">— {{ $aggregate['split_labels']['vat'] }}</td><td class="amount">{{ number_format($t['vat_amount'], 2, ',', ' ') }} ₽</td></tr>
        <tr><td class="label">— {{ $aggregate['split_labels']['platform_net'] }}</td><td class="amount">{{ number_format($t['platform_net'], 2, ',', ' ') }} ₽</td></tr>
        <tr class="grand"><td class="label">К выплате продавцу</td><td class="amount">{{ number_format($t['seller_payout'], 2, ',', ' ') }} ₽</td></tr>
    </table>

    <p class="note">
        Комиссия рассчитана по каждой позиции (ставка категории товара: % от суммы + фикс. за единицу).
        Эквайринг и НДС, если применяются, выделяются только из суммы комиссии, а не из всего оборота заказа.
        Документ носит справочный характер и не является фискальным чеком.
    </p>

    <div class="footer">Сформировано {{ now()->format('d.m.Y H:i') }} · Alvora</div>
</div>
</body>
</html>
