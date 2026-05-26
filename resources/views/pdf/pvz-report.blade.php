<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Отчёт ПВЗ {{ $period }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 8px; }
        .meta { margin-bottom: 16px; }
        .meta p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .summary { margin: 16px 0; }
        .summary strong { display: inline-block; min-width: 200px; }
    </style>
</head>
<body>
    <h1>Отчёт оператора пункта выдачи</h1>
    <div class="meta">
        <p><strong>Период:</strong> {{ $period }}</p>
        <p><strong>ПВЗ:</strong> {{ $pickup_point?->title ?? '—' }}</p>
        <p><strong>Адрес:</strong> {{ $pickup_point?->address ?? '—' }}</p>
        <p><strong>Оператор:</strong> {{ trim(($operator->name ?? '').' '.($operator->last_name ?? '')) }} (ID {{ $operator->id }})</p>
        <p><strong>Вознаграждение:</strong> {{ $fee_description ?? '' }}</p>
    </div>
    <div class="summary">
        <p><strong>Выдано заказов:</strong> {{ $stats['issued_count'] }}</p>
        <p><strong>Отказов от получения:</strong> {{ $refused_count }}</p>
        <p><strong>Сумма к выплате:</strong> {{ number_format($stats['earnings'], 2, ',', ' ') }} ₽</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Заказ</th>
                <th>Дата</th>
                <th>Сумма заказа</th>
                <th>Начислено</th>
            </tr>
        </thead>
        <tbody>
            @forelse($accruals as $row)
                <tr>
                    <td>{{ $row->order?->number ?? $row->order_id }}</td>
                    <td>{{ $row->created_at?->format('d.m.Y H:i') }}</td>
                    <td>{{ number_format((float) ($row->order_total ?? $row->order?->total ?? 0), 2, ',', ' ') }} ₽</td>
                    <td>{{ number_format((float) $row->amount, 2, ',', ' ') }} ₽</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Нет выданных заказов за период</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
