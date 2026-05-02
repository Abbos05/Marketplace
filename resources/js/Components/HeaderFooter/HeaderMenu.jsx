// resources/js/Components/HeaderMenu.jsx
import React, { useState, useEffect, useRef } from 'react';
import { Link, usePage } from '@inertiajs/react';

const HeaderMenu = ({ setIsModalOpen, setIsLogin }) => {
  const { auth: { user } } = usePage().props;
  const isAuthenticated = !!user;

  const [isOpen, setIsOpen] = useState(false);
  const [theme, setTheme] = useState('dark');
  const [showTermsModal, setShowTermsModal] = useState(false); // ← новое модальное окно

  const menuRef = useRef(null);

  // Тема
  useEffect(() => {
    const saved = localStorage.getItem('theme') || 'dark';
    setTheme(saved);
    document.documentElement.classList.toggle('light-theme', saved === 'light');
  }, []);

  const toggleTheme = () => {
    const newTheme = theme === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
    localStorage.setItem('theme', newTheme);
    document.documentElement.classList.toggle('light-theme', newTheme === 'light');
  };

  // Логаут
  const handleLogout = () => {
    setIsOpen(false);
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/logout';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = '_token';
      input.value = csrf;
      form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
  };

  // Закрытие по клику вне
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (menuRef.current && !menuRef.current.contains(e.target)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <>
      <li className="header-menu" ref={menuRef}>
        <div className="header-menu-icon" onClick={() => setIsOpen(!isOpen)}>
          <div className={`wallet-plus ${isOpen ? 'openMenu' : ''} wallet-btn`}>
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M7.5 14c1.5.005 1.5 1 4.5 1s3-1 4.5-1c1 0 3.5 2.5 3.5 3.5S17.483 21 11.991 21C6.5 21 4 18.5 4 17.5s2.5-3.503 3.5-3.5M12 3C9 3 7 5 7 8s2 5 5 5 5-2 5-5-2-5-5-5"></path></svg>
            <p>Профиль</p>
          </div>

        </div>

        {isOpen && (
          <ul className="header-menu-dropdown">
            {/* Профиль */}
            {isAuthenticated ? (
              <>
                <li className="header-menu-profile-item">
                  <Link href="/profile" className="header-menu-profile" onClick={() => setIsOpen(false)}>
                    <img
                      src={user.avatar ? `/${user.avatar}` : '/img/profiles/profile.png'}
                      alt={user.name}
                      className="header-menu-avatar"
                    />
                    <div className="header-menu-profile-info">
                      <span className="header-menu-username">{user.name}</span>
                      {user.phone && <img src="/img/profiles/check.png" alt="verified" className="verified-check" />}
                    </div>
                  </Link>
                </li>
                <li className="header-menu-divider"></li>
              </>
            ) : (
              <li className="header-menu-item auth-item">
                <Link
                  href="#"
                  onClick={(e) => {
                    e.preventDefault();
                    setIsModalOpen(true);
                    setIsLogin(true);
                    setIsOpen(false);
                  }}
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                    <polyline points="10 17 15 12 10 7" />
                    <line x1="15" y1="12" x2="3" y2="12" />
                  </svg>
                  Войти / Регистрация
                </Link>
              </li>
            )}

            {/* Основные ссылки */}
            <li className="header-menu-item">
              <Link href="/about" onClick={() => setIsOpen(false)}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <circle cx="12" cy="12" r="10" />
                  <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                  <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                О проекте
              </Link>
            </li>

            <li className="header-menu-item">
              <Link href="/contacts" onClick={() => setIsOpen(false)}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                  <polyline points="22,6 12,13 2,6" />
                </svg>
                Контакты
              </Link>
            </li>

            <li className="header-menu-item">
              <Link href="/support" onClick={() => setIsOpen(false)}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                  <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
                Поддержка
              </Link>
            </li>

            {/* Правила — теперь модальное окно */}
            <li className="header-menu-item" onClick={() => { setShowTermsModal(true); setIsOpen(false); }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="8" y1="6" x2="21" y2="6" />
                <line x1="8" y1="12" x2="21" y2="12" />
                <line x1="8" y1="18" x2="21" y2="18" />
                <circle cx="4" cy="6" r="2" />
                <circle cx="4" cy="12" r="2" />
                <circle cx="4" cy="18" r="2" />
              </svg>
              Правила
            </li>

            {/* Переключатель темы */}
            <li className="header-menu-item theme-toggle-item" onClick={toggleTheme}>
              <div className="theme-toggle">
                {theme === 'dark' ? (
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="12" cy="12" r="5" />
                    <line x1="12" y1="1" x2="12" y2="3" />
                    <line x1="12" y1="21" x2="12" y2="23" />
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                    <line x1="1" y1="12" x2="3" y2="12" />
                    <line x1="21" y1="12" x2="23" y2="12" />
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                  </svg>
                ) : (
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                  </svg>
                )}
                <span>{theme === 'dark' ? 'Светлая тема' : 'Тёмная тема'}</span>
              </div>
            </li>

            {/* Выход */}
            {isAuthenticated && (
              <>
                {/* <li className="header-menu-divider"></li> */}
                <li className="header-menu-item logout-item" onClick={handleLogout}>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                  </svg>
                  Выйти
                </li>
              </>
            )}
          </ul>
        )}
      </li>

      {/* КРАСИВОЕ МОДАЛЬНОЕ ОКНО С ПРАВИЛАМИ */}
      {showTermsModal && (
        <div className="alt-modal-overlay" onClick={() => setShowTermsModal(false)}>
          <div className="alt-modal-terms" onClick={(e) => e.stopPropagation()}>


            <h2 className="alt-modal-title">Правила платформы ALVORA</h2>

            <div className="alt-modal-content">
              <p><strong>1.</strong> Запрещается продажа контента, нарушающего авторские права.</p>
              <p><strong>2.</strong> Все NFT проходят модерацию перед публикацией.</p>
              <p><strong>3.</strong> Комиссия платформы — 0% с каждой сделки.</p>
              <p><strong>4.</strong> Вывод средств доступен от 50 ₽, обработка — моментально.</p>
              <p><strong>5.</strong> Запрещены любые виды мошенничества и манипуляций ценами.</p>
              <p><strong>6.</strong> Администрация оставляет за собой право блокировки аккаунта при нарушении правил.</p>
              <p className="mt-8 text-yellow-400 font-semibold">
                Используя ALVORA, вы соглашаетесь с данными правилами.
              </p>
            </div>

            <div className="alt-modal-footer">
              <button className="alt-btn-golden" onClick={() => setShowTermsModal(false)}>
                Согласен
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
};

export default HeaderMenu;