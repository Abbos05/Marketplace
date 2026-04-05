<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Чек транзакции</title>
    <style>
        @font-face {
            font-family: 'DejaVu Sans';
            src: url('https://dejavu-fonts.github.io/download/dejavu-sans-regular.ttf') format('truetype');
            unicode-range: U+0000-00FF, U+0100-017F, U+0400-04FF; /* Кириллица */
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            background-color: #f9f9f9; /* Светлый фон страницы */
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .receipt {
            max-width: 400px; /* Узкий чек */
            margin: 0 auto;
            background-color: white;
            border: 1px solid #ffd438;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            font-size: 14px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #ffd438;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #000;
            font-size: 20px;
            margin: 0;
            font-weight: bold;
        }
        .header .subtitle {
            color: #666;
            font-size: 12px;
            margin: 5px 0 0 0;
        }
        .item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #eee;
        }
        .item-label {
            font-weight: bold;
            color: #555;
            flex: 1;
        }
        .item-value {
            color: #000;
            font-weight: bold;
            text-align: right;
            flex: 1;
        }
        .summary {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            color: #888;
            font-size: 10px;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        .confirmation {
            text-align: center;
            color: #ffd438;
            font-weight: bold;
            margin: 15px 0;
            font-size: 16px;
        }
        .bred{
            border: 2px solid red;
        }
        .bgreen{
            border: 2px solid #ffd438;
        }
    </style>
</head>
<body>
    <div class="receipt {{ $type == 'Отказано' ? 'bred' : 'bgreen' }}">
        <div class="header">
            <h1>ЧЕК ТРАНЗАКЦИИ</h1>
            <div class="subtitle">AltChain — Официальная торговая площадка NFT</div>
        </div>

        <div class="item"><span class="item-label">Тип:</span> <span class="item-value">{{ $type }}</span></div>
        <div class="item"><span class="item-label">NFT:</span> <span class="item-value">{{ $nft_title }}</span></div>
        <div class="item"><span class="item-label">ID NFT:</span> <span class="item-value">#{{ $nft_id }}</span></div>
        <div class="item"><span class="item-label">Покупатель:</span> <span class="item-value">{{ $buyer }}</span></div>
        <div class="item"><span class="item-label">Продавец:</span> <span class="item-value">{{ $seller }}</span></div>
        <div class="item"><span class="item-label">Сумма:</span> <span class="item-value">{{ $price }} ₽</span></div>
        <div class="item"><span class="item-label">Дата:</span> <span class="item-value">{{ $date }}</span></div>
        <div class="item"><span class="item-label">Транзакция:</span> <span class="item-value">№ 00{{ $tx_id }}</span></div>

        <div class="confirmation">Подтверждено</div>

        <div class="footer">
            © 2025 AltChain. Все права защищены.<br>
            Сгенерировано: {{ now()->format('d.m.Y H:i') }} (UTC+3)
        </div>
    </div>
</body>
</html>