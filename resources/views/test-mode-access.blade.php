<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alvora — Тестовый доступ</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.json?v=4">
    <meta name="theme-color" content="#FF2E63">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="/icons/icon-512.png?v=4">
    <link rel="icon" type="image/png" sizes="512x512" href="/icons/icon-512.png?v=4">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #fff;
            color: #1e293b;
            line-height: 1.4;
        }
        .page {
            width: min(100%, 860px);
            margin: 0 auto;
            padding: 44px 16px 60px;
        }
        .brand {
            text-align: center;
            margin-bottom: 18px;
            font-size: 34px;
            font-weight: 800;
            color: #FF2E63;
            letter-spacing: 0.02em;
        }
        .card {
            margin-top: 30px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            padding: 28px 24px;
        }
        .card + .card { margin-top: 20px; }
        .access-card {
            max-width: 520px;
            margin: 0 auto;
            text-align: center;
        }
        .title {
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 10px;
            color: #0f172a;
        }
        .sub {
            font-size: 15px;
            color: #64748b;
            margin-bottom: 22px;
        }
        .test-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
            text-align: left;
        }
        .test-form label {
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .password-field {
            width: 100%;
            height: 46px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 0 14px;
            font-size: 15px;
            color: #0f172a;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .password-field:focus {
            border-color: #FF2E63;
            box-shadow: 0 0 0 3px rgba(255, 46, 99, 0.1);
        }
        .btn {
            width: 100%;
            height: 46px;
            border: 0;
            border-radius: 12px;
            background: #FF2E63;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: background .2s ease;
        }
        .btn:hover { background: #d30035; }
        .error-message {
            background: #fff1f2;
            border: 1px solid rgba(244, 63, 94, 0.35);
            border-radius: 12px;
            padding: 10px 12px;
            color: #be123c;
            font-size: 14px;
            margin-bottom: 2px;
        }
        .hint {
            margin-top: 8px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
        }
        .hint a {
            color: #FF2E63;
            text-decoration: none;
            font-weight: 600;
        }
        .hint a:hover { text-decoration: underline; }
        .section-title {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .section-sub {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 14px;
        }
        .download-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .download-btn {
            min-height: 42px;
            padding: 0 16px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #1e293b;
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: border-color .2s, color .2s, background .2s;
        }
        .download-btn:hover {
            border-color: #FF2E63;
            color: #FF2E63;
            background: rgba(255, 46, 99, 0.05);
        }
        .download-btn--primary {
            background: #FF2E63;
            border-color: #FF2E63;
            color: #fff;
            margin: 0 auto;
        }
        .download-btn--primary:hover {
            background: #d30035;
            border-color: #d30035;
            color: #fff;
        }
        .download-hint {
            margin-top: 12px;
            color: #64748b;
            font-size: 13px;
        }
        .contact-list {
            display: grid;
            gap: 10px;
            color: #334155;
            font-size: 14px;
        }
        .contact-list a {
            color: #FF2E63;
            text-decoration: none;
            font-weight: 600;
        }
        .contact-list a:hover { text-decoration: underline; }
        .toast {
            position: fixed;
            left: 50%;
            bottom: 18px;
            transform: translateX(-50%);
            background: #FF2E63;
            color: #fff;
            border-radius: 999px;
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 600;
            opacity: 0;
            transition: opacity .2s;
            pointer-events: none;
            z-index: 20;
        }
        @media (max-width: 560px) {
            .page { padding-top: 28px; }
            .brand { font-size: 28px; }
            .card { padding: 20px 16px; border-radius: 16px; }
            .title { font-size: 22px; }
            .download-actions { flex-direction: column; }
            .download-btn { width: 100%; }
        }
    </style>
</head>
<body>
<main class="page">
    <div class="brand">ALVORA</div>

    <section class="card access-card">
        <h1 class="title">Вход в тестовый режим</h1>
        <p class="sub">Введите тестовый пароль, чтобы открыть доступ.</p>

        <form id="testAccessForm" class="test-form" method="POST" action="{{ route('test-mode.access.submit') }}">
            @csrf
            <label for="testPassword">Тестовый пароль</label>
            <input
                type="password"
                id="testPassword"
                name="password"
                class="password-field"
                placeholder="Введите пароль"
                autocomplete="off"
                required
            >

            @error('password')
                <div id="clientError" class="error-message">{{ $message }}</div>
            @else
                <div id="clientError" class="error-message" style="display:none;"></div>
            @enderror

            <button type="submit" class="btn">Войти</button>
        </form>

        @if(!empty($telegramUrl))
            <p class="hint">
                Нет пароля?
                <a href="{{ $telegramUrl }}" target="_blank" rel="noopener noreferrer">Написать в Telegram</a>
                @if(!empty($telegramLabel))
                    ({{ $telegramLabel }})
                @endif
            </p>
        @endif

    <section class="card">
        <h2 class="section-title">Скачать приложение</h2>
        <p class="section-sub">Блок скачивания вынесен отдельно. Откройте сайт на телефоне и установите приложение без всплывающей модалки.</p>
        <div class="download-actions">
            <button type="button" id="pwaInstallBtnInline" class="download-btn download-btn--primary">Установить приложение</button>
        </div>
        <p id="pwaInstallHintInline" class="download-hint">Кнопка станет активной, когда браузер разрешит установку (обычно Chrome на Android).</p>
    </section>

    <section class="card">
        <h2 class="section-title">Контакты</h2>
        <div class="contact-list">
            <div><strong>Поддержка:</strong> <a href="mailto:SupportAlvoraPlace@gmail.com">SupportAlvoraPlace@gmail.com</a></div>
            <div><strong>Telegram поддержки:</strong> <a href="https://t.me/AlvoraPlace" target="_blank" rel="noopener noreferrer">@AlvoraPlace</a></div>
            <div><strong>Основатель:</strong> Дадоматов Аббос Нурмахмадович</div>
            <div><strong>Telegram основателя:</strong> <a href="https://t.me/id_a_005_a" target="_blank" rel="noopener noreferrer">t.me/id_a_005_a</a></div>
            <div><strong>VK:</strong> <a href="https://vk.com/id_a_i_09_05_i_a" target="_blank" rel="noopener noreferrer">vk.com/id_a_i_09_05_i_a</a></div>
        </div>
    </section>
    </section>

</main>

<div id="toastMessage" class="toast">Приложение установлено</div>

<script>
    const passwordInput = document.getElementById('testPassword');
    const clientErrorDiv = document.getElementById('clientError');
    const installBtn = document.getElementById('pwaInstallBtnInline');
    const installHint = document.getElementById('pwaInstallHintInline');
    const toast = document.getElementById('toastMessage');
    let deferredInstallPrompt = null;
    let toastTimeout = null;

    passwordInput?.addEventListener('input', () => {
        if (clientErrorDiv) {
            clientErrorDiv.style.display = 'none';
        }
    });

    function showToast(message) {
        if (!toast) return;
        if (toastTimeout) clearTimeout(toastTimeout);
        toast.textContent = message;
        toast.style.opacity = '1';
        toastTimeout = setTimeout(() => {
            toast.style.opacity = '0';
        }, 2800);
    }

    function setInstallState(ready) {
        if (!installBtn) return;
        installBtn.disabled = !ready;
        installBtn.textContent = ready ? 'Установить приложение' : 'Установка недоступна';
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js?v=3', { scope: '/' }).catch(() => {});
    }

    setInstallState(false);

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        setInstallState(true);
        if (installHint) {
            installHint.textContent = 'Готово: нажмите "Установить приложение".';
        }
    });

    installBtn?.addEventListener('click', async () => {
        if (!deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        const { outcome } = await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
        setInstallState(false);
        if (installHint) {
            installHint.textContent = 'Если установка не открылась, попробуйте меню браузера -> "Добавить на главный экран".';
        }
        if (outcome === 'accepted') {
            showToast('Приложение установлено');
        }
    });

    window.addEventListener('appinstalled', () => {
        showToast('Приложение установлено');
        deferredInstallPrompt = null;
        setInstallState(false);
    });
</script>
</body>
</html>