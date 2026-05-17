import React, { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { router, useForm } from '@inertiajs/react';
import { canManageUserAsStaff, canAssignStaffRoles, isStaff, roleOptionsFor } from '@/lib/staffAccess';

import PhoneVerificationModal from '@/Components/Profile/PhoneVerificationModal';
import BlockedAccountModal from '@/Components/Profile/BlockedAccountModal';
import DeleteAccountModal from '@/Components/Profile/DeleteAccountModal';
import MyFavorites from '@/Components/Product/ProductPage';
import Recommendations from '@/Components/Product/ProductPage';
import Barcode from 'react-barcode';

import '../../../css/profile/style.css';

import CompanyFormModal from '@/Components/Company/CompanyFormModal';
import CompanyProfile from '@/Components/Company/CompanyProfile';

export default function Profile({ auth, products = [], LikeProducts = [], orders = [], myFavorites = [], sellerProfile = null, adminUsers = [], pickupPoints = [], userSessions = [], loginHistory = [], currentSessionId = null }) {
  const { staffAccess } = usePage().props;
  const [selectedOrder, setSelectedOrder] = useState(null);

  const { data: pickupForm, setData: setPickupForm, patch: patchPickup, processing: pickupProcessing, errors: pickupErrors } = useForm({
    default_pickup_point_id: auth.user.default_pickup_point_id ?? '',
  });

  useEffect(() => {
    setPickupForm('default_pickup_point_id', auth.user.default_pickup_point_id ?? '');
  }, [auth.user.default_pickup_point_id]);

  const handlePickupSubmit = (e) => {
    e.preventDefault();
    patchPickup(route('profile.default-pickup'), {
      preserveScroll: true,
      onSuccess: () => { setEditingCard(null); setMessage('Пункт выдачи сохранён'); },
    });
  };

  const isStaffUser = staffAccess?.isStaff ?? isStaff(auth.user);
  const canAssignRoles = staffAccess?.canAssignStaffRoles ?? canAssignStaffRoles(auth.user);
  const panelTitle = staffAccess?.panelTitle ?? 'Панель управления';
  const [activeTab, setActiveTabState] = useState(
    () => sessionStorage.getItem('profile_tab') || 'main'
  );
  const setActiveTab = (tab) => {
    setActiveTabState(tab);
    sessionStorage.setItem('profile_tab', tab);
  };
  const [displayCount, setDisplayCount] = useState(8);
  const [adminSearch, setAdminSearch] = useState('');
  const [adminFilter, setAdminFilter] = useState('all');
  const [adminRoleChanges, setAdminRoleChanges] = useState({});
  const showMore = () => {
    setDisplayCount(prevCount => Math.min(prevCount + 8, LikeProducts.length));
  };

  // Модалки
  // Верификация: открывается когда нет email (телефон — основной идентификатор, уже есть)
  const [isPhoneOpen, setIsPhoneOpen] = useState(false);
  const [isBlockedOpen, setIsBlockedOpen] = useState(auth.user.is_blocked === 1);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [message, setMessage] = useState(null);
  const needsProfileVerification = (!auth.user.email || !auth.user.phone) && auth.user.is_active !== 0;

  useEffect(() => {
    if (auth.user.is_blocked === 1) setIsBlockedOpen(true);
  }, [auth.user.is_blocked]);

  // ─── Настройки: какая карточка открыта для редактирования ───────────────────
  const [editingCard, setEditingCard] = useState(null); // 'personal' | 'contacts' | 'pickup' | 'security' | 'sessions'

  // Форма: личные данные (фото + имя + фамилия + описание)
  const [avatarPreview, setAvatarPreview] = useState(auth.user.avatar || '/img/profiles/profile.png');
  const { data: profileData, setData: setProfileData, post: postProfile, processing: profileProcessing, errors: profileErrors, reset: resetProfile } = useForm({
    name:        auth.user.name        || '',
    last_name:   auth.user.last_name   || '',
    description: auth.user.description || '',
    avatar: null,
  });

  const handleAvatarChange = (e) => {
    const file = e.target.files[0];
    if (file) { setProfileData('avatar', file); setAvatarPreview(URL.createObjectURL(file)); }
  };

  const handleProfileSubmit = (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append('name',        profileData.name);
    formData.append('last_name',   profileData.last_name);
    formData.append('description', profileData.description);
    if (profileData.avatar) formData.append('avatar', profileData.avatar);
    postProfile('/profile/update', {
      data: formData, forceFormData: true, preserveState: true, preserveScroll: true,
      onSuccess: () => { resetProfile('avatar'); setEditingCard(null); setMessage('Личные данные обновлены!'); },
    });
  };

  // Форма: контактные данные (только email; телефон — readonly)
  const { data: contactData, setData: setContactData, post: postContact, processing: contactProcessing, errors: contactErrors } = useForm({
    email: auth.user.email || '',
  });

  const formatPhone = (value) => {
    const digits = (value || '').replace(/\D/g, '').slice(0, 11);
    if (!digits) return '';
    let f = '+7';
    if (digits.length > 1) f += ' ' + digits.substring(1, 4);
    if (digits.length > 4) f += ' ' + digits.substring(4, 7);
    if (digits.length > 7) f += ' ' + digits.substring(7, 9);
    if (digits.length > 9) f += ' ' + digits.substring(9, 11);
    return f;
  };

  const loginMethodLabels = {
    password: 'Пароль',
    phone_otp: 'Код по телефону',
    email_otp: 'Код по email',
    phone_password: 'Пароль по телефону',
    google: 'Google',
    yandex: 'Yandex',
    github: 'GitHub',
  };

  const parseUserAgent = (ua) => {
    if (!ua) return { browser: '—', device: '—' };
    let browser = 'Прочее';
    let device = 'Десктоп';
    if (/iPhone|iPad/.test(ua)) device = 'iPhone/iPad';
    else if (/Android/.test(ua)) device = 'Android';
    else if (/Mac OS X/.test(ua)) device = 'macOS';
    else if (/Windows/.test(ua)) device = 'Windows';
    else if (/Linux/.test(ua)) device = 'Linux';
    if (/Edg\//.test(ua)) browser = 'Edge';
    else if (/OPR\/|Opera/.test(ua)) browser = 'Opera';
    else if (/Chrome\//.test(ua)) browser = 'Chrome';
    else if (/Firefox\//.test(ua)) browser = 'Firefox';
    else if (/Safari\//.test(ua)) browser = 'Safari';
    return { browser, device };
  };

  const timeAgo = (iso) => {
    if (!iso) return '—';
    const diff = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
    if (diff < 60) return `${diff} с назад`;
    if (diff < 3600) return `${Math.floor(diff / 60)} мин назад`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} ч назад`;
    return `${Math.floor(diff / 86400)} д назад`;
  };

  const handleContactSubmit = (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append('email', contactData.email);
    // phone НЕ отправляем — изменяется только через поддержку
    postContact('/profile/update', {
      data: formData, forceFormData: true, preserveState: true, preserveScroll: true,
      onSuccess: () => { setEditingCard(null); setMessage('Email обновлён!'); },
    });
  };

  // Форма: безопасность (смена пароля)
  const { data: secData, setData: setSecData, post: postSec, processing: secProcessing, errors: secErrors, reset: resetSec } = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const handleSecuritySubmit = (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append('current_password',     secData.current_password);
    formData.append('password',             secData.password);
    formData.append('password_confirmation', secData.password_confirmation);
    postSec('/profile/update', {
      data: formData, forceFormData: true, preserveState: true, preserveScroll: true,
      onSuccess: () => { resetSec(); setEditingCard(null); setMessage('Пароль успешно изменён!'); },
    });
  };

  // Выход из аккаунта
  const handleLogout = () => {
    if (!confirm('Вы уверены, что хотите выйти из аккаунта?')) return;
    router.post(route('logout'), {}, {
      // После разлогина делаем hard-reload, чтобы получить свежий CSRF-токен.
      // Без этого мета-тег csrf-token остаётся старым и следующий POST
      // из модала авторизации падает с "CSRF token mismatch".
      onSuccess: () => { window.location.href = '/'; }
    });
  };

  const kickOwnSession = (sessionId) => {
    if (sessionId === currentSessionId) {
      setMessage('Нельзя завершить текущее устройство');
      return;
    }
    if (!confirm('Завершить вход на этом устройстве?')) return;
    router.delete(route('profile.sessions.destroy', sessionId), {
      preserveScroll: true,
      onSuccess: () => setMessage('Сессия завершена'),
    });
  };

  const onlineUserSessions = userSessions.filter((session) => session.is_online);
  const lastLogin = loginHistory[0] ?? null;

  const formatName = (name) => {
    if (!name) return 'Пользователь';
    return name.length > 20 ? name.slice(0, 20) + '...' : name;
  };

  useEffect(() => {
    const contentElement = document.querySelector('body');
    if (contentElement) contentElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, [activeTab]);

  // Компания
  const [showCompanyForm, setShowCompanyForm] = useState(false);
  const { data, setData, post, processing, errors, reset } = useForm({
    inn: '',
    shop_name: '',
    legal_address: '',
    pickup_address: '',
    description: '',
  });

  const weekDays = {
    mon: 'Понедельник', tue: 'Вторник', wed: 'Среда',
    thu: 'Четверг', fri: 'Пятница', sat: 'Суббота', sun: 'Воскресенье',
  };

  const handleSubmitCompany = (e) => {
    e.preventDefault();
    post('/seller-profile/store', {
      preserveScroll: true,
      onSuccess: () => {
        setShowCompanyForm(false);
        reset();
        setMessage('Компания успешно добавлена!');
        setTimeout(() => router.reload(), 500);
      },
      onError: (errs) => {
        setMessage('Ошибка при создании компании: ' + (errs.error || 'Попробуйте еще раз'));
      },
    });
  };

  return (
    <MainLayout>
      <Head title="Профиль" />

      <div className="profile-page">


        {/* ─── LEFT SIDEBAR ─────────────────────────────────────────────────── */}
        <div className="profile-sidebar">
          <div className="profile-user">
            <img
              src={auth.user.avatar || '/img/profiles/profile.png'}
              className="avatar"
              alt="avatar"
            />
            <div className="name" title={auth.user.name}>
              {formatName(auth.user.name)}
              {auth.user.email && auth.user.phone && (
                <img src="/img/profiles/check.png" alt="Верифицирован" className="verify-icon" />
              )}
            </div>
          </div>

          <div className="profile-menu">
            {needsProfileVerification && (
              <div className="verify" onClick={() => setIsPhoneOpen(true)}>
                Пройти верификацию
              </div>
            )}

            <div onClick={() => setActiveTab('main')} className={activeTab === 'main' ? 'active' : ''}>
              Главная
            </div>

            <div onClick={() => setActiveTab('orders')} className={activeTab === 'orders' ? 'active' : ''}>
              Заказы
            </div>

            <div onClick={() => setActiveTab('favorites')} className={activeTab === 'favorites' ? 'active' : ''}>
              Избранное
            </div>

            {!isStaffUser && (
              <div onClick={() => setActiveTab('company')} className={activeTab === 'company' ? 'active' : ''}>
                Мои компании
              </div>
            )}

            <div
              onClick={() => { setActiveTab('settings'); setEditingCard(null); }}
              className={activeTab === 'settings' ? 'active' : ''}
            >
              Настройки
            </div>

            {isStaffUser && (
              <div
                onClick={() => setActiveTab('admin')}
                className={`profile-menu-admin ${activeTab === 'admin' ? 'active' : ''}`}
              >
                {staffAccess?.isModerator ? 'Раздел модератора' : 'Раздел администратора'}
              </div>
            )}

            <div className="profile-menu-logout" onClick={handleLogout}>
              Выйти из аккаунта
            </div>
          </div>
        </div>

        {/* ─── RIGHT CONTENT ────────────────────────────────────────────────── */}
        <div className="profile-content">

          {needsProfileVerification && (
            <div className="verify-banner">
              <div className="verify-banner-content">
                <div className="verify-banner-text">
                  <h2>Заполните профиль</h2>
                  <p>Укажите имя, email и подтвердите телефон, чтобы открыть все возможности платформы:</p>
                  <ul>
                    <li>Оформление заказов</li>
                    <li>Покупка товаров со скидкой</li>
                    <li>Получение и использование промокодов</li>
                    <li>Участие в эксклюзивных акциях</li>
                  </ul>
                </div>
                <div className="verify-banner-button">
                  <button className="verify-green-btn" onClick={() => setIsPhoneOpen(true)}>
                    Заполнить профиль
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* === ГЛАВНАЯ === */}
          {activeTab === 'main' && (
            <div className="Profile__recommendated">
              {LikeProducts && LikeProducts.length > 0 && (
                <>
                  <section className="category-header">
                    <h2 className='profile__title'>Рекомендации для вас</h2>
                  </section>
                  <Recommendations products={LikeProducts.slice(0, displayCount)} />
                  {displayCount < LikeProducts.length && (
                    <button className="showMore__btn" onClick={showMore}>Показать еще</button>
                  )}
                </>
              )}
            </div>
          )}

          {/* === ЗАКАЗЫ === */}
          {activeTab === 'orders' && (
            <div className="orders-section">
              <div className="orders-header">
                <h2 className='profile__title'>Мои заказы</h2>
              </div>
              {orders && orders.length > 0 ? (
                <>
                  <div className="orders-list products">
                    {orders.map((order) => (
                      <div
                        key={order.id}
                        className="order-card"
                        onClick={() => window.location.href = `/orders/${order.id}`}
                        style={{ cursor: 'pointer' }}
                      >
                        <div className="order-info">
                          {order.items && order.items[0] && (
                            <img
                              src={order.items[0].variant?.product?.image || '/img/products/default.png'}
                              className="order-image"
                            />
                          )}
                          <div className="order-details">
                            <h3>Заказ #{order.number}</h3>
                            <p className="order-price">{order.total} ₽</p>
                            <div className={`order-status-badge status-${order.frontend_status}`}>
                              {order.frontend_status === 'ready' && 'Можно забрать'}
                              {order.frontend_status === 'shipping' && 'В пути'}
                              {order.frontend_status === 'pending' && 'Обрабатывается'}
                            </div>
                          </div>
                        </div>
                        <div className="order-actions">
                          <div className="qr-code-box" onClick={(e) => { e.stopPropagation(); setSelectedOrder(order); }}>
                            <Barcode value={order.order_code} width={2} height={40} />
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                  <div className="all-orders-btn-container">
                    <Link href="/orders" className="all-orders-btn">Все заказы</Link>
                  </div>
                </>
              ) : (
                <div className="empty-orders products">
                  <p>У вас пока нет заказов</p>
                  <Link href="/" className="shop-now-btn">Перейти в каталог</Link>
                </div>
              )}
            </div>
          )}

          {selectedOrder && (
            <div className="qr-modal" onClick={() => setSelectedOrder(null)}>
              <div className="qr-modal-content" onClick={(e) => e.stopPropagation()}>
                <div className="qr-modal-header">
                  <h3>Заказ #{selectedOrder.number}</h3>
                  <button className="close-btn" onClick={() => setSelectedOrder(null)}>✕</button>
                </div>
                <div className="qr-modal-body">
                  <Barcode value={selectedOrder.order_code} width={3} height={100} />
                  <p className="pickup-code-text">Код: {selectedOrder.order_code}</p>
                  <p className="pickup-instruction">Покажите код при получении</p>
                </div>
              </div>
            </div>
          )}

          {/* === ИЗБРАННОЕ === */}
          {activeTab === 'favorites' && (
            <div className="Profile__recommendated">
              <section className="category-header">
                <h2 className='profile__title'>Мои избранные товары</h2>
              </section>
              {myFavorites && myFavorites.length > 0 ? (
                <>
                  <MyFavorites products={myFavorites.slice(0, 4)} />
                  <Link href="/favorites" className="showMore__btn">Показать еще</Link>
                </>
              ) : (
                <div className="empty-favorite products">
                  <p>У вас пока избранных товаров</p>
                  <Link href="/" className="shop-now-btn">Перейти в каталог</Link>
                </div>
              )}
            </div>
          )}

          {/* === КОМПАНИИ === */}
          {activeTab === 'company' && (
            <div className="company-page">
              {!sellerProfile && !showCompanyForm && (
                <div className="company-empty-state">
                  <div className="empty-state-icon">🏢</div>
                  <h2>Добавьте компанию</h2>
                  <p>Укажите ИНН компании, которая будет оплачивать заказы</p>
                  <button className="add-company-btn" onClick={() => setShowCompanyForm(true)}>
                    + Добавить компанию
                  </button>
                </div>
              )}

              {showCompanyForm && (
                <div className="company-form-container">
                  <div className="form-header">
                    <h2>Добавьте компанию</h2>
                    <button className="back-btn" onClick={() => setShowCompanyForm(false)}>← Назад</button>
                  </div>
                  <form onSubmit={handleSubmitCompany} className="company-form">
                    <div className="form-group">
                      <label>ИНН компании <span className="required">*</span></label>
                      <input type="text" value={data.inn} onChange={(e) => setData('inn', e.target.value)} placeholder="Введите 10 или 12 цифр" maxLength={12} className={errors.inn ? 'error' : ''} />
                      {errors.inn && <span className="error-text">{errors.inn}</span>}
                      <p className="hint">Укажите ИНН компании, которая будет оплачивать заказы</p>
                    </div>
                    <div className="form-group">
                      <label>Название магазина <span className="required">*</span></label>
                      <input type="text" value={data.shop_name} onChange={(e) => setData('shop_name', e.target.value)} placeholder="Например: ООО 'Ромашка'" className={errors.shop_name ? 'error' : ''} />
                      {errors.shop_name && <span className="error-text">{errors.shop_name}</span>}
                    </div>
                    <div className="form-group">
                      <label>Юридический адрес</label>
                      <input type="text" value={data.legal_address} onChange={(e) => setData('legal_address', e.target.value)} placeholder="Город, улица, дом, офис" />
                    </div>
                    <div className="form-group">
                      <label>Адрес самовывоза <span className="required">*</span></label>
                      <input type="text" value={data.pickup_address} onChange={(e) => setData('pickup_address', e.target.value)} placeholder="Адрес, где покупатели могут забрать товар" className={errors.pickup_address ? 'error' : ''} />
                      {errors.pickup_address && <span className="error-text">{errors.pickup_address}</span>}
                    </div>
                    <div className="form-group">
                      <label>Описание магазина</label>
                      <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder="Расскажите о вашей компании..." rows={3} />
                    </div>
                    <div className="form-group">
                      <p className="hint" style={{ background: '#f1f5f9', borderRadius: 8, padding: '10px 14px', margin: 0, fontSize: 13 }}>
                        Режим работы можно настроить после создания компании в разделе{' '}
                        <strong>Настройки продавца → Магазин</strong>.
                      </p>
                    </div>
                    <div className="form-actions">
                      <button type="button" className="cancel-btn" onClick={() => setShowCompanyForm(false)}>Отмена</button>
                      <button type="submit" className="submit-btn" disabled={processing}>{processing ? 'Сохранение...' : 'Добавить компанию'}</button>
                    </div>
                  </form>
                </div>
              )}

              {sellerProfile && (
                <div className="company-card">
                  <div className="company-card-header">
                    <div className="company-logo"><div className="logo-placeholder">🏢</div></div>
                    <div className="company-title">
                      <h2>{sellerProfile.shop_name}</h2>
                      <p className="company-inn">ИНН: {sellerProfile.inn}</p>
                      <div className="company-status">
                        <span className={`status-badge ${auth.user.role === 'seller' ? 'active' : 'pending'}`}>
                          {auth.user.role === 'seller' ? 'Активна' : 'На проверке'}
                        </span>
                      </div>
                    </div>
                  </div>
                  <div className="company-card-body">
                    <div className="info-section">
                      <h3>Контактная информация</h3>
                      <div className="info-row"><span className="info-label">Юридический адрес:</span><span className="info-value">{sellerProfile.legal_address || '—'}</span></div>
                      <div className="info-row"><span className="info-label">Адрес самовывоза:</span><span className="info-value">{sellerProfile.pickup_address}</span></div>
                      {sellerProfile.description && (
                        <div className="info-row"><span className="info-label">Описание:</span><span className="info-value">{sellerProfile.description}</span></div>
                      )}
                    </div>
                    {sellerProfile.working_hours && (
                      <div className="info-section">
                        <h3>Часы работы</h3>
                        <div className="working-hours-list">
                          {Object.entries(sellerProfile.working_hours).map(([day, hours]) => {
                            const dayName = weekDays[day];
                            if (!dayName) return null;
                            const isOpen = hours.open ?? hours.enabled ?? false;
                            return (
                              <div key={day} className="hours-row">
                                <span className="day">{dayName}</span>
                                {isOpen ? <span className="time">{hours.from} — {hours.to}</span> : <span className="closed">Выходной</span>}
                              </div>
                            );
                          })}
                        </div>
                      </div>
                    )}
                    {sellerProfile.rating > 0 && (
                      <div className="info-section stats">
                        <div className="stat-item"><span className="stat-value">⭐ {sellerProfile.rating}</span><span className="stat-label">Рейтинг</span></div>
                        <div className="stat-item"><span className="stat-value">{sellerProfile.total_sales}</span><span className="stat-label">Продаж</span></div>
                      </div>
                    )}
                    {auth.user.role === 'seller' && (
                      <Link href="/seller/dashboard" className="dashboard-link">Перейти в панель продавца →</Link>
                    )}
                  </div>
                </div>
              )}
            </div>
          )}

          {/* === НАСТРОЙКИ === */}
          {activeTab === 'settings' && (
            <div className="settings-section">
              <h2 className="profile__title">Настройки</h2>

              {/* ── Карточка 1: Личные данные (фото + имя + фамилия + описание) ── */}
              <div className="settings-card">
                <div className="settings-card-header">
                  <div className="settings-card-meta">
                    <span className="settings-card-icon">👤</span>
                    <div>
                      <div className="settings-card-title">Личные данные</div>
                      {editingCard !== 'personal' && (
                        <div className="settings-card-value">
                          <img src={auth.user.avatar || '/img/profiles/profile.png'} className="settings-avatar-thumb" alt="avatar" />
                          <span>{[auth.user.name, auth.user.last_name].filter(Boolean).join(' ') || 'Не заполнено'}</span>
                        </div>
                      )}
                    </div>
                  </div>
                  {editingCard !== 'personal' ? (
                    <button className="settings-edit-btn" onClick={() => {
                      setProfileData('name',        auth.user.name        || '');
                      setProfileData('last_name',   auth.user.last_name   || '');
                      setProfileData('description', auth.user.description || '');
                      setAvatarPreview(auth.user.avatar || '/img/profiles/profile.png');
                      setEditingCard('personal');
                    }}>Изменить</button>
                  ) : (
                    <button className="settings-cancel-btn" onClick={() => { setEditingCard(null); resetProfile('avatar'); setAvatarPreview(auth.user.avatar || '/img/profiles/profile.png'); }}>Отмена</button>
                  )}
                </div>

                {editingCard === 'personal' && (
                  <form className="settings-card-body" onSubmit={handleProfileSubmit}>
                    <div className="settings-avatar-upload-row">
                      <img src={avatarPreview} className="settings-avatar-large" alt="Превью" />
                      <label className="settings-avatar-upload-btn">
                        Загрузить фото
                        <input type="file" accept="image/*" onChange={handleAvatarChange} style={{ display: 'none' }} />
                      </label>
                    </div>
                    <div className="settings-fields-row">
                      <div className="settings-field">
                        <label className="settings-label">Имя</label>
                        <input
                          type="text"
                          value={profileData.name}
                          onChange={(e) => setProfileData('name', e.target.value.slice(0, 50))}
                          className="settings-input"
                          placeholder="Имя"
                          maxLength={50}
                        />
                        {profileErrors.name && <p className="settings-error">{profileErrors.name}</p>}
                      </div>
                      <div className="settings-field">
                        <label className="settings-label">Фамилия</label>
                        <input
                          type="text"
                          value={profileData.last_name}
                          onChange={(e) => setProfileData('last_name', e.target.value.slice(0, 50))}
                          className="settings-input"
                          placeholder="Фамилия"
                          maxLength={50}
                        />
                        {profileErrors.last_name && <p className="settings-error">{profileErrors.last_name}</p>}
                      </div>
                    </div>
                    <div className="settings-field">
                      <label className="settings-label">
                        О себе <span className="settings-hint">({profileData.description?.length || 0}/175)</span>
                      </label>
                      <textarea
                        rows={3}
                        value={profileData.description}
                        onChange={(e) => setProfileData('description', e.target.value.slice(0, 175))}
                        className="settings-input settings-textarea"
                        placeholder="Расскажите о себе..."
                        maxLength={175}
                      />
                      {profileErrors.description && <p className="settings-error">{profileErrors.description}</p>}
                    </div>
                    <div className="settings-card-actions">
                      <button type="submit" className="settings-save-btn" disabled={profileProcessing}>
                        {profileProcessing ? 'Сохранение...' : 'Сохранить'}
                      </button>
                    </div>
                  </form>
                )}
              </div>

              {/* ── Карточка 2: Контакты (email редактируемый + телефон readonly) ── */}
              <div className="settings-card">
                <div className="settings-card-header">
                  <div className="settings-card-meta">
                    <span className="settings-card-icon">📧</span>
                    <div>
                      <div className="settings-card-title">Контакты</div>
                      {editingCard !== 'contacts' && (
                        <div className="settings-card-value">
                          <span>{auth.user.email || 'Email не указан'}</span>
                          <span className="settings-value-sep">·</span>
                          <span>{auth.user.phone ? formatPhone(auth.user.phone) : 'Телефон не подтверждён'}</span>
                        </div>
                      )}
                    </div>
                  </div>
                  {editingCard !== 'contacts' ? (
                    <button className="settings-edit-btn" onClick={() => { setContactData('email', auth.user.email || ''); setEditingCard('contacts'); }}>Изменить</button>
                  ) : (
                    <button className="settings-cancel-btn" onClick={() => setEditingCard(null)}>Отмена</button>
                  )}
                </div>

                {editingCard === 'contacts' && (
                  <form className="settings-card-body" onSubmit={handleContactSubmit}>
                    <div className="settings-field">
                      <label className="settings-label">Email</label>
                      <input
                        type="email"
                        value={contactData.email}
                        onChange={(e) => setContactData('email', e.target.value)}
                        className="settings-input"
                        placeholder="example@mail.com"
                      />
                      {contactErrors.email && <p className="settings-error">{contactErrors.email}</p>}
                    </div>
                    <div className="settings-field">
                      <label className="settings-label">Телефон</label>
                      {auth.user.phone ? (
                        <>
                          <input
                            type="tel"
                            value={formatPhone(auth.user.phone)}
                            disabled
                            className="settings-input settings-input--readonly"
                          />
                          <p className="settings-hint-text">Номер телефона можно изменить только через службу поддержки</p>
                        </>
                      ) : (
                        <>
                          <button type="button" className="settings-save-btn" onClick={() => setIsPhoneOpen(true)}>
                            Подтвердить телефон
                          </button>
                          <p className="settings-hint-text">Без подтверждённого телефона нельзя оформить заказ.</p>
                        </>
                      )}
                    </div>
                    <div className="settings-card-actions">
                      <button type="submit" className="settings-save-btn" disabled={contactProcessing}>
                        {contactProcessing ? 'Сохранение...' : 'Сохранить'}
                      </button>
                    </div>
                  </form>
                )}
              </div>

              {/* ── Пункт выдачи по умолчанию ─────────────────────────────── */}
              <div className="settings-card">
                <div className="settings-card-header">
                  <div className="settings-card-meta">
                    <span className="settings-card-icon">📦</span>
                    <div>
                      <div className="settings-card-title">Пункт выдачи</div>
                      <div className="settings-card-value">
                        {pickupForm.default_pickup_point_id
                          ? (pickupPoints.find((p) => p.id === Number(pickupForm.default_pickup_point_id))?.label ?? 'Выбран')
                          : 'Не выбран — при заказе нужно будет указать'}
                      </div>
                    </div>
                  </div>
                  {editingCard !== 'pickup' ? (
                    <button className="settings-edit-btn" onClick={() => {
                      setPickupForm('default_pickup_point_id', auth.user.default_pickup_point_id ?? '');
                      setEditingCard('pickup');
                    }}>Изменить</button>
                  ) : (
                    <button className="settings-cancel-btn" onClick={() => {
                      setPickupForm('default_pickup_point_id', auth.user.default_pickup_point_id ?? '');
                      setEditingCard(null);
                    }}>Отмена</button>
                  )}
                </div>
                {editingCard === 'pickup' && (
                  <form className="settings-card-body" onSubmit={handlePickupSubmit}>
                    <div className="settings-field">
                      <label className="settings-label">ПВЗ по умолчанию</label>
                      <select
                        className="settings-input"
                        value={pickupForm.default_pickup_point_id === '' || pickupForm.default_pickup_point_id == null ? '' : String(pickupForm.default_pickup_point_id)}
                        onChange={(e) => setPickupForm('default_pickup_point_id', e.target.value === '' ? '' : Number(e.target.value))}
                      >
                        <option value="">— выберите позже при заказе —</option>
                        {pickupPoints.map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.label}
                          </option>
                        ))}
                      </select>
                      {pickupErrors.default_pickup_point_id && (
                        <p className="settings-error">{pickupErrors.default_pickup_point_id}</p>
                      )}
                      <p className="settings-hint-text">
                        Без пункта выдачи оформить заказ нельзя: укажите здесь или при каждом заказе в корзине.
                      </p>
                    </div>
                    <div className="settings-card-actions">
                      <button type="submit" className="settings-save-btn" disabled={pickupProcessing}>
                        {pickupProcessing ? 'Сохранение...' : 'Сохранить ПВЗ'}
                      </button>
                    </div>
                  </form>
                )}
              </div>

              {/* ── Карточка 3: Безопасность ─────────────────────────────────── */}
              <div className="settings-card">
                <div className="settings-card-header">
                  <div className="settings-card-meta">
                    <span className="settings-card-icon">🔒</span>
                    <div>
                      <div className="settings-card-title">Безопасность</div>
                      {editingCard !== 'security' && (
                        <div className="settings-card-value">Изменить пароль</div>
                      )}
                    </div>
                  </div>
                  {editingCard !== 'security' ? (
                    <button className="settings-edit-btn" onClick={() => { resetSec(); setEditingCard('security'); }}>Изменить</button>
                  ) : (
                    <button className="settings-cancel-btn" onClick={() => { resetSec(); setEditingCard(null); }}>Отмена</button>
                  )}
                </div>

                {editingCard === 'security' && (
                  <form className="settings-card-body" onSubmit={handleSecuritySubmit}>
                    {!auth.user?.newPassw && (
                      <div className="settings-field">
                        <label className="settings-label">Текущий пароль</label>
                        <input type="password" value={secData.current_password} onChange={(e) => setSecData('current_password', e.target.value)} className="settings-input" placeholder="••••••••" />
                        {secErrors.current_password && <p className="settings-error">{secErrors.current_password}</p>}
                      </div>
                    )}
                    <div className="settings-field">
                      <label className="settings-label">Новый пароль</label>
                      <input type="password" value={secData.password} onChange={(e) => setSecData('password', e.target.value)} className="settings-input" placeholder="Минимум 8 символов" />
                      {secErrors.password && <p className="settings-error">{secErrors.password}</p>}
                    </div>
                    <div className="settings-field">
                      <label className="settings-label">Подтвердить пароль</label>
                      <input type="password" value={secData.password_confirmation} onChange={(e) => setSecData('password_confirmation', e.target.value)} className="settings-input" placeholder="••••••••" />
                      {secErrors.password_confirmation && <p className="settings-error">{secErrors.password_confirmation}</p>}
                    </div>
                    <div className="settings-card-actions">
                      <button type="submit" className="settings-save-btn" disabled={secProcessing}>
                        {secProcessing ? 'Сохранение...' : 'Сохранить'}
                      </button>
                    </div>
                  </form>
                )}
              </div>

              {/* ── Карточка 4: Устройства и входы ─────────────────────────── */}
              <div className="settings-card">
                <div className="settings-card-header">
                  <div className="settings-card-meta">
                    <span className="settings-card-icon">🛡️</span>
                    <div>
                      <div className="settings-card-title">Устройства и входы</div>
                      {editingCard !== 'sessions' && (
                        <div className="settings-card-value">
                          <span>Сейчас онлайн: {onlineUserSessions.length}</span>
                          <span className="settings-value-sep">·</span>
                          <span>Последний вход: {lastLogin ? timeAgo(lastLogin.created_at) : 'нет данных'}</span>
                        </div>
                      )}
                    </div>
                  </div>
                  {editingCard !== 'sessions' ? (
                    <button className="settings-edit-btn" onClick={() => setEditingCard('sessions')}>Изменить</button>
                  ) : (
                    <button className="settings-cancel-btn" onClick={() => setEditingCard(null)}>Отмена</button>
                  )}
                </div>

                {editingCard === 'sessions' && (
                  <div className="settings-card-body">
                    <div className="settings-session-block">
                      <div className="settings-session-head">
                        <h4>Активные устройства</h4>
                        <span>{onlineUserSessions.length} онлайн из {userSessions.length}</span>
                      </div>
                      {userSessions.length === 0 ? (
                        <p className="settings-hint-text">Активных устройств пока нет.</p>
                      ) : (
                        <div className="settings-session-list">
                          {userSessions.map((session) => {
                            const { browser, device } = parseUserAgent(session.user_agent);
                            const isCurrent = session.id === currentSessionId;
                            return (
                              <div className="settings-session-item" key={session.id}>
                                <div className="settings-session-main">
                                  <span className={`settings-online-dot ${session.is_online ? 'online' : ''}`} />
                                  <div>
                                    <div className="settings-session-title">
                                      {browser} · {device}
                                      {isCurrent && <span className="settings-current-badge">это устройство</span>}
                                    </div>
                                    <div className="settings-session-meta">
                                      <span>{session.ip_address || 'IP не определён'}</span>
                                      <span>{timeAgo(session.last_activity)}</span>
                                    </div>
                                  </div>
                                </div>
                                {!isCurrent && (
                                  <button
                                    type="button"
                                    className="settings-danger-btn settings-session-kick"
                                    onClick={() => kickOwnSession(session.id)}
                                  >
                                    Завершить
                                  </button>
                                )}
                              </div>
                            );
                          })}
                        </div>
                      )}
                    </div>

                    <div className="settings-session-block">
                      <div className="settings-session-head">
                        <h4>История входов</h4>
                        <span>{loginHistory.length} записей</span>
                      </div>
                      {loginHistory.length === 0 ? (
                        <p className="settings-hint-text">История появится после следующего входа в аккаунт.</p>
                      ) : (
                        <div className="settings-login-history">
                          {loginHistory.map((event) => {
                            const { browser, device } = parseUserAgent(event.user_agent);
                            return (
                              <div className="settings-login-row" key={event.id}>
                                <div>
                                  <div className="settings-session-title">
                                    {loginMethodLabels[event.login_method] || event.login_method || 'Вход'}
                                  </div>
                                  <div className="settings-session-meta">
                                    <span>{browser} · {device}</span>
                                    <span>{event.ip_address || 'IP не определён'}</span>
                                  </div>
                                </div>
                                <span className="settings-login-time">{timeAgo(event.created_at)}</span>
                              </div>
                            );
                          })}
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </div>

              {/* ── Карточка 5: Аккаунт (выход + удаление) ────────────────── */}
              <div className="settings-card settings-card--account">
                <div className="settings-card-header">
                  <div className="settings-card-meta">
                    <span className="settings-card-icon">⚙️</span>
                    <div>
                      <div className="settings-card-title">Аккаунт</div>
                      <div className="settings-card-value">Управление сессией и данными</div>
                    </div>
                  </div>
                </div>
                <div className="settings-card-body settings-account-actions">
                  {!isStaffUser ? (
                    <button className="settings-danger-btn" onClick={() => setIsDeleteOpen(true)}>
                    Удалить аккаунт
                    </button>
                  )
                : (
                  <h4>Вам нельзя удалить аккаунт</h4>
                )}
                </div>
              </div>
            </div>
          )}

          {/* === РАЗДЕЛ АДМИНИСТРАТОРА === */}
          {activeTab === 'admin' && isStaffUser && (() => {
            const matchesSearch = (u) =>
              !adminSearch ||
              (u.name || '').toLowerCase().includes(adminSearch.toLowerCase()) ||
              (u.last_name || '').toLowerCase().includes(adminSearch.toLowerCase()) ||
              (u.email || '').toLowerCase().includes(adminSearch.toLowerCase()) ||
              (u.phone || '').includes(adminSearch);

            const matchesFilter = (u) => {
              if (adminFilter === 'active')  return !u.is_blocked && !u.deleted_at;
              if (adminFilter === 'blocked') return !!u.is_blocked && !u.deleted_at;
              if (adminFilter === 'deleted') return !!u.deleted_at;
              if (adminFilter === 'pending') return u.seller_profile && u.role !== 'seller' && !u.deleted_at;
              return true;
            };

            const filtered = adminUsers.filter(u => matchesSearch(u) && matchesFilter(u));

            const counts = {
              all:     adminUsers.length,
              active:  adminUsers.filter(u => !u.is_blocked && !u.deleted_at).length,
              blocked: adminUsers.filter(u => !!u.is_blocked && !u.deleted_at).length,
              deleted: adminUsers.filter(u => !!u.deleted_at).length,
              pending: adminUsers.filter(u => u.seller_profile && u.role !== 'seller' && !u.deleted_at).length,
            };

            return (
              <div className="admin-section">
                <div className="admin-section-header">
                  <h2 className="profile__title">{staffAccess?.isModerator ? 'Раздел модератора' : 'Раздел администратора'}</h2>
                  <a href="/admin/dashboard" className="admin-goto-btn">
                    {panelTitle} →
                  </a>
                </div>

                {/* Filter tabs */}
                <div className="admin-filter-tabs">
                  {[
                    { key: 'all',     label: 'Все',               count: counts.all },
                    { key: 'active',  label: 'Активные',          count: counts.active },
                    { key: 'blocked', label: 'Заблокированные',   count: counts.blocked },
                    { key: 'deleted', label: 'Удалённые',         count: counts.deleted },
                    { key: 'pending', label: 'Ожидают одобрения', count: counts.pending },
                  ].map(tab => (
                    <button
                      key={tab.key}
                      className={`admin-filter-tab ${adminFilter === tab.key ? 'active' : ''}`}
                      onClick={() => setAdminFilter(tab.key)}
                    >
                      {tab.label}
                      {tab.count > 0 && <span className="admin-filter-count">{tab.count}</span>}
                    </button>
                  ))}
                </div>

                {/* Search */}
                <div className="admin-search-bar">
                  <input
                    type="text"
                    className="admin-search-input"
                    placeholder="Поиск по имени, email, телефону..."
                    value={adminSearch}
                    onChange={(e) => setAdminSearch(e.target.value)}
                  />
                  <span className="admin-users-count">{filtered.length} пользователей</span>
                </div>

                {/* User list */}
                <div className="admin-users-list">
                  {filtered.map(u => (
                    <div key={u.id} className={`admin-user-card ${u.is_blocked ? 'admin-user-blocked' : ''} ${u.deleted_at ? 'admin-user-deleted' : ''}`}>
                      <div className="admin-user-avatar">
                        <img src={u.avatar || '/img/profiles/profile.png'} alt="avatar" />
                      </div>

                      <div className="admin-user-info">
                        <div className="admin-user-name">
                          {[u.name, u.last_name].filter(Boolean).join(' ') || 'Без имени'}
                          {u.deleted_at && <span className="admin-deleted-badge">Удалён</span>}
                          {u.is_blocked && !u.deleted_at && <span className="admin-blocked-badge">Заблокирован</span>}
                        </div>
                        <div className="admin-user-contacts">
                          {u.email && <span>{u.email}</span>}
                          {u.phone && <span>+{u.phone}</span>}
                        </div>
                        <div className="admin-user-meta">
                          ID: {u.id}
                          {u.created_at && <span> · {new Date(u.created_at).toLocaleDateString('ru-RU')}</span>}
                        </div>
                      </div>

                      <div className="admin-user-role">
                        <span className={`admin-role-badge role-${u.role}`}>
                          {u.role === 'admin' && 'Админ'}
                          {u.role === 'moderator' && 'Модератор'}
                          {u.role === 'seller' && 'Продавец'}
                          {u.role === 'user' && 'Пользователь'}
                        </span>
                      </div>

                      <div className="admin-user-actions">
                        {canManageUserAsStaff(auth.user, u) && !u.deleted_at && (
                          <>
                            <select
                              className="admin-role-select"
                              value={adminRoleChanges[u.id] ?? u.role}
                              onChange={(e) => {
                                const newRole = e.target.value;
                                setAdminRoleChanges(prev => ({ ...prev, [u.id]: newRole }));
                                router.patch(`/admin/users/${u.id}/role`, { role: newRole }, {
                                  preserveScroll: true,
                                  preserveState: false,
                                  onSuccess: () => setMessage(`Роль пользователя #${u.id} изменена`),
                                });
                              }}
                            >
                              {roleOptionsFor(auth.user).map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                              ))}
                            </select>

                            <button
                              className={`admin-action-btn ${u.is_blocked ? 'admin-btn-unblock' : 'admin-btn-block'}`}
                              onClick={() => {
                                const wasBlocked = !!u.is_blocked;
                                router.put(`/admin/users/${u.id}/block`, {}, {
                                  preserveScroll: true,
                                  preserveState: false,
                                  onSuccess: () => {
                                    setMessage(wasBlocked
                                      ? `Пользователь #${u.id} разблокирован`
                                      : `Пользователь #${u.id} заблокирован`
                                    );
                                  },
                                });
                              }}
                            >
                              {u.is_blocked ? 'Разблокировать' : 'Заблокировать'}
                            </button>

                            <button
                              className="admin-action-btn admin-btn-delete"
                              onClick={() => {
                                if (confirm(`Удалить пользователя ${u.name || '#' + u.id}?`)) {
                                  router.delete(`/admin/users/${u.id}`, {
                                    preserveScroll: true,
                                    preserveState: false,
                                    onSuccess: () => setMessage(`Пользователь #${u.id} удалён`),
                                  });
                                }
                              }}
                            >
                              Удалить
                            </button>

                            <a
                              href={`/admin/users/${u.id}/detail`}
                              className="admin-action-btn admin-btn-view"
                            >
                              Подробнее
                            </a>
                          </>
                        )}
                        {(u.id === auth.user.id || u.id === 1) && (
                          <span className="admin-self-label">Вы / Защищён</span>
                        )}
                      </div>
                    </div>
                  ))}
                  {filtered.length === 0 && (
                    <div className="admin-empty">Нет пользователей по выбранным фильтрам</div>
                  )}
                </div>
              </div>
            );
          })()}

        </div>
      </div>

      {/* ─── МОДАЛКИ ────────────────────────────────────────────────────────── */}
      <PhoneVerificationModal
        isOpen={isPhoneOpen}
        onClose={() => setIsPhoneOpen(false)}
        auth={auth}
        onSuccess={(msg) => setMessage(msg || 'Телефон верифицирован!')}
      />
      <BlockedAccountModal isOpen={isBlockedOpen} onClose={() => setIsBlockedOpen(false)} />
      <DeleteAccountModal
        isOpen={isDeleteOpen}
        onClose={() => setIsDeleteOpen(false)}
        onSuccess={(msg) => setMessage(msg || 'Аккаунт удален')}
      />
    </MainLayout>
  );
}
