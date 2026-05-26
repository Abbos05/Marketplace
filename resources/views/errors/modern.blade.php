<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? ($code ?? 'Ошибка') }}</title>
    <style>
        :root {
            --bg-main: #070b14;
            --bg-card: rgba(15, 22, 38, 0.72);
            --stroke: rgba(120, 144, 196, 0.25);
            --text-main: #eef3ff;
            --text-sub: #9cacd8;
            --accent: #5f7bff;
            --accent-2: #1fe4ff;
            --danger: #ff5f89;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            font-family: Inter, "Segoe UI", Roboto, sans-serif;
            color: var(--text-main);
            background:
                radial-gradient(circle at 15% 20%, rgba(95, 123, 255, 0.22), transparent 38%),
                radial-gradient(circle at 85% 80%, rgba(31, 228, 255, 0.18), transparent 42%),
                linear-gradient(160deg, #060a13, #090f1b 48%, #050913);
        }

        .noise {
            position: fixed;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.07) 0.4px, transparent 0.4px);
            background-size: 3px 3px;
            opacity: 0.18;
            pointer-events: none;
        }

        .shell {
            min-height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr auto;
            position: relative;
            z-index: 1;
        }

        .topbar,
        .footerbar {
            padding: 18px clamp(18px, 5vw, 80px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(8px);
            background: rgba(8, 13, 25, 0.45);
        }

        .footerbar {
            border-bottom: 0;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            color: var(--text-sub);
            font-size: 13px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--text-main);
            text-decoration: none;
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        .brand-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 0 16px rgba(95, 123, 255, 0.85);
        }

        .center {
            width: min(1080px, 94vw);
            margin: auto;
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: clamp(18px, 4vw, 48px);
            align-items: center;
            padding: clamp(18px, 3vw, 42px) 0;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--stroke);
            border-radius: 24px;
            padding: clamp(20px, 4vw, 42px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(10px);
        }

        .code-pill {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            letter-spacing: 0.9px;
            text-transform: uppercase;
            color: #c8d5ff;
            background: rgba(95, 123, 255, 0.15);
            border: 1px solid rgba(130, 153, 255, 0.36);
        }

        .title {
            font-size: clamp(28px, 4vw, 42px);
            margin: 16px 0 10px;
            line-height: 1.16;
        }

        .message {
            margin: 0 0 28px;
            color: var(--text-sub);
            line-height: 1.6;
            max-width: 560px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn {
            text-decoration: none;
            border-radius: 12px;
            padding: 12px 18px;
            font-weight: 600;
            font-size: 14px;
            transition: 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--accent), #4966ff 48%, #6d6bff);
            box-shadow: 0 12px 28px rgba(70, 98, 255, 0.35);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 30px rgba(70, 98, 255, 0.42);
        }

        .btn-ghost {
            color: #d3deff;
            border-color: rgba(148, 170, 230, 0.35);
            background: rgba(19, 29, 50, 0.42);
        }

        .btn-ghost:hover {
            background: rgba(38, 51, 83, 0.6);
            transform: translateY(-2px);
        }

        .visual {
            position: relative;
            perspective: 1200px;
            min-height: 290px;
        }

        .digit-stack {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            transform-style: preserve-3d;
            animation: float 5.5s ease-in-out infinite;
        }

        .digit,
        .digit-shadow,
        .digit-glow {
            position: absolute;
            font-size: clamp(72px, 19vw, 200px);
            font-weight: 800;
            letter-spacing: 0.07em;
            line-height: 0.9;
            user-select: none;
        }

        .digit {
            color: rgba(235, 241, 255, 0.95);
            transform: translateZ(35px);
            text-shadow: 0 14px 28px rgba(0, 0, 0, 0.5);
        }

        .digit-shadow {
            color: rgba(19, 29, 49, 0.95);
            transform: translateZ(-36px) translate(12px, 14px);
            filter: blur(0.4px);
        }

        .digit-glow {
            color: transparent;
            -webkit-text-stroke: 2px rgba(109, 136, 255, 0.65);
            transform: translateZ(0);
            filter: drop-shadow(0 0 20px rgba(95, 123, 255, 0.55));
        }

        .orb {
            position: absolute;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            filter: blur(1px);
        }

        .orb.one {
            top: 10%;
            right: 10%;
            background: radial-gradient(circle, rgba(31, 228, 255, 0.35), transparent 70%);
            animation: drift 6s ease-in-out infinite;
        }

        .orb.two {
            bottom: 12%;
            left: 8%;
            background: radial-gradient(circle, rgba(255, 95, 137, 0.3), transparent 72%);
            animation: drift 7.2s ease-in-out infinite reverse;
        }

        .notice {
            margin-top: 22px;
            font-size: 13px;
            color: #a9badf;
        }

        .notice span {
            color: #ffe7ef;
        }

        @keyframes float {
            0%, 100% { transform: rotateX(4deg) rotateY(-9deg) translateY(0); }
            50% { transform: rotateX(-4deg) rotateY(10deg) translateY(-12px); }
        }

        @keyframes drift {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-16px) scale(1.08); }
        }

        @media (max-width: 900px) {
            .center {
                grid-template-columns: 1fr;
            }

            .visual {
                min-height: 220px;
            }

            .digit,
            .digit-shadow,
            .digit-glow {
                font-size: clamp(64px, 24vw, 150px);
            }
        }
    </style>
</head>
<body>
    <div class="noise"></div>
    <div class="shell">
        <header class="topbar">
            <a class="brand" href="{{ route('home') }}">
                <span class="brand-dot"></span>
                <span>{{ config('app.name', 'Marketplace') }}</span>
            </a>
        </header>

        <main class="center">
            <section class="card">
                <span class="code-pill">Ошибка {{ $code ?? '000' }}</span>
                <h1 class="title">{{ $title ?? 'Что-то пошло не так' }}</h1>
                <p class="message">{{ $message ?? 'Возникла неожиданная проблема. Попробуйте обновить страницу или вернуться на главную.' }}</p>

                <div class="actions">
                    <a class="btn btn-primary" href="{{ route('home') }}">На главную</a>
                    <a class="btn btn-ghost" href="{{ url()->previous() }}">Вернуться назад</a>
                </div>

                <p class="notice">Код ошибки: <span>{{ $code ?? '000' }}</span></p>
            </section>

            <section class="visual" aria-hidden="true">
                <div class="orb one"></div>
                <div class="orb two"></div>
                <div class="digit-stack">
                    <div class="digit-shadow">{{ $code ?? 'ERR' }}</div>
                    <div class="digit-glow">{{ $code ?? 'ERR' }}</div>
                    <div class="digit">{{ $code ?? 'ERR' }}</div>
                </div>
            </section>
        </main>

        <footer class="footerbar">
            <span>Системная страница проекта</span>
            <span>{{ date('Y') }} &middot; Все права защищены</span>
        </footer>
    </div>
</body>
</html>
