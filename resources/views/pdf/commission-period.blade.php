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
        .totals { width: 100%; margin-top: 12px; }
        .totals td { padding: 6px 0; border: none; }
        .totals .label { color: #475569; width: 72%; }
        .totals .amount { text-align: right; font-weight: bold; width: 28%; }
        .totals .section { font-weight: bold; padding-top: 10px; color: #0f172a; }
        .totals .grand { border-top: 2px solid #0f172a; font-size: 12px; margin-top: 8px; }
        .note { margin-top: 14px; font-size: 9px; color: #64748b; line-height: 1.45; }
        .footer { margin-top: 18px; font-size: 8px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
@php $t = $aggregate['totals']; @endphp
<div class="doc">
    <div class="header">
        <h1>Маркетплейс Alvora</h1>
        <h2>{{ $title }}</h2>
        <p>Период: {{ $period_label }} · Продавец: {{ $seller->name }} {{ $seller->last_name }}</p>
        <p>{{ now()->format('d.m.Y H:i') }} · позиций: {{ $t['items_count'] }}</p>
    </div>

    <table class="totals">
        <tr class="section"><td colspan="2">Оборот и выплаты</td></tr>
        <tr><td class="label">Валовые продажи (сумма покупателей)</td><td class="amount">{{ number_format($t['gross'], 2, ',', ' ') }} ₽</td></tr>
        <tr><td class="label">Комиссия платформы (всего)</td><td class="amount">{{ number_format($t['commission_total'], 2, ',', ' ') }} ₽</td></tr>
        <tr><td class="label">К выплате продавцу (чистый доход)</td><td class="amount">{{ number_format($t['seller_payout'], 2, ',', ' ') }} ₽</td></tr>

        <tr class="section"><td colspan="2">Структура комиссии</td></tr>
        <tr><td class="label">{{ $aggregate['split_labels']['payment_fee'] }}</td><td class="amount">{{ number_format($t['payment_processing_fee'], 2, ',', ' ') }} ₽</td></tr>
        <tr><td class="label">{{ $aggregate['split_labels']['vat'] }}</td><td class="amount">{{ number_format($t['vat_amount'], 2, ',', ' ') }} ₽</td></tr>
        <tr><td class="label">{{ $aggregate['split_labels']['platform_net'] }}</td><td class="amount">{{ number_format($t['platform_net'], 2, ',', ' ') }} ₽</td></tr>

        <tr class="section"><td colspan="2">Состав комиссии (расчёт)</td></tr>
        <tr><td class="label">Процентная часть</td><td class="amount">{{ number_format($t['commission_percent_amount'], 2, ',', ' ') }} ₽</td></tr>
        <tr><td class="label">Фиксированная часть</td><td class="amount">{{ number_format($t['commission_fixed_amount'], 2, ',', ' ') }} ₽</td></tr>

        <tr class="grand"><td class="label">Проверка: комиссия = эквайринг + НДС + доля платформы</td>
            <td class="amount">{{ number_format($t['payment_processing_fee'] + $t['vat_amount'] + $t['platform_net'], 2, ',', ' ') }} ₽</td></tr>
    </table>

    <p class="note">
        Комиссия считается отдельно по каждой позиции заказа: ставка категории товара (% от суммы + фикс. за единицу).
        @if((float) config('marketplace.commission_split.payment_fee_percent_of_commission') > 0)
            Из суммы комиссии выделяется эквайринг ({{ config('marketplace.commission_split.payment_fee_percent_of_commission') }}% от комиссии),
        @endif
        @if((float) config('marketplace.commission_split.vat_percent_of_commission') > 0)
            НДС ({{ config('marketplace.commission_split.vat_percent_of_commission') }}% от комиссии),
        @endif
        остаток — чистая доля маркетплейса. Отменённые и отказные заказы в период не учитываются.
    </p>

    <div class="footer">Сформировано {{ now()->format('d.m.Y H:i') }} · Alvora</div>
</div>
</body>
</html>
