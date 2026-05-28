import React, { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { router, useForm } from '@inertiajs/react';
import { canManageUserAsStaff, canAssignStaffRoles, isStaff, roleOptionsFor, roleOptionsForTarget } from '@/lib/staffAccess';

import PhoneVerificationModal from '@/Components/Profile/PhoneVerificationModal';
import BlockedAccountModal from '@/Components/Profile/BlockedAccountModal';
import BlockedAccountBanner from '@/Components/Profile/BlockedAccountBanner';
import ConfirmModal from '@/Components/ConfirmModal';
import DeleteAccountModal from '@/Components/Profile/DeleteAccountModal';
import ProfileSidebar from '@/Components/Profile/ProfileSidebar';
import ProfileHelpPanel from '@/Components/Profile/ProfileHelpPanel';
import ProfileHomeDashboard from '@/Components/Profile/ProfileHomeDashboard';
import { compressAvatarImage, formatFileSize } from '@/lib/compressAvatarImage';
import { MESSAGES_FAQ, PICKUP_FAQ, COMPANY_FAQ, PVZ_PARTNER_FAQ } from '@/lib/profileFaqContent';
import { profileMobileTitle } from '@/lib/profileTabLabels';
import { resolveAvatarUrl } from '@/lib/avatarUrl';
import { clearPhoneAuthFlow } from '@/lib/phoneAuthSession';
import MyFavorites from '@/Components/Product/ProductPage';
import Barcode from 'react-barcode';

import '../../../css/profile/style.css';

import CompanyFormModal from '@/Components/Company/CompanyFormModal';
import CompanyProfile from '@/Components/Company/CompanyProfile';

const PROFILE_TABS = ['main', 'orders', 'favorites', 'company', 'settings', 'reviews', 'messages', 'pickup', 'pvz'];

function resolveProfileTabFromSearch(search = '') {
  const tab = new URLSearchParams(search).get('tab');
  return tab && PROFILE_TABS.includes(tab) ? tab : 'main';
}

const PROFILE_MOBILE_MQ = '(max-width: 768px)';

function initialMobileShowMenu() {
  if (typeof window === 'undefined') return true;
  if (!window.matchMedia(PROFILE_MOBILE_MQ).matches) return true;
  return !new URLSearchParams(window.location.search).has('tab');
}

export default function Profile({ auth, products = [], LikeProducts = [], orders = [], myFavorites = [], myReviews = [], profileCounts = {}, sellerProfile = null, closedSellerProfile = null, sellerRestorePending = null, adminUsers = [], pickupPoints = [], userSessions = [], loginHistory = [], currentSessionId = null, accountDeletion = null }) {
  const { staffAccess, pvzAccess } = usePage().props;
  const { url: pageUrl } = usePage();
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
      onSuccess: () => { setShowPickupForm(false); setMessage('Пункт выдачи сохранён'); },
    });
  };

  const isStaffUser = staffAccess?.isStaff ?? isStaff(auth.user);
  const canAssignRoles = staffAccess?.canAssignStaffRoles ?? canAssignStaffRoles(auth.user);
  const panelTitle = staffAccess?.panelTitle ?? 'Панель управления';
  const hasCompanySection = !!(sellerProfile || closedSellerProfile || sellerRestorePending);
  const showCompaniesTab = !isStaffUser && hasCompanySection;
  const [activeTab, setActiveTabState] = useState(() => {
    if (typeof window === 'undefined') return 'main';
    return resolveProfileTabFromSearch(window.location.search);
  });
  const [isMobileProfile, setIsMobileProfile] = useState(false);
  const [mobileShowMenu, setMobileShowMenu] = useState(initialMobileShowMenu);
  const setActiveTab = (tab) => {
    setActiveTabState(tab);
    const next = new URL(window.location.href);
    if (tab === 'main') {
      next.searchParams.delete('tab');
    } else {
      next.searchParams.set('tab', tab);
    }
    const query = next.searchParams.toString();
    window.history.replaceState({}, '', query ? `${next.pathname}?${query}` : next.pathname);
  };
  const [adminSearch, setAdminSearch] = useState('');
  const [adminFilter, setAdminFilter] = useState('all');
  const [adminRoleChanges, setAdminRoleChanges] = useState({});
  // Верификация: открывается когда нет email (телефон — основной идентификатор, уже есть)
  const [isPhoneOpen, setIsPhoneOpen] = useState(false);
  const isUserBlocked = !!auth.user.is_blocked;
  const [isBlockedOpen, setIsBlockedOpen] = useState(isUserBlocked);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [confirmModal, setConfirmModal] = useState(null);
  const [confirmRestoreCompany, setConfirmRestoreCompany] = useState(false);
  const [restorePending, setRestorePending] = useState(false);
  const [message, setMessage] = useState(null);
  const needsProfileVerification =
    (!auth.user.name?.trim() || !auth.user.email || !auth.user.phone) && auth.user.is_active !== 0;
  const isPvzUser = auth.user.role === 'pvz' || pvzAccess?.isPvz;
  const isSeller = auth.user.role === 'seller' && !!sellerProfile;

  useEffect(() => {
    sessionStorage.removeItem('profile_tab');
  }, []);

  useEffect(() => {
    const tab = resolveProfileTabFromSearch(window.location.search);
    if (isStaffUser && tab === 'company') {
      setActiveTab('main');
      return;
    }
    setActiveTabState(tab);
  }, [pageUrl, isStaffUser]);

  useEffect(() => {
    if (isUserBlocked) setIsBlockedOpen(true);
  }, [isUserBlocked]);

  // ─── Настройки: какая карточка открыта для редактирования ───────────────────
  const [editingCard, setEditingCard] = useState(null); // 'personal' | 'contacts' | 'pickup' | 'security' | 'sessions'

  // Форма: личные данные (фото + имя + фамилия + описание)
  const userAvatarUrl = resolveAvatarUrl(auth.user.avatar) || '/img/profiles/profile.png';
  const [avatarPreview, setAvatarPreview] = useState(userAvatarUrl);
  const [avatarUploadError, setAvatarUploadError] = useState(null);
  const [avatarUploadHint, setAvatarUploadHint] = useState(null);
  const { data: profileData, setData: setProfileData, post: postProfile, processing: profileProcessing, errors: profileErrors, reset: resetProfile } = useForm({
    name:        auth.user.name        || '',
    last_name:   auth.user.last_name   || '',
    avatar: null,
  });

  const handleAvatarChange = async (e) => {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;

    setAvatarUploadError(null);
    setAvatarUploadHint('Обрабатываем фото...');

    try {
      const prepared = await compressAvatarImage(file);
      setProfileData('avatar', prepared);
      setAvatarPreview(URL.createObjectURL(prepared));
      setAvatarUploadHint(`Готово: ${formatFileSize(prepared.size)} (JPG, оптимизировано для загрузки)`);
    } catch (err) {
      setProfileData('avatar', null);
      setAvatarUploadError(err.message || 'Не удалось обработать фото.');
      setAvatarUploadHint(null);
    }
  };

  const handleProfileSubmit = (e) => {
    e.preventDefault();
    if (avatarUploadError) {
      setMessage(avatarUploadError);
      return;
    }
    const formData = new FormData();
    formData.append('name',        profileData.name);
    formData.append('last_name',   profileData.last_name);
    if (profileData.avatar) formData.append('avatar', profileData.avatar);
    postProfile('/profile/update', {
      data: formData, forceFormData: true, preserveState: true, preserveScroll: true,
      onSuccess: () => {
        resetProfile('avatar');
        setEditingCard(null);
        setAvatarUploadError(null);
        setAvatarUploadHint(null);
        setMessage('Личные данные обновлены!');
      },
      onError: (errors) => {
        const first = errors.avatar || errors.name || errors.last_name;
        if (first) setMessage(Array.isArray(first) ? first[0] : first);
      },
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
    phone_otp: 'Код по телефону (SMS)',
    phone_otp_notification: 'Код в уведомлениях',
    phone_otp_2fa: 'Телефон + пароль',
    phone_password_reset: 'Сброс пароля',
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

  const goSettingsSection = (section) => {
    setActiveTab('settings');
    if (!section) {
      setEditingCard(null);
      return;
    }
    if (section === 'personal') {
      setProfileData('name', auth.user.name || '');
      setProfileData('last_name', auth.user.last_name || '');
      setAvatarPreview(resolveAvatarUrl(auth.user.avatar) || '/img/profiles/profile.png');
    }
    if (section === 'contacts') {
      setContactData('email', auth.user.email || '');
    }
    setEditingCard(section);
  };

  useEffect(() => {
    const mq = window.matchMedia(PROFILE_MOBILE_MQ);
    const sync = () => setIsMobileProfile(mq.matches);
    sync();
    mq.addEventListener('change', sync);
    return () => mq.removeEventListener('change', sync);
  }, []);

  useEffect(() => {
    if (!isMobileProfile) {
      setMobileShowMenu(true);
      return;
    }
    setMobileShowMenu(!new URLSearchParams(window.location.search).has('tab'));
  }, [pageUrl, isMobileProfile]);

  const profilePageClassName = [
    'profile-page',
    isMobileProfile && mobileShowMenu ? 'is-mobile-menu' : '',
    isMobileProfile && !mobileShowMenu ? 'is-mobile-content' : '',
  ].filter(Boolean).join(' ');

  const mobileSectionTitle = profileMobileTitle(activeTab, activeTab === 'settings' ? editingCard : null);

  const openProfileSection = (tab) => {
    setActiveTab(tab);
    if (tab !== 'settings') setEditingCard(null);
    if (tab === 'pickup') {
      setPickupForm('default_pickup_point_id', auth.user.default_pickup_point_id ?? '');
    }
    if (isMobileProfile) {
      setMobileShowMenu(false);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  const openProfileSettings = (section) => {
    goSettingsSection(section);
    if (isMobileProfile) {
      setMobileShowMenu(false);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  const backToProfileMenu = () => {
    setMobileShowMenu(true);
    window.scrollTo({ top: 0, behavior: 'smooth' });
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
 
  const handleLogout = () => {
    setConfirmModal({
      title: 'Выйти из аккаунта?',
      message: 'Вы будете перенаправлены на главную страницу. Войти снова можно в любой момент.',
      confirmText: 'Выйти',
      variant: 'default',
      onConfirm: () => {
        clearPhoneAuthFlow();
        router.post(route('logout'));
      },
    });
  };

  const kickAllOtherSessions = () => {
    const others = userSessions.filter((session) => session.id !== currentSessionId);
    if (others.length === 0) {
      setMessage('Других активных сеансов нет');
      return;
    }
    setConfirmModal({
      title: 'Завершить все сеансы?',
      message: `Будут закрыты все входы (${others.length}), кроме этого устройства.`,
      confirmText: 'Завершить все',
      variant: 'danger',
      onConfirm: () => {
        router.delete(route('profile.sessions.destroy-others'), {
          preserveScroll: true,
          onSuccess: () => setMessage('Все другие сеансы завершены'),
        });
      },
    });
  };

  const kickOwnSession = (sessionId) => {
    if (sessionId === currentSessionId) {
      setMessage('Нельзя завершить текущее устройство');
      return;
    }
    setConfirmModal({
      title: 'Завершить сессию?',
      message: 'Вход на выбранном устройстве будет закрыт.',
      confirmText: 'Завершить',
      variant: 'danger',
      onConfirm: () => {
        router.delete(route('profile.sessions.destroy', sessionId), {
          preserveScroll: true,
          onSuccess: () => setMessage('Сессия завершена'),
        });
      },
    });
  };

  const runConfirmAction = () => {
    if (!confirmModal?.onConfirm) return;
    confirmModal.onConfirm();
    setConfirmModal(null);
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
  const [showPickupForm, setShowPickupForm] = useState(false);
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
 
      <div className={profilePageClassName}>


        <ProfileSidebar
          auth={auth}
          activeTab={activeTab}
          settingsSection={activeTab === 'settings' ? editingCard : null}
          onTabChange={openProfileSection}
          onSettingsSection={openProfileSettings}
          onLogout={handleLogout}
          isUserBlocked={isUserBlocked}
          formatName={formatName}
          formatPhone={formatPhone}
          showCompaniesTab={showCompaniesTab}
          isStaffUser={isStaffUser}
          staffAccess={staffAccess}
          pvzAccess={pvzAccess}
          isSeller={isSeller}
          counts={profileCounts}
          mobileMenuMode={isMobileProfile && mobileShowMenu}
        />

        {/* ─── RIGHT CONTENT ────────────────────────────────────────────────── */}
        <div className="profile-content">
          {isMobileProfile && !mobileShowMenu && (
            <div className="profile-mobile-topbar">
              <button type="button" className="profile-mobile-back" onClick={backToProfileMenu}>
                <span className="profile-mobile-back-icon" aria-hidden>←</span>
                Меню профиля
              </button>
              <h1 className="profile-mobile-title">{mobileSectionTitle}</h1>
            </div>
          )}

          {isUserBlocked && (
            <BlockedAccountBanner onDetailsClick={() => setIsBlockedOpen(true)} />
          )}

          {needsProfileVerification && !isUserBlocked && activeTab !== 'main' && (
            <div className="verify-banner">
              <div className="verify-banner-content">
                <div className="verify-banner-text">
                  <h2>Заполните профиль</h2>
                  <p>Укажите имя, email и подтвердите телефон, чтобы открыть все возможности платформы:</p>
                  <ul>
                    <li>Оформление заказов</li>
                    <li>Заявка на пункт выдачи (ПВЗ)</li>
                    <li>Регистрация компании продавца</li>
                    <li>Получение и использование промокодов</li>
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
            <ProfileHomeDashboard
              auth={auth}
              orders={orders}
              profileCounts={profileCounts}
              LikeProducts={LikeProducts}
              needsProfileVerification={needsProfileVerification}
              isUserBlocked={isUserBlocked}
              isStaffUser={isStaffUser}
              isPvzUser={isPvzUser}
              isSeller={isSeller}
              sellerProfile={sellerProfile}
              sellerRestorePending={sellerRestorePending}
              closedSellerProfile={closedSellerProfile}
              pvzAccess={pvzAccess}
              onTabChange={openProfileSection}
              onOpenPhoneModal={() => setIsPhoneOpen(true)}
              onGoSettings={openProfileSettings}
            />
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
                              onError={(e) => {
                                e.currentTarget.onerror = null;
                                e.currentTarget.src = '/img/products/default.png';
                              }}
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

          {/* === МОИ ОТЗЫВЫ === */}
          {activeTab === 'reviews' && (
            <div className="profile-reviews-section">
              <section className="category-header">
                <h2 className="profile__title">Мои отзывы</h2>
                <p className="profile-reviews-hint">
                  Здесь все отзывы, которые вы оставили на товары. Новый отзыв можно написать на странице заказа после получения.
                </p>
              </section>
              {myReviews && myReviews.length > 0 ? (
                <ul className="profile-reviews-list">
                  {myReviews.map((review) => (
                    <li key={review.id} className="profile-review-card">
                      <Link
                        href={review.product?.id ? `/product/${review.product.id}` : '#'}
                        className="profile-review-product"
                      >
                        <img
                          src={review.product?.image || '/img/products/default.png'}
                          alt=""
                          className="profile-review-image"
                        />
                        <div className="profile-review-product-meta">
                          <div className="profile-review-title">
                            {review.product?.title || 'Товар'}
                          </div>
                          <div className="profile-review-stars" aria-label={`Оценка ${review.rating}`}>
                            {'★'.repeat(review.rating)}{'☆'.repeat(5 - review.rating)}
                          </div>
                        </div>
                      </Link>
                      <div className="profile-review-body">
                        {review.comment ? (
                          <p className="profile-review-comment">{review.comment}</p>
                        ) : (
                          <p className="profile-review-comment profile-review-comment--empty">Без текста</p>
                        )}
                        {review.moderation_status === 'rejected' && review.moderation_comment && (
                          <p className="profile-review-rejection">
                            <span className="profile-review-rejection-label">Причина отклонения:</span>
                            {review.moderation_comment}
                          </p>
                        )}
                        <div className="profile-review-footer">
                          <span
                            className={`profile-review-status ${
                              review.moderation_status === 'published'
                                ? 'is-published'
                                : review.moderation_status === 'rejected'
                                  ? 'is-rejected'
                                  : 'is-pending'
                            }`}
                          >
                            {review.moderation_status === 'published'
                              ? 'Опубликован'
                              : review.moderation_status === 'rejected'
                                ? 'Отклонён'
                                : 'На модерации'}
                          </span>
                          {review.created_at && (
                            <time dateTime={review.created_at}>
                              {new Date(review.created_at).toLocaleDateString('ru-RU')}
                            </time>
                          )}
                          {review.order_id && (
                            <Link href={`/orders/${review.order_id}`} className="profile-review-order-link">
                              К заказу
                            </Link>
                          )}
                        </div>
                      </div>
                    </li>
                  ))}
                </ul>
              ) : (
                <div className="empty-favorite products profile-reviews-empty">
                  <p>Вы ещё не оставляли отзывов</p>
                  <p className="profile-reviews-hint">После получения заказа откройте его и оцените товары — отзыв появится здесь.</p>
                  <Link href="/orders" className="shop-now-btn">Перейти к заказам</Link>
                </div>
              )}
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

          {/* === СООБЩЕНИЯ === */}
          {activeTab === 'messages' && (
            <div className="profile-service-section">
              <ProfileHelpPanel
                title="Сообщения и поддержка"
                intro="Сначала загляните в ответы на частые вопросы — возможно, ответ уже есть. Если нет — откройте чат с поддержкой."
                items={MESSAGES_FAQ}
              >
                <p className="profile-help-action-hint">
                  В чате можно уточнить статус заказа, оплату, возврат и другие вопросы. Приложите номер заказа или скриншот.
                </p>
                <div className="profile-help-action-buttons">
                  <button
                    type="button"
                    className="profile-help-btn profile-help-btn--primary"
                    onClick={() => router.visit('/messages')}
                  >
                    Открыть чат с поддержкой
                  </button>
                  <Link href="/contacts" className="profile-help-btn profile-help-btn--outline">
                    Контакты
                  </Link>
                </div>
              </ProfileHelpPanel>
            </div>
          )}

          {/* === ПУНКТ ВЫДАЧИ === */}
          {activeTab === 'pickup' && !auth.user.is_blocked && (
            <div className="profile-service-section">
              <ProfileHelpPanel
                title="Пункт выдачи"
                intro="Прочитайте, как работает выдача заказов, затем укажите удобный пункт по умолчанию — его можно сменить при оформлении."
                items={PICKUP_FAQ}
              >
                {!showPickupForm ? (
                  <>
                    <div className="profile-pickup-summary">
                      <span className="profile-pickup-summary-label">Сейчас выбрано:</span>
                      <span className="profile-pickup-summary-value">
                        {pickupForm.default_pickup_point_id
                          ? (pickupPoints.find((p) => p.id === Number(pickupForm.default_pickup_point_id))?.label ?? 'Пункт выдачи')
                          : 'Не выбран — укажите ниже'}
                      </span>
                    </div>
                    <button
                      type="button"
                      className="profile-help-btn profile-help-btn--primary"
                      onClick={() => {
                        setPickupForm('default_pickup_point_id', auth.user.default_pickup_point_id ?? '');
                        setShowPickupForm(true);
                      }}
                    >
                      {pickupForm.default_pickup_point_id ? 'Изменить пункт выдачи' : 'Выбрать пункт выдачи'}
                    </button>
                  </>
                ) : (
                  <div className="profile-pickup-form-card settings-card">
                    <div className="settings-card-header">
                      <div className="settings-card-meta">
                        <span className="settings-card-icon">📦</span>
                        <div>
                          <div className="settings-card-title">ПВЗ по умолчанию</div>
                          <div className="settings-card-value">Выберите пункт из списка</div>
                        </div>
                      </div>
                      <button
                        type="button"
                        className="settings-cancel-btn"
                        onClick={() => {
                          setPickupForm('default_pickup_point_id', auth.user.default_pickup_point_id ?? '');
                          setShowPickupForm(false);
                        }}
                      >
                        Свернуть
                      </button>
                    </div>
                    <form className="settings-card-body" onSubmit={handlePickupSubmit}>
                      <div className="settings-field">
                        <label className="settings-label">Пункт выдачи</label>
                        <select
                          className="settings-input"
                          value={pickupForm.default_pickup_point_id === '' || pickupForm.default_pickup_point_id == null ? '' : String(pickupForm.default_pickup_point_id)}
                          onChange={(e) => setPickupForm('default_pickup_point_id', e.target.value === '' ? '' : Number(e.target.value))}
                        >
                          <option value="">— выберите пункт —</option>
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
                          Без пункта выдачи оформить заказ нельзя. Можно указать другой при каждом заказе в корзине.
                        </p>
                      </div>
                      <div className="settings-card-actions">
                        <button type="submit" className="settings-save-btn" disabled={pickupProcessing}>
                          {pickupProcessing ? 'Сохранение...' : 'Сохранить'}
                        </button>
                      </div>
                    </form>
                  </div>
                )}
              </ProfileHelpPanel>
            </div>
          )}

          {/* === ПАРТНЁРСТВО ПВЗ === */}
          {activeTab === 'pvz' && !auth.user.is_blocked && !pvzAccess?.isPvz && (
            <div className="profile-service-section">
              <ProfileHelpPanel
                title="Партнёрство: пункт выдачи"
                intro="Откройте официальный пункт выдачи ALVORA. Изучите условия ниже, затем перейдите к подаче заявки."
                items={PVZ_PARTNER_FAQ}
              >
                {needsProfileVerification ? (
                  <p className="profile-help-action-hint">
                    Сначала заполните профиль (имя, email, телефон) — без этого заявку подать нельзя.
                  </p>
                ) : (
                  <p className="profile-help-action-hint">
                    Анкета займёт несколько минут. После проверки откроется панель оператора ПВЗ.
                  </p>
                )}
                <div className="profile-help-action-buttons">
                  <button
                    type="button"
                    className="profile-help-btn profile-help-btn--primary"
                    disabled={needsProfileVerification}
                    onClick={() => router.visit('/pickup/partner')}
                  >
                    Подать заявку на пункт выдачи
                  </button>
                  {needsProfileVerification && (
                    <button
                      type="button"
                      className="profile-help-btn profile-help-btn--outline"
                      onClick={() => setIsPhoneOpen(true)}
                    >
                      Заполнить профиль
                    </button>
                  )}
                </div>
              </ProfileHelpPanel>
            </div>
          )}

          {/* === КОМПАНИИ === */}
          {activeTab === 'company' && !auth.user.is_blocked && !isStaffUser && (
            <div className="company-page">
              <ProfileHelpPanel
                title="Компания продавца"
                intro="Перед регистрацией магазина ознакомьтесь с ответами — так заявка пройдёт быстрее."
                items={COMPANY_FAQ}
              />

              <div className="company-mobile-unavailable">
                <h3>Раздел продавца доступен с компьютера</h3>
                <p>Для добавления компании и работы с панелью продавца нужен широкий экран. Откройте этот раздел на ноутбуке или ПК.</p>
              </div>

              {isPvzUser && (
                <div className="company-empty-state" style={{ marginBottom: 24, textAlign: 'left', maxWidth: 640 }}>
                  <div className="empty-state-icon">📍</div>
                  <h2>Сейчас у вас роль оператора ПВЗ</h2>
                  <p>
                    На одном аккаунте нельзя одновременно быть продавцом и оператором пункта выдачи.
                    Чтобы открыть компанию продавца, сначала завершите работу ПВЗ в{' '}
                    <Link href="/pvz/settings">настройках панели ПВЗ</Link> (закрытие с подтверждением администратора),
                    либо используйте отдельный аккаунт для продаж.
                  </p>
                  <Link href="/pvz" className="add-company-btn" style={{ display: 'inline-block', textDecoration: 'none' }}>
                    Перейти в панель ПВЗ
                  </Link>
                </div>
              )}

              {!isPvzUser && !sellerProfile && sellerRestorePending && !showCompanyForm && (
                <div className="company-empty-state company-restore-state">
                  <div className="empty-state-icon">⏳</div>
                  <h2>Заявка на восстановление отправлена</h2>
                  <p>
                    Магазин <strong>{sellerRestorePending.shop_name}</strong>
                    {sellerRestorePending.inn ? ` (ИНН ${sellerRestorePending.inn})` : ''} ожидает решения администратора в разделе модерации.
                  </p>
                  {sellerRestorePending.requested_at && (
                    <p className="hint">Отправлена: {new Date(sellerRestorePending.requested_at).toLocaleString('ru-RU')}</p>
                  )}
                </div>
              )}

              {!isPvzUser && !sellerProfile && !sellerRestorePending && closedSellerProfile && !showCompanyForm && (
                <div className="company-empty-state company-restore-state">
                  <div className="empty-state-icon">🏢</div>
                  <h2>Восстановить компанию</h2>
                  <p>
                    Ранее у вас был магазин <strong>{closedSellerProfile.shop_name}</strong>
                    {closedSellerProfile.inn ? ` (ИНН ${closedSellerProfile.inn})` : ''}.
                    После отправки заявки администратор проверит её в модерации.
                  </p>
                  {closedSellerProfile.closed_at && (
                    <p className="hint">Закрыта: {new Date(closedSellerProfile.closed_at).toLocaleString('ru-RU')}</p>
                  )}
                  <button type="button" className="add-company-btn" onClick={() => setConfirmRestoreCompany(true)}>
                    Подать заявку на восстановление
                  </button>
                </div>
              )}

              {!isPvzUser && !sellerProfile && !closedSellerProfile && !showCompanyForm && (
                <div className="company-empty-state profile-company-cta">
                  <div className="empty-state-icon">🏢</div>
                  <h2>Готовы открыть магазин?</h2>
                  <p>После прочтения вопросов выше нажмите кнопку и заполните данные компании.</p>
                  <button type="button" className="add-company-btn" onClick={() => setShowCompanyForm(true)}>
                    + Добавить компанию
                  </button>
                </div>
              )}

              {!isPvzUser && showCompanyForm && (
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

              {sellerProfile && isPvzUser && (
                <div className="company-card" style={{ opacity: 0.85 }}>
                  <div className="company-card-header">
                    <div className="company-title">
                      <h2>{sellerProfile.shop_name}</h2>
                      <p className="company-inn">ИНН: {sellerProfile.inn}</p>
                      <span className="status-badge pending">Приостановлено — активна роль ПВЗ</span>
                    </div>
                  </div>
                  <p className="hint" style={{ padding: '0 20px 20px' }}>
                    Данные компании сохранены, но панель продавца недоступна, пока вы оператор пункта выдачи.
                  </p>
                </div>
              )}

              {sellerProfile && !isPvzUser && (
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
                        <div className="stat-item"><span className="stat-value">{sellerProfile.rating}</span><span className="stat-label">Рейтинг</span></div>
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
                          <img src={userAvatarUrl} className="settings-avatar-thumb" alt="avatar" />
                          <span>{[auth.user.name, auth.user.last_name].filter(Boolean).join(' ') || 'Не заполнено'}</span>
                        </div>
                      )}
                    </div>
                  </div>
                  {editingCard !== 'personal' ? (
                    <button className="settings-edit-btn" onClick={() => {
                      setProfileData('name',        auth.user.name        || '');
                      setProfileData('last_name',   auth.user.last_name   || '');
                      setAvatarPreview(resolveAvatarUrl(auth.user.avatar) || '/img/profiles/profile.png');
                      setEditingCard('personal');
                    }}>Изменить</button>
                  ) : (
                    <button className="settings-cancel-btn" onClick={() => { setEditingCard(null); resetProfile('avatar'); setAvatarPreview(userAvatarUrl); }}>Отмена</button>
                  )}
                </div>

                {editingCard === 'personal' && (
                  <form className="settings-card-body" onSubmit={handleProfileSubmit}>
                    <div className="settings-avatar-upload-row">
                      <img src={avatarPreview} className="settings-avatar-large" alt="Превью" />
                      <div className="settings-avatar-upload-meta">
                        <label className="settings-avatar-upload-btn">
                          Загрузить фото
                          <input type="file" accept="image/*" onChange={handleAvatarChange} style={{ display: 'none' }} />
                        </label>
                        <p className="settings-hint-text">JPG, PNG или WEBP до 10 МБ. Фото с телефона сжимается автоматически.</p>
                        {avatarUploadHint && <p className="settings-hint-text settings-hint-text--ok">{avatarUploadHint}</p>}
                        {(avatarUploadError || profileErrors.avatar) && (
                          <p className="settings-error">{avatarUploadError || profileErrors.avatar}</p>
                        )}
                      </div>
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

              {/* ── Пункт выдачи (ссылка на раздел) ───────────────────────── */}
              <div className="settings-card settings-card--link">
                <div className="settings-card-header">
                  <div className="settings-card-meta">
                    <span className="settings-card-icon">📦</span>
                    <div>
                      <div className="settings-card-title">Пункт выдачи</div>
                      <div className="settings-card-value">
                        {pickupForm.default_pickup_point_id
                          ? (pickupPoints.find((p) => p.id === Number(pickupForm.default_pickup_point_id))?.label ?? 'Выбран')
                          : 'Не выбран'}
                      </div>
                    </div>
                  </div>
                  <button
                    type="button"
                    className="settings-edit-btn"
                    onClick={() => {
                      setActiveTab('pickup');
                      setShowPickupForm(true);
                    }}
                  >
                    Открыть раздел
                  </button>
                </div>
              </div>

              {/* ── Карточка 3: Безопасность ─────────────────────────────────── */}
              <div className="settings-card">
                <div className="settings-card-header">
                  <div className="settings-card-meta">
                    <span className="settings-card-icon">🔒</span>
                    <div>
                      <div className="settings-card-title">Безопасность</div>
                      {editingCard !== 'security' && (
                        <div className="settings-card-value">
                          {auth.user?.newPassw ? 'Установить пароль (2FA)' : 'Двухэтапная аутентификация'}
                        </div>
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
                    <p className="settings-hint-text">
                      {auth.user?.newPassw
                        ? 'После установки пароля при каждом входе потребуется код с телефона и пароль — это двухэтапная аутентификация (2FA).'
                        : 'Пароль включён: при входе после кода с телефона нужно ввести пароль (двухэтапная аутентификация).'}
                    </p>
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
                        <div className="settings-session-head-actions">
                          <span>{onlineUserSessions.length} онлайн из {userSessions.length}</span>
                          {userSessions.some((session) => session.id !== currentSessionId) && (
                            <button
                              type="button"
                              className="settings-danger-btn settings-session-kick-all"
                              onClick={kickAllOtherSessions}
                            >
                              Завершить все, кроме этого
                            </button>
                          )}
                        </div>
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
                  {isStaffUser ? (
                    <p className="settings-hint-text">Аккаунты администратора и модератора нельзя удалить через профиль.</p>
                  ) : accountDeletion?.can_delete ? (
                    <button type="button" className="settings-danger-btn" onClick={() => setIsDeleteOpen(true)}>
                      Удалить аккаунт
                    </button>
                  ) : (
                    <div className="settings-delete-blockers">
                      <p className="settings-hint-text settings-hint-text--warn">
                        Удаление аккаунта недоступно, пока не выполнены условия:
                      </p>
                      <ul className="settings-delete-blockers__list">
                        {(accountDeletion?.blockers ?? []).map((b) => (
                          <li key={b.code}>
                            <span>{b.message}</span>
                            {b.action_url && (
                              <a href={b.action_url} className="settings-delete-blockers__link">
                                {b.action_label || 'Перейти'}
                              </a>
                            )}
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* inline admin removed — use «Панель администратора» in menu */}
          {false && activeTab === 'admin' && isStaffUser && (() => {
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

                <div className="admin-mobile-unavailable">
                  <h3>Админ-панель доступна с компьютера</h3>
                  <p>Для управления пользователями нужен широкий экран. Откройте этот раздел на ноутбуке или ПК.</p>
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
                          {u.role === 'pvz' && 'Оператор ПВЗ'}
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
                              {roleOptionsForTarget(auth.user, u).map((opt) => (
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
                                setConfirmModal({
                                  title: 'Удалить пользователя?',
                                  message: `Аккаунт ${u.name || '#' + u.id} будет деактивирован.`,
                                  confirmText: 'Удалить',
                                  variant: 'danger',
                                  onConfirm: () => {
                                    router.delete(`/admin/users/${u.id}`, {
                                      preserveScroll: true,
                                      preserveState: false,
                                      onSuccess: () => setMessage(`Пользователь #${u.id} удалён`),
                                    });
                                  },
                                });
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
      <ConfirmModal
        isOpen={!!confirmModal}
        onClose={() => setConfirmModal(null)}
        onConfirm={runConfirmAction}
        title={confirmModal?.title}
        message={confirmModal?.message}
        confirmText={confirmModal?.confirmText}
        variant={confirmModal?.variant}
      />
      <ConfirmModal
        isOpen={confirmRestoreCompany}
        onClose={() => setConfirmRestoreCompany(false)}
        onConfirm={() => {
          setRestorePending(true);
          router.post(route('seller-profile.restore'), { confirmed: true }, {
            onFinish: () => {
              setRestorePending(false);
              setConfirmRestoreCompany(false);
            },
          });
        }}
        title="Подать заявку на восстановление?"
        message={`Заявка на восстановление магазина «${closedSellerProfile?.shop_name || ''}» будет отправлена администратору. После одобрения вы снова станете продавцом; товары останутся сняты с витрины — их нужно включить отдельно.`}
        confirmText="Отправить заявку"
        cancelText="Отмена"
        variant="default"
        processing={restorePending}
      />
      <DeleteAccountModal
        isOpen={isDeleteOpen}
        onClose={() => setIsDeleteOpen(false)}
        onError={(msg) => {
          setIsDeleteOpen(false);
          setMessage(msg);
        }}
      />
    </MainLayout>
  );
}
