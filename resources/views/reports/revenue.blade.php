<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Отчёт по выручке</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 8px; }
        .meta { color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .summary { margin-top: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Отчёт по выручке ALVORA</h1>
    <p class="meta">Период: {{ $from }} — {{ $to }}</p>
    <p class="summary">Заказов: {{ $ordersCount }} · Сумма: {{ number_format($total, 2, ',', ' ') }} ₽</p>

    <table>
        <thead>
            <tr>
                <th>Дата</th>
                <th>Выручка, ₽</th>
                <th>Заказов</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($chartData as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row['date'])->format('d.m.Y') }}</td>
                    <td>{{ number_format($row['revenue'], 2, ',', ' ') }}</td>
                    <td>{{ $row['count'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
