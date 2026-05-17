<!DOCTYPE html>
<html lang="ru">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="UTF-8">
    <title>Чек заказа #{{ $order->number }}</title>
    <style>
        /* DomPDF: Helvetica/без кириллицы подставляется при font-weight:600 и monospace — только DejaVu */
        @page { margin: 12mm 10mm; }
        * {
            font-family: 'DejaVu Sans', sans-serif !important;
        }
        body {
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        .receipt {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 18px;
        }

        .header {
            text-align: center;
            border-bottom: 2px dashed #cbd5e1;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header .logo {
            font-size: 18px;
            font-weight: bold; /* DejaVu Sans Bold — кириллица есть */
            color: #FF2E63;
            letter-spacing: 0.5px;
        }
        .header .subtitle {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }
        .header .order-num {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
        }
        .header .order-code {
            display: inline-block;
            margin-top: 4px;
            padding: 3px 10px;
            background: #eef2ff;
            color: #FF2E63;
            border-radius: 4px;
            font-family: 'DejaVu Sans Mono', monospace !important;
            font-size: 11px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        .info-label { color: #64748b; }
        .info-value { font-weight: bold; }

        .section-title {
            font-size: 12px;
            font-weight: bold; /* не 600 — иначе подстановка Helvetica */
            margin: 14px 0 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .parties-grid {
            width: 100%;
            margin-bottom: 12px;
        }
        .parties-grid td {
            vertical-align: top;
            width: 50%;
            padding: 6px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 4px;
        }
        .party-title {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .party-name {
            font-weight: bold;
            margin-bottom: 2px;
        }
        .party-meta { color: #475569; font-size: 10px; }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .items th {
            background: #f1f5f9;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
            padding: 6px;
            border-bottom: 1px solid #cbd5e1;
        }
        .items td {
            padding: 6px;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 11px;
        }
        .items td.num { text-align: right; }

        .seller-block {
            margin-bottom: 10px;
        }
        .seller-block-title {
            background: #eef2ff;
            color: #FF2E63;
            padding: 4px 8px;
            font-weight: bold;
            border-radius: 4px;
        }

        .totals {
            margin-top: 14px;
            border-top: 2px dashed #cbd5e1;
            padding-top: 10px;
        }
        .totals .total-row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
        }
        .totals .grand-total {
            font-size: 11px;
            font-weight: bold;
            padding-top: 6px;
            border-top: 1px solid #cbd5e1;
            margin-top: 6px;
        }

        .status-block {
            margin-top: 12px;
            padding: 8px;
            background: #f1f5f9;
            border-radius: 4px;
            text-align: center;
        }
        .status-pill {
            color: #FF2E63;
            font-size: 11px;
        }

        .footer {
            margin-top: 16px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px dashed #cbd5e1;
            padding-top: 10px;
        }
    </style>
</head>
<body>
<div class="receipt">
    {{-- Header --}}
    <div class="header">
        <div class="logo">Маркетплейс Alvora</div>
        <div class="subtitle">Чек электронного заказа</div>
        <div class="order-num">Заказ № {{ $order->number }}</div>
        @if($order->order_code)
            <div class="order-code">Код выдачи: {{ $order->order_code }}</div>
        @endif
    </div>

    {{-- Order info --}}
    <div class="info-row">
        <span class="info-label">Дата заказа:</span>
        <span class="info-value">{{ optional($order->created_at)->format('d.m.Y H:i') }}</span>
    </div>
    @php
        $pdfDeliveryMethodLabels = [
            'pvz' => 'Пункт выдачи',
            'courier' => 'Курьер',
            'post' => 'Почта России',
        ];
        $pdfPaymentMethodLabels = [
            'card' => 'Банковская карта',
            'stripe' => 'Банковская карта (Stripe)',
            'wallet' => 'Кошелёк',
            'cod' => 'Наложенный платёж',
        ];
    @endphp
    @if($order->payment_method)
        <div class="info-row">
            <span class="info-label">Способ оплаты:</span>
            <span class="info-value">{{ $pdfPaymentMethodLabels[strtolower(trim((string) $order->payment_method))] ?? $order->payment_method }}</span>
        </div>
    @endif
    @if($order->delivery_method)
        <div class="info-row">
            <span class="info-label">Доставка:</span>
            <span class="info-value">{{ $pdfDeliveryMethodLabels[strtolower(trim((string) $order->delivery_method))] ?? $order->delivery_method }}</span>
        </div>
    @endif

    {{-- Buyer / Seller(s) --}}
    <div class="section-title">Стороны</div>
    <table class="parties-grid">
        <tr>
            <td>
                <div class="party-title">Покупатель</div>
                @if($order->buyer)
                    <div class="party-name">
                        {{ trim(($order->buyer->name ?? '') . ' ' . ($order->buyer->last_name ?? '')) ?: 'Без имени' }}
                    </div>
                    <div class="party-meta">ID: {{ $order->buyer->id }}</div>
                    @if($order->buyer->email) <div class="party-meta">{{ $order->buyer->email }}</div> @endif
                    @if($order->buyer->phone) <div class="party-meta">+{{ $order->buyer->phone }}</div> @endif
                @else
                    <div class="party-meta">Аккаунт удалён</div>
                @endif
            </td>
            <td>
                <div class="party-title">Доставка</div>
                @if($order->delivery_address)
                    <div class="party-meta">{{ $order->delivery_address }}</div>
                @else
                    <div class="party-meta">—</div>
                @endif
            </td>
        </tr>
    </table>

    @php
        $commissionService = app(\App\Services\CommissionService::class);
    @endphp

    {{-- Items grouped by seller --}}
    <div class="section-title">Состав заказа</div>
    @foreach($bySeller as $sellerId => $items)
        @php $seller = $items->first()->seller; @endphp
        <div class="seller-block">
            <div class="seller-block-title">
                Продавец: {{ $seller?->name ?? 'ID ' . $sellerId }}
                @if($seller?->email) — {{ $seller->email }} @endif
            </div>
            <table class="items">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th class="num">Кол-во</th>
                        <th class="num">Цена</th>
                        <th class="num">Сумма</th>
                        <th class="num">Комиссия</th>
                        <th class="num">К выплате</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        @php $itemSnap = $commissionService->resolveSnapshot($item, persist: true); @endphp
                        <tr>
                            <td>{{ $item->variant?->product?->title ?? '—' }}</td>
                            <td class="num">{{ $item->quantity }}</td>
                            <td class="num">{{ number_format($item->price_at_purchase, 2, ',', ' ') }} ₽</td>
                            <td class="num">{{ number_format($itemSnap['gross'], 2, ',', ' ') }} ₽</td>
                            <td class="num">{{ number_format($itemSnap['commission'], 2, ',', ' ') }} ₽</td>
                            <td class="num">{{ number_format($itemSnap['seller_payout'], 2, ',', ' ') }} ₽</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    {{-- Totals --}}
    <div class="totals">
        @php
            $subtotal = 0.0;
            $commissionTotal = 0.0;
            $payoutTotal = 0.0;
            foreach ($order->items as $lineItem) {
                $lineSnap = $commissionService->resolveSnapshot($lineItem, persist: true);
                $subtotal += $lineSnap['gross'];
                $commissionTotal += $lineSnap['commission'];
                $payoutTotal += $lineSnap['seller_payout'];
            }
        @endphp
        <div class="total-row">
            <span>Подытог:</span>
            <span>{{ number_format($subtotal, 2, ',', ' ') }} ₽</span>
        </div>
        @if($order->discount > 0)
            <div class="total-row">
                <span>Скидка:</span>
                <span>− {{ number_format($order->discount, 2, ',', ' ') }} ₽</span>
            </div>
        @endif
        <div class="total-row">
            <span>Комиссия платформы:</span>
            <span>{{ number_format($commissionTotal, 2, ',', ' ') }} ₽</span>
        </div>
        <div class="total-row">
            <span>К выплате продавцам:</span>
            <span>{{ number_format($payoutTotal, 2, ',', ' ') }} ₽</span>
        </div>
        <div class="total-row grand-total">
            <span>ИТОГО:</span>
            <span>{{ number_format($order->total, 2, ',', ' ') }} ₽</span>
        </div>
    </div>

    {{-- Status / оплата: всегда русские подписи (ключи из БД могут отличаться регистром или быть legacy) --}}
    @php
        $orderStatusLabels = [
            'NEW' => 'Новый заказ',
            'INTRANSIT' => 'В пути',
            'DELIVERED' => 'В пункте выдачи',
            'CANCELED' => 'Отменён',
            'REFUSED' => 'Отказ от получения',
            // старые значения (до миграции) — для корректной подписи в PDF
            'PAID' => 'Новый заказ',
            'PROCESSING' => 'Новый заказ',
            'READYPICKUP' => 'В пути',
            'ATPVZ' => 'В пути',
            'ISSUED' => 'Выдан покупателю',
            'RETURNED' => 'Отказ от получения',
            'new' => 'Новый заказ',
            'paid' => 'Новый заказ',
            'processing' => 'Новый заказ',
            'ready_for_pickup' => 'В пути',
            'in_transit' => 'В пути',
            'at_pvz' => 'В пути',
            'issued' => 'Доставлен',
            'canceled' => 'Отменён',
            'returned' => 'Отказ от получения',
        ];
        $paymentStatusLabels = [
            'pending' => 'Ожидает оплаты',
            'paid' => 'Оплачен',
            'failed' => 'Не оплачено',
            'refunded' => 'Возвращён',
            // значения Stripe Checkout, если когда-либо попали в поле
            'unpaid' => 'Не оплачен',
            'no_payment_required' => 'Оплата не требуется',
        ];

        $rawOrderStatus = trim((string) ($order->status ?? ''));
        $orderStatusRu = $orderStatusLabels[$rawOrderStatus]
            ?? $orderStatusLabels[strtolower($rawOrderStatus)]
            ?? $orderStatusLabels[strtoupper($rawOrderStatus)]
            ?? ($rawOrderStatus !== '' ? 'Неизвестный статус заказа' : '—');

        $rawPaymentStatus = strtolower(trim((string) ($order->payment_status ?? '')));
        $paymentStatusRu = $paymentStatusLabels[$rawPaymentStatus] ?? 'Неизвестный статус оплаты';
    @endphp
    <div class="status-block">
        Статус: <span class="status-pill">{{ $orderStatusRu }}</span>
        @if($order->payment_status)
            &nbsp;&nbsp; Оплата: <span class="status-pill">{{ $paymentStatusRu }}</span>
        @endif
    </div>

    {{-- Footer --}}
    <div class="footer">
        Чек сгенерирован {{ now()->format('d.m.Y H:i') }} · Документ не является фискальным
    </div>
</div>
</body>
</html>
