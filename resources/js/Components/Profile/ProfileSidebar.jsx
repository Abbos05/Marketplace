import React from 'react';
import { Link, router } from '@inertiajs/react';
import { resolveAvatarUrl } from '@/lib/avatarUrl';

function NavIcon({ children }) {
  return <span className="profile-nav-icon" aria-hidden>{children}</span>;
}

function NavItem({ label, active, onClick, href, badge, className = '' }) {
  const cls = `profile-nav-item${active ? ' is-active' : ''}${className ? ` ${className}` : ''}`;
  const inner = (
    <>
      {label.icon}
      <span className="profile-nav-label">{label.text}</span>
      {badge > 0 && <span className="profile-nav-badge">{badge > 99 ? '99+' : badge}</span>}
    </>
  );

  if (href) {
    return (
      <Link href={href} className={cls} preserveScroll>
        {inner}
      </Link>
    );
  }

  return (
    <button type="button" className={cls} onClick={onClick}>
      {inner}
    </button>
  );
}

function NavGroup({ title, children }) {
  if (!children) return null;
  return (
    <div className="profile-nav-group">
      {title && <div className="profile-nav-group-title">{title}</div>}
      <div className="profile-nav-group-items">{children}</div>
    </div>
  );
}

const icons = {
  home: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <path d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z" />
      </svg>
    </NavIcon>
  ),
  orders: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <path d="M6 6h15l-1.5 9H7.5L6 6zM6 6L5 3H2" />
        <circle cx="9" cy="20" r="1" />
        <circle cx="18" cy="20" r="1" />
      </svg>
    </NavIcon>
  ),
  heart: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <path d="M12 21s-7-4.5-7-10a4 4 0 017-2 4 4 0 017 2c0 5.5-7 10-7 10z" />
      </svg>
    </NavIcon>
  ),
  cart: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <rect x="3" y="6" width="18" height="14" rx="2" />
        <path d="M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2" />
      </svg>
    </NavIcon>
  ),
  star: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7l3-7z" />
      </svg>
    </NavIcon>
  ),
  chat: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />

      </svg>
    </NavIcon>
  ),
  user: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <circle cx="12" cy="8" r="4" />
        <path d="M4 20c0-4 3.5-6 8-6s8 2 8 6" />
      </svg>
    </NavIcon>
  ),
  mail: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <rect x="3" y="5" width="18" height="14" rx="2" />
        <path d="M3 7l9 6 9-6" />
      </svg>
    </NavIcon>
  ),
  box: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <path d="M12 2l8 4.5v7L12 18l-8-4.5v-7L12 2z" />
        <path d="M12 9.5v8.5M4 6.5l8 4.5 8-4.5" />
      </svg>
    </NavIcon>
  ),
  lock: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <rect x="5" y="11" width="14" height="10" rx="2" />
        <path d="M8 11V8a4 4 0 118 0v3" />
      </svg>
    </NavIcon>
  ),
  building: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <path d="M4 20V4l8-2v18H4zm8 0V8l8-2v14h-8z" />
      </svg>
    </NavIcon>
  ),
  shop: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <path d="M3 9l2-6h14l2 6M3 9h18v11a1 1 0 01-1 1H4a1 1 0 01-1-1V9z" />
        <path d="M9 14h6" />
      </svg>
    </NavIcon>
  ),
  pin: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <path d="M12 21s6-5.2 6-10a6 6 0 10-12 0c0 4.8 6 10 6 10z" />
        <circle cx="12" cy="11" r="2.5" />
      </svg>
    </NavIcon>
  ),
  shield: (
    <NavIcon>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
        <path d="M12 2l8 3v6c0 5-3.5 9-8 11-4.5-2-8-6-8-11V5l8-3z" />
      </svg>
    </NavIcon>
  ),
};

