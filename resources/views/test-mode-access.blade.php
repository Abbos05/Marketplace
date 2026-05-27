<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Alvora — Тестовый доступ</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.json?v=3">
    <meta name="theme-color" content="#FF2E63">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="/icons/icon-192.png?v=3">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png?v=3">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Inter',sans-serif;background:#0a0a0a;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;position:relative;overflow-x:hidden;}
        body::before{content:'';position:fixed;top:0;left:0;right:0;bottom:0;background:radial-gradient(circle at 20% 50%,#d3003572,transparent 55%),radial-gradient(circle at 80% 80%,rgba(234, 0, 58, 0.15),transparent 60%),linear-gradient(135deg,#0a0a0a 0%,#14142b 100%);z-index:0;}
        .glow{position:fixed;width:500px;height:500px;background:radial-gradient(circle,rgba(255,46,99,0.3),transparent 70%);border-radius:50%;filter:blur(60px);pointer-events:none;z-index:0;}
        .glow-1{top:-200px;left:-200px;}.glow-2{bottom:-200px;right:-200px;}.glow-3{top:50%;left:50%;transform:translate(-50%,-50%);width:600px;height:600px;opacity:0.2;}
        .container{position:relative;z-index:2;max-width:1300px;width:100%;}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:40px;flex-wrap:wrap;gap:20px;}
        .logo{display:flex;align-items:center;gap:12px;}    
        .logo-icon{width:48px;height:48px;background:linear-gradient(135deg,#FF2E63,#FF6B8A);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:28px;color:#fff;box-shadow:0 0 30px rgba(255,46,99,0.5);}
        .logo-text{font-size:32px;font-weight:800;letter-spacing:-1px;background:linear-gradient(135deg,#fff,#FF2E63);-webkit-background-clip:text;background-clip:text;color:transparent;}
        .badge-header{background:rgba(255,46,99,0.2);border:1px solid rgba(255,46,99,0.4);padding:8px 20px;border-radius:40px;font-size:14px;font-weight:500;color:#FF2E63;}
        .main-grid{display:grid;grid-template-columns:1fr 1fr;gap:50px;margin-bottom:50px;}
        .left-content{background:rgba(255,255,255,0.03);backdrop-filter:blur(10px);border-radius:32px;padding:40px;border:1px solid rgba(255,46,99,0.2);}
        .tagline{color:#FF2E63;font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:3px;margin-bottom:20px;}
        h1{font-size:56px;font-weight:900;line-height:1.1;margin-bottom:20px;color:#fff;}
        .highlight{background:linear-gradient(135deg,#FF2E63,#FF8A9F);-webkit-background-clip:text;background-clip:text;color:transparent;}
        .desc{font-size:18px;line-height:1.5;color:rgba(255,255,255,0.7);margin-bottom:30px;}
        .timer-wrapper{background:rgba(255,255,255,0.05);border-radius:24px;padding:25px;margin-bottom:30px;border:1px solid rgba(255,46,99,0.2);}
        .timer-title{font-size:12px;text-transform:uppercase;letter-spacing:2px;color:#FF2E63;margin-bottom:20px;}
        .countdown{display:flex;gap:20px;}
        .time-block{text-align:center;flex:1;}
        .time-number{font-size:48px;font-weight:800;background:linear-gradient(135deg,#FF2E63,#FF6B8A);-webkit-background-clip:text;background-clip:text;color:transparent;line-height:1;}
        .time-label{font-size:12px;color:rgba(255,255,255,0.5);margin-top:8px;text-transform:uppercase;}
        .test-access-card{background:rgba(255,255,255,0.03);backdrop-filter:blur(12px);border-radius:32px;padding:40px;border:1px solid rgba(255,46,99,0.3);box-shadow:0 20px 40px rgba(0,0,0,0.4);}
        .test-badge{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#FF2E63,#FF6B8A);padding:6px 16px;border-radius:40px;font-size:13px;font-weight:700;color:#fff;margin-bottom:20px;}
        .test-access-card h2{font-size:32px;font-weight:800;color:#fff;margin-bottom:16px;}
        .test-access-card p{color:rgba(255,255,255,0.7);line-height:1.5;margin-bottom:28px;}
        .test-form{display:flex;flex-direction:column;gap:20px;}
        .input-group{display:flex;flex-direction:column;gap:8px;}
        .input-group label{font-size:14px;font-weight:600;color:#FF2E63;}
        .password-field{background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.25);border-radius:60px;padding:16px 24px;font-size:16px;color:#fff;font-family:'Inter',sans-serif;outline:none;transition:all 0.3s;}
        .password-field:focus{border-color:#FF2E63;box-shadow:0 0 20px rgba(255,46,99,0.4);background:rgba(255,255,255,0.12);}
        .error-message{background:rgba(220,38,38,0.2);border-left:3px solid #FF2E63;padding:12px 16px;border-radius:16px;font-size:13px;color:#ffb3b3;display:none;}
        .btn-test{background:linear-gradient(135deg,#FF2E63,#FF6B8A);border:none;padding:16px 24px;border-radius:60px;font-weight:700;font-size:16px;color:#fff;cursor:pointer;font-family:'Inter',sans-serif;transition:all 0.3s;display:flex;align-items:center;justify-content:center;gap:10px;}
        .btn-test:hover{transform:scale(1.02);box-shadow:0 10px 30px rgba(255,46,99,0.5);}
        .test-password-help{margin-top:8px;padding-top:22px;border-top:1px dashed rgba(255,46,99,0.35);text-align:center;}
        .test-password-help__title{font-size:15px;font-weight:700;color:#fff;margin-bottom:8px;}
        .test-password-help__text{font-size:13px;line-height:1.5;color:rgba(255,255,255,0.62);margin-bottom:16px;}
        .telegram-contact-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:13px 22px;border-radius:60px;text-decoration:none;font-size:14px;font-weight:700;color:#fff;background:linear-gradient(135deg,#229ED9,#1a8bc7);border:1px solid rgba(255,255,255,0.2);transition:transform .25s,box-shadow .25s,background .25s;}
        .telegram-contact-btn i{font-size:20px;}
        .telegram-contact-btn:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(34,158,217,0.45);background:linear-gradient(135deg,#2aabee,#229ED9);}
        .telegram-contact-btn__hint{display:block;margin-top:10px;font-size:12px;color:rgba(255,255,255,0.45);}
        .telegram-contact-btn__hint span{color:#7dd3fc;font-weight:600;}
        .feature-list{display:flex;flex-direction:column;gap:16px;margin-top:20px;}
        .feature-item{display:flex;gap:14px;align-items:center;padding:12px 16px;background:rgba(255,255,255,0.04);border-radius:20px;transition:all 0.3s;}
        .feature-item:hover{background:rgba(255,46,99,0.1);transform:translateX(5px);}
        .feature-icon{width:42px;height:42px;background:linear-gradient(135deg,#FF2E63,#FF6B8A);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;}
        .feature-text h4{color:#fff;font-size:16px;font-weight:700;margin-bottom:4px;}
        .feature-text p{color:rgba(255,255,255,0.6);font-size:12px;}
        .footer{background:rgba(255,255,255,0.03);backdrop-filter:blur(10px);border-radius:32px;padding:40px;border:1px solid rgba(255,46,99,0.2);margin-top:20px;}
        .footer-brand{text-align:center;margin-bottom:30px;}
        .footer-logo{font-size:28px;font-weight:800;background:linear-gradient(135deg,#fff,#FF2E63);-webkit-background-clip:text;background-clip:text;color:transparent;display:inline-block;margin-bottom:15px;}
        .footer-tagline{color:rgba(255,255,255,0.6);font-size:14px;max-width:500px;margin:0 auto;}
        .social-links{display:flex;justify-content:center;gap:15px;margin:25px 0;flex-wrap:wrap;}
        .social-link{background:rgba(255,255,255,0.08);padding:12px 24px;border-radius:40px;text-decoration:none;font-weight:600;color:#fff;transition:all 0.3s;display:inline-flex;align-items:center;gap:8px;font-size:14px;border:1px solid rgba(255,46,99,0.2);}
        .social-link:hover{background:#FF2E63;transform:translateY(-3px);}
        .contacts-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-top:30px;padding-top:30px;border-top:1px solid rgba(255,255,255,0.1);}
        .contact-card{background:rgba(255,255,255,0.05);border-radius:20px;padding:20px;text-align:center;}
        .contact-card h4{color:#FF2E63;font-size:16px;margin-bottom:12px;}
        .contact-card p{color:rgba(255,255,255,0.7);font-size:13px;margin:5px 0;}
        .contact-card a{color:#fff;text-decoration:none;}
        .founder-card{background:linear-gradient(135deg,rgba(255,46,99,0.15),rgba(255,46,99,0.05));border:1px solid rgba(255,46,99,0.3);}
        .seller-card{background:linear-gradient(135deg,#FF2E63,#FF6B8A);}
        .seller-card h4,.seller-card p,.seller-card a{color:#fff;}
        .toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#FF2E63;color:#fff;padding:12px 24px;border-radius:60px;font-weight:500;z-index:1000;opacity:0;transition:opacity 0.3s;pointer-events:none;font-size:14px;}
        body.pwa-modal-open{overflow:hidden;}
        .pwa-install-modal{position:fixed;inset:0;z-index:2000;display:flex;align-items:flex-start;justify-content:center;padding:24px 16px 16px;pointer-events:none;opacity:0;visibility:hidden;transition:opacity .3s,visibility .3s;}
        .pwa-install-modal.is-open{pointer-events:auto;opacity:1;visibility:visible;}
        .pwa-install-modal__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);}
        .pwa-install-modal__card{position:relative;z-index:1;width:min(100%,420px);background:linear-gradient(160deg,#1a1a2e 0%,#14142b 100%);border:1px solid rgba(255,46,99,.45);border-radius:24px;padding:28px 24px 22px;box-shadow:0 24px 60px rgba(0,0,0,.55),0 0 40px rgba(255,46,99,.15);transform:translateY(-16px);transition:transform .35s ease;}
        .pwa-install-modal.is-open .pwa-install-modal__card{transform:translateY(0);}
        .pwa-install-modal__icon{width:56px;height:56px;border-radius:16px;margin-bottom:16px;box-shadow:0 8px 24px rgba(255,46,99,.35);}
        .pwa-install-modal__title{font-size:22px;font-weight:800;color:#fff;margin-bottom:8px;line-height:1.2;}
        .pwa-install-modal__text{font-size:14px;line-height:1.5;color:rgba(255,255,255,.72);margin-bottom:12px;}
        .pwa-install-modal__hint{font-size:13px;line-height:1.45;color:rgba(255,255,255,.5);margin-bottom:18px;display:none;}
        .pwa-install-modal__actions{display:flex;flex-direction:column;gap:10px;}
        #pwaInstallBtn{display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:14px 20px;border:none;border-radius:14px;background:linear-gradient(135deg,#ff2e63,#d30035 55%,#ff5c8a);color:#fff;font-family:'Inter',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:transform .2s,opacity .2s,box-shadow .2s;box-shadow:0 8px 24px rgba(255,46,99,.35);}
        #pwaInstallBtn:disabled{opacity:.55;cursor:wait;transform:none;box-shadow:none;}
        #pwaInstallBtn.is-ready:not(:disabled):hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(255,46,99,.45);}
        #pwaInstallSkip{width:100%;padding:12px;border:none;border-radius:14px;background:transparent;color:rgba(255,255,255,.55);font-family:'Inter',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:color .2s,background .2s;}
        #pwaInstallSkip:hover{color:#fff;background:rgba(255,255,255,.06);}
        @media (max-width:900px){.main-grid{grid-template-columns:1fr;gap:30px;}h1{font-size:42px;}.time-number{font-size:36px;}}
        @media (max-width:550px){h1{font-size:32px;}.time-number{font-size:28px;}.btn-test{padding:14px;}}
    </style>
</head>
<body data-pwa-test-modal="1">
<div id="pwaInstallModal" class="pwa-install-modal" aria-hidden="true" role="dialog" aria-labelledby="pwaInstallTitle" aria-modal="true">
    <div class="pwa-install-modal__backdrop" aria-hidden="true"></div>
    <div class="pwa-install-modal__card">
        <img class="pwa-install-modal__icon" src="/icons/icon-192.png?v=3" width="56" height="56" alt="Alvora">
        <h2 id="pwaInstallTitle" class="pwa-install-modal__title">Установить Alvora</h2>
        <p class="pwa-install-modal__text">Добавьте приложение на главный экран — быстрый вход в тестовый режим без поиска в браузере.</p>
        <p id="pwaInstallHint" class="pwa-install-modal__hint" style="display:none;"></p>
        <div class="pwa-install-modal__actions">
            <button type="button" id="pwaInstallBtn" disabled>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 3v12m0 0l4-4m-4 4L8 11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span class="pwa-install-btn__label">Подготовка…</span>
            </button>
            <button type="button" id="pwaInstallSkip">Пропустить</button>
        </div>
    </div>
</div>

<div class="glow glow-1"></div>
<div class="glow glow-2"></div>
<div class="glow glow-3"></div>

<div class="container">
    <div class="header">
        <div class="logo">
            <span class="logo-text">Alvora</span>
        </div>
        <div class="badge-header"><i class="fas fa-flask"></i> Тестовый режим · Премиум маркетплейс</div>
    </div>

    <div class="main-grid">
        <div class="left-content">
            <div class="tagline"><i class="fas fa-hourglass-half"></i> СКОРО ПОЛНЫЙ ЗАПУСК</div>
            <h1>Инновационная платформа<br><span class="highlight">Alvora Marketplace</span></h1>
            <p class="desc">Эксклюзивные товары, цифровые решения и безопасные сделки. Уже скоро — экосистема нового поколения.</p>
            <div class="timer-wrapper">
                <div class="timer-title">ДО ОТКРЫТИЯ ОСТАЛОСЬ</div>
                <div class="countdown">
                    <div class="time-block"><div class="time-number" id="hours">00</div><div class="time-label">Часов</div></div>
                    <div class="time-block"><div class="time-number" id="minutes">00</div><div class="time-label">Минут</div></div>
                    <div class="time-block"><div class="time-number" id="seconds">00</div><div class="time-label">Секунд</div></div>
                </div>
            </div>
            <div class="feature-list">
                <div class="feature-item"><div class="feature-icon"><i class="fas fa-bolt"></i></div><div class="feature-text"><h4>Мгновенные заказы</h4><p>Умный поиск и оплата в один клик</p></div></div>
                <div class="feature-item"><div class="feature-icon"><i class="fas fa-gem"></i></div><div class="feature-text"><h4>Эксклюзивные коллекции</h4><p>Редкие бренды и цифровые товары</p></div></div>
                <div class="feature-item"><div class="feature-icon"><i class="fas fa-shield-alt"></i></div><div class="feature-text"><h4>Полная защита</h4><p>Гарантия возврата и поддержка 24/7</p></div></div>
            </div>
        </div>

        <div class="test-access-card">
            <div class="test-badge"><i class="fas fa-key"></i> ЗАКРЫТЫЙ ТЕСТ-ДРАЙВ</div>
            <h2>Ранний доступ</h2>
            <p>Получите возможность первыми оценить функционал Alvora. Введите тестовый пароль, чтобы войти в демо-режим платформы.</p>
            <form id="testAccessForm" class="test-form" method="POST" action="{{ route('test-mode.access.submit') }}">
                @csrf
                <div class="input-group">
                    <label><i class="fas fa-lock"></i> Тестовый пароль</label>
                    <input type="password" id="testPassword" name="password" class="password-field" placeholder="Введите пароль" autocomplete="off" required>
                </div>
                @error('password')
                    <div class="error-message" style="display:block;">{{ $message }}</div>
                @enderror
                <div id="clientError" class="error-message" style="display:none;">❌ Неверный пароль. Доступ запрещён.</div>
                <button type="submit" class="btn-test"><i class="fas fa-unlock-alt"></i> Открыть доступ</button>

                @if(!empty($telegramUrl))
                <div class="test-password-help">
                    <p class="test-password-help__title">Нет тестового пароля?</p>
                    <p class="test-password-help__text">Чтобы получить доступ к демо-режиму, напишите нам в Telegram — ответим и выдадим пароль.</p>
                    <a href="{{ $telegramUrl }}" class="telegram-contact-btn" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-telegram-plane"></i>
                        Написать в Telegram
                    </a>
                    @if(!empty($telegramLabel))
                        <span class="telegram-contact-btn__hint">Аккаунт: <span>{{ $telegramLabel }}</span></span>
                    @endif
                </div>
                @endif
            </form>
        </div>
    </div>

    <div class="footer">
        <div class="footer-brand">
            <div class="footer-logo">ALVORA</div>
            <p class="footer-tagline">Маркетплейс для покупателей и продавцов: каталог, заказы, доставка в пункты выдачи.</p>
        </div>
        <div class="social-links">
            <a href="https://vk.com/id_a_i_09_05_i_a" class="social-link" target="_blank"><i class="fab fa-vk"></i> VK</a>
            <a href="https://t.me/AlvoraPlace" class="social-link" target="_blank"><i class="fab fa-telegram"></i> Telegram-канал</a>
            <a href="https://max.ru/join/uTTd84ZCWV6LDqeiR1KOFZnBPp-2ar4mgwWMtSsmfmQ" class="social-link" target="_blank"><i class="fas fa-rocket"></i> MAX</a>
            <a href="https://www.instagram.com/id_a_l_00_05_l_a/" class="social-link" target="_blank"><i class="fab fa-instagram"></i> Instagram</a>
            <a href="https://t.me/id_a_005_a" class="social-link" target="_blank"><i class="fab fa-telegram"></i> Telegram основателя</a>
        </div>
        <div class="contacts-grid">
            <div class="contact-card">
                <h4><i class="fas fa-headset"></i> Служба поддержки</h4>
                <p><i class="fas fa-envelope"></i> <a href="mailto:SupportAlvoraPlace@gmail.com">SupportAlvoraPlace@gmail.com</a></p>
                <p><i class="fab fa-telegram"></i> <a href="https://t.me/AlvoraPlace" target="_blank">@AlvoraPlace</a></p>
            </div>
            <div class="contact-card founder-card">
                <h4><i class="fas fa-user-tie"></i> Основатель платформы</h4>
                <p><strong>Дадоматов Аббос Нурмахмадович</strong></p>
                <p><i class="fas fa-envelope"></i> Abbos••••@gmail.com</p>
                <p><i class="fab fa-telegram"></i> <a href="https://t.me/id_a_005_a" target="_blank">t.me/id_a_005_a</a></p>
                <p><i class="fab fa-vk"></i> <a href="https://vk.com/id_a_i_09_05_i_a" target="_blank">vk.com/id_a_i_09_05_i_a</a></p>
                <p style="font-size: 11px; opacity: 0.6;">Полные контакты — через поддержку</p>
            </div>
            <div class="contact-card seller-card">
                <h4><i class="fas fa-store"></i> Для продавцов</h4>
                <p>Станьте частью Alvora Marketplace</p>
                <p style="margin-top: 8px;"><i class="fas fa-envelope"></i> <a href="mailto:SupportAlvoraPlace@gmail.com" style="color: white;">SupportAlvoraPlace@gmail.com</a></p>
            </div>
        </div>
    </div>
</div>

<div id="toastMessage" class="toast">✨ Добро пожаловать в тестовый режим!</div>

<script>
    // Таймер
    const LAUNCH_KEY = 'alvora_launch_target';
    let targetDate;

    function initTimer() {
        const saved = localStorage.getItem(LAUNCH_KEY);
        if (saved) {
            targetDate = new Date(parseInt(saved));
            if (targetDate < new Date()) {
                createNewLaunchDate();
            }
        } else {
            createNewLaunchDate();
        }
        localStorage.setItem(LAUNCH_KEY, targetDate.getTime());
    }

    function createNewLaunchDate() {
        const now = new Date();
        targetDate = new Date(now);
        targetDate.setHours(now.getHours() + 22);
    }

    initTimer();

    function updateCountdown() {
        const now = new Date().getTime();
        const distance = targetDate.getTime() - now;
        if (distance < 0) {
            document.getElementById('hours').innerText = '00';
            document.getElementById('minutes').innerText = '00';
            document.getElementById('seconds').innerText = '00';
            return;
        }
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        document.getElementById('hours').innerText = hours < 10 ? '0' + hours : hours;
        document.getElementById('minutes').innerText = minutes < 10 ? '0' + minutes : minutes;
        document.getElementById('seconds').innerText = seconds < 10 ? '0' + seconds : seconds;
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);

    // Клиентская валидация пароля (дополнительно к серверной)
    const form = document.getElementById('testAccessForm');
    const passwordInput = document.getElementById('testPassword');
    const clientErrorDiv = document.getElementById('clientError');
    const toast = document.getElementById('toastMessage');
    let toastTimeout = null;

    function showToast(msg, isError = false) {
        if (toastTimeout) clearTimeout(toastTimeout);
        toast.textContent = msg;
        toast.style.background = isError ? '#dc2626' : '#FF2E63';
        toast.style.opacity = '1';
        toastTimeout = setTimeout(() => { toast.style.opacity = '0'; }, 3000);
    }

    // Если есть ошибки валидации от Laravel, показываем их
    @if($errors->has('password'))
        clientErrorDiv.style.display = 'block';
        clientErrorDiv.textContent = '{{ $errors->first('password') }}';
        showToast('{{ $errors->first('password') }}', true);
    @endif

    passwordInput.addEventListener('input', () => {
        clientErrorDiv.style.display = 'none';
    });
</script>
<script src="/js/pwa-standalone.js" defer></script>
</body>
</html>