import React, { useState, useEffect, useRef } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { resolveAvatarUrl } from '@/lib/avatarUrl';

const ProfileIcon = () => (
  <svg className="header-profile-trigger__icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" aria-hidden>
    <path
      fill="currentColor"
      d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"
    />
  </svg>
);

const ChevronIcon = ({ open }) => (
  <svg
    className={`header-profile-trigger__chevron${open ? ' is-open' : ''}`}
    width="14"
    height="14"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="2.5"
    aria-hidden
  >
    <polyline points="6 9 12 15 18 9" />
  </svg>
);

function displayUserName(name) {
  const trimmed = (name || '').trim();
  if (!trimmed) return 'Пользователь';
  if (trimmed.length <= 14) return trimmed;
  return `${trimmed.slice(0, 13)}`;
}

const HeaderMenu = ({ setIsModalOpen }) => {
  const { auth: { user } } = usePage().props;
  const isAuthenticated = !!user;

  const [isOpen, setIsOpen] = useState(false);
  const [theme, setTheme] = useState('dark');
  const menuRef = useRef(null);

  const avatarUrl = isAuthenticated ? resolveAvatarUrl(user.avatar) : null;

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

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (menuRef.current && !menuRef.current.contains(e.target)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    if (!isOpen) return;
    const onKey = (e) => {
      if (e.key === 'Escape') setIsOpen(false);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [isOpen]);

  return (
    <li className="header-menu" ref={menuRef}>
      <button
        type="button"
        className={`header-profile-trigger${isOpen ? ' is-open' : ''}`}
        onClick={() => setIsOpen((v) => !v)}
        aria-expanded={isOpen}
        aria-haspopup="true"
      >
        {isAuthenticated ? (
          <>
            {avatarUrl ? (
              <img src={avatarUrl} alt="" className="header-profile-trigger__avatar" />
            ) : (
              <ProfileIcon />
            )}
            <span className="header-profile-trigger__name">{displayUserName(user.name)}</span>
            <ChevronIcon open={isOpen} />
          </>
        ) : (
          <>
            <ProfileIcon />
            <span className="header-profile-trigger__name">Профиль</span>
            <ChevronIcon open={isOpen} />
          </>
        )}
      </button>

      {isOpen && (
        <ul className="header-menu-dropdown" role="menu">
          {isAuthenticated ? (
            <li className="header-menu-item header-menu-item--profile" role="none">
              <Link href="/profile" role="menuitem" onClick={() => setIsOpen(false)}>
                {avatarUrl ? (
                  <img src={avatarUrl} alt="" className="header-menu-dropdown-avatar" />
                ) : (
                  <ProfileIcon />
                )}
                <span className="header-menu-dropdown-user">
                  <span className="header-menu-dropdown-name">{user.name || 'Пользователь'}</span>
                  {user.phone && <span className="header-menu-dropdown-meta">Телефон подтверждён</span>}
                </span>
              </Link>
            </li>
          ) : (
            <li className="header-menu-item auth-item" role="none">
              <Link
                href="#"
                role="menuitem"
                onClick={(e) => {
                  e.preventDefault();
                  setIsModalOpen(true);
                  setIsOpen(false);
                }}
              >
                <ProfileIcon />
                Войти / Регистрация
              </Link>
            </li>
          )}

          <li className="header-menu-divider" role="separator" />

          <li className="header-menu-item" role="none">
            <Link href="/about" role="menuitem" onClick={() => setIsOpen(false)}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="10" />
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                <line x1="12" y1="17" x2="12.01" y2="17" />
              </svg>
              О проекте
            </Link>
          </li>

          <li className="header-menu-item" role="none">
            <Link href="/contacts" role="menuitem" onClick={() => setIsOpen(false)}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                <polyline points="22,6 12,13 2,6" />
              </svg>
              Контакты
            </Link>
          </li>

          <li className="header-menu-item" role="none">
            <Link href="/help" role="menuitem" onClick={() => setIsOpen(false)}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
              Поддержка
            </Link>
          </li>

          <li className="header-menu-item" role="none">
            <Link href="/terms" role="menuitem" onClick={() => setIsOpen(false)}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <line x1="8" y1="6" x2="21" y2="6" />
                <line x1="8" y1="12" x2="21" y2="12" />
                <line x1="8" y1="18" x2="21" y2="18" />
                <circle cx="4" cy="6" r="2" />
                <circle cx="4" cy="12" r="2" />
                <circle cx="4" cy="18" r="2" />
              </svg>
              Правила
            </Link>
          </li>

        

          {isAuthenticated && (
            <li className="header-menu-item logout-item" role="none" onClick={handleLogout}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
              </svg>
              Выйти
            </li>
          )}
        </ul>
      )}
    </li>
  );
};

export default HeaderMenu;