export default function ProfileSidebar({
  auth,
  activeTab,
  settingsSection,
  onTabChange,
  onSettingsSection,
  onLogout,
  isUserBlocked,
  formatName,
  formatPhone,
  showCompaniesTab,
  isStaffUser,
  staffAccess,
  pvzAccess,
  isSeller,
  counts = {},
  mobileMenuMode = false,
}) {
  const goTab = (tab) => onTabChange(tab);
  const goSettings = (section) => onSettingsSection(section);

  function getUserDisplayName(user) {
    const firstName = (user.name || '').trim();
    const lastName = (user.last_name || '').trim();
    
    // Функция для капитализации первой буквы
    const capitalize = (str) => {
      if (!str) return '';
      return str[0].toUpperCase() + str.slice(1).toLowerCase();
    };
    
    // Если имя длинное (≥14 символов) - показываем только имя с троеточием
    if (firstName.length >= 14) {
      return `${capitalize(firstName.slice(0, 13))}...`;
    }
    
    // Если имя короткое - показываем имя и первую букву фамилии
    if (firstName && lastName) {
      return `${capitalize(firstName)} ${capitalize(lastName[0])}.`;
    }
    
    return capitalize(firstName) || capitalize(lastName) || 'Пользователь';
  }
  return (
    <aside className={`profile-sidebar${mobileMenuMode ? ' profile-sidebar--mobile-menu' : ''}`}>
      <div className="profile-user">
        <img
          src={auth.user.avatar || '/img/profiles/profile.png'}
          className="avatar"
          alt=""
        />
        <div className="profile-user-info">
          <div className="name" title={[auth.user.name, auth.user.last_name].filter(Boolean).join(' ')}>
            {formatName(getUserDisplayName(auth.user))}
            {auth.user.email && auth.user.phone && (
              <img src="/img/profiles/check.png" alt="" className="verify-icon" />
            )}
          </div>
          {auth.user.phone && (
            <div className="profile-user-phone">{formatPhone ? formatPhone(auth.user.phone) : auth.user.phone}</div>
          )}
          {isUserBlocked && (
            <div className="profile-blocked-badge">Аккаунт заблокирован</div>
          )}
        </div>
      </div>

      <nav className="profile-nav" aria-label="Навигация профиля">
        <NavGroup title="Покупки">
          <NavItem
            label={{ text: 'Главная', icon: icons.home }}
            active={activeTab === 'main'}
            onClick={() => goTab('main')}
          />
          <NavItem
            label={{ text: 'Заказы', icon: icons.orders }}
            active={activeTab === 'orders'}
            onClick={() => goTab('orders')}
            badge={counts.orders}
          />
          <NavItem
            label={{ text: 'Избранное', icon: icons.heart }}
            active={activeTab === 'favorites'}
            onClick={() => goTab('favorites')}
            badge={counts.favorites}
          />
          <NavItem
            label={{ text: 'Корзина', icon: icons.cart }}
            href="/cart"
          />
          <NavItem
            label={{ text: 'Мои отзывы', icon: icons.star }}
            active={activeTab === 'reviews'}
            onClick={() => goTab('reviews')}
            badge={counts.reviews}
          />
        </NavGroup>

        <NavGroup title="Сервисы">
          <NavItem
            label={{ text: 'Сообщения', icon: icons.chat }}
            active={activeTab === 'messages'}
            onClick={() => goTab('messages')}
          />
          <NavItem
            label={{ text: 'Пункт выдачи', icon: icons.box }}
            active={activeTab === 'pickup'}
            onClick={() => goTab('pickup')}
          />
        </NavGroup>

        <NavGroup title="Профиль и настройки">
          <NavItem
            label={{ text: 'Обзор настроек', icon: icons.user }}
            active={activeTab === 'settings' && !settingsSection}
            onClick={() => goSettings(null)}
          />
          <NavItem
            label={{ text: 'Личные данные', icon: icons.user }}
            active={activeTab === 'settings' && settingsSection === 'personal'}
            onClick={() => goSettings('personal')}
            className="profile-nav-item--sub"
          />
          <NavItem
            label={{ text: 'Контакты', icon: icons.mail }}
            active={activeTab === 'settings' && settingsSection === 'contacts'}
            onClick={() => goSettings('contacts')}
            className="profile-nav-item--sub"
          />
          <NavItem
            label={{ text: 'Безопасность', icon: icons.lock }}
            active={activeTab === 'settings' && settingsSection === 'security'}
            onClick={() => goSettings('security')}
            className="profile-nav-item--sub"
          />
          <NavItem
            label={{ text: 'Устройства и входы', icon: icons.shield }}
            active={activeTab === 'settings' && settingsSection === 'sessions'}
            onClick={() => goSettings('sessions')}
            className="profile-nav-item--sub"
          />
        </NavGroup>

        {!isStaffUser && (
          <NavGroup title="Бизнес и партнёрство">
            <NavItem
              label={{
                text: showCompaniesTab || isSeller ? 'Мои компании' : 'Открыть компанию',
                icon: icons.building,
              }}
              active={activeTab === 'company'}
              onClick={() => goTab('company')}
            />
            {isSeller && (
              <NavItem
                label={{ text: 'Панель продавца', icon: icons.shop }}
                href="/seller/dashboard"
              />
            )}
            {pvzAccess?.isPvz && (
              <NavItem
                label={{ text: 'Панель ПВЗ', icon: icons.pin }}
                onClick={() => router.visit('/pvz')}
              />
            )}
            {!pvzAccess?.isPvz && (
              <NavItem
                label={{ text: 'Партнёрство ПВЗ', icon: icons.pin }}
                active={activeTab === 'pvz'}
                onClick={() => goTab('pvz')}
              />
            )}
          </NavGroup>
        )}

        {isStaffUser && (
          <NavGroup title="Администрирование">
            <NavItem
              label={{
                text: staffAccess?.isModerator ? 'Панель модератора' : 'Панель администратора',
                icon: icons.shield,
              }}
              onClick={() => router.visit('/admin/dashboard')}
              className="profile-nav-item--admin"
            />
          </NavGroup>
        )}
      </nav>

      <div className="profile-nav-footer">
        <button type="button" className="profile-nav-logout" onClick={onLogout}>
          <NavIcon>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
              <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" />
            </svg>
          </NavIcon>
          <span>Выйти из аккаунта</span>
        </button>
      </div>
    </aside>
  );
}
