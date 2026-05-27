import React, { useEffect, useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import ProductRecommendationsSection from '@/Components/Product/ProductRecommendationsSection';
import { buildProfileHomeActions } from '@/lib/profileHomeActions';

function StatCard({ label, value, onClick }) {
  if (onClick) {
    return (
      <button type="button" className="profile-home-stat is-clickable" onClick={onClick}>
        <span className="profile-home-stat-value">{value}</span>
        <span className="profile-home-stat-label">{label}</span>
      </button>
    );
  }

  return (
    <div className="profile-home-stat">
      <span className="profile-home-stat-value">{value}</span>
      <span className="profile-home-stat-label">{label}</span>
    </div>
  );
}

function ActionSlide({ action, onAction }) {
  return (
    <div className={`profile-home-action-card profile-home-action-card--${action.tone}`}>
      <div className="profile-home-action-body">
        <h3>{action.title}</h3>
        <p>{action.text}</p>
        <button type="button" className="profile-home-action-btn" onClick={() => onAction(action)}>
          {action.cta}
        </button>
      </div>
    </div>
  );
}

export default function ProfileHomeDashboard({
  auth,
  orders = [],
  profileCounts = {},
  LikeProducts = [],
  needsProfileVerification,
  isUserBlocked,
  isStaffUser,
  isPvzUser,
  isSeller,
  sellerProfile,
  sellerRestorePending,
  closedSellerProfile,
  pvzAccess,
  onTabChange,
  onOpenPhoneModal,
  onGoSettings,
}) {
  const [slideIndex, setSlideIndex] = useState(0);
  const [autoplayEpoch, setAutoplayEpoch] = useState(0);

  const SLIDE_AUTO_MS = 6000;

  const actions = useMemo(
    () =>
      buildProfileHomeActions({
        needsProfileVerification,
        isUserBlocked,
        isStaffUser,
        isPvzUser,
        sellerProfile,
        sellerRestorePending,
        closedSellerProfile,
        pvzAccess,
        auth,
        orders,
      }),
    [
      needsProfileVerification,
      isUserBlocked,
      isStaffUser,
      isPvzUser,
      sellerProfile,
      sellerRestorePending,
      closedSellerProfile,
      pvzAccess,
      auth,
      orders,
    ],
  );

  useEffect(() => {
    setSlideIndex(0);
    setAutoplayEpoch((n) => n + 1);
  }, [actions.length, actions[0]?.id]);

  useEffect(() => {
    if (actions.length <= 1) return undefined;
    const timer = window.setInterval(() => {
      setSlideIndex((i) => (i + 1) % actions.length);
    }, SLIDE_AUTO_MS);
    return () => window.clearInterval(timer);
  }, [actions.length, autoplayEpoch, SLIDE_AUTO_MS]);

  const bumpAutoplay = () => setAutoplayEpoch((n) => n + 1);

  const goToSlide = (index) => {
    bumpAutoplay();
    setSlideIndex(index);
  };

  const handleAction = (action) => {
    const { type, tab, section, orderId } = action.action;
    if (type === 'phone-modal') {
      onOpenPhoneModal?.();
      return;
    }
    if (type === 'tab') {
      onTabChange?.(tab);
      return;
    }
    if (type === 'settings') {
      onGoSettings?.(section);
      return;
    }
    if (type === 'order' && orderId) {
      router.visit(`/orders/${orderId}`);
    }
  };

  const currentSlide = actions[slideIndex] ?? null;
  const hasActions = actions.length > 0;
  const greetingName = (() => {
    const name = auth.user.name?.trim();
    const lastName = auth.user.last_name?.trim();
    
    if (name && lastName) return `${name} ${lastName}`;
    return name || lastName || 'Пользователь';
  })();
  
  const goPrevSlide = () => {
    bumpAutoplay();
    setSlideIndex((i) => (i - 1 + actions.length) % actions.length);
  };

  const goNextSlide = () => {
    bumpAutoplay();
    setSlideIndex((i) => (i + 1) % actions.length);
  };

  if (isUserBlocked) {
    return (
      <div className="profile-home">
        <section className="profile-home-header">
          <h2 className="profile__title">Главная</h2>
          <p className="profile-main-blocked-hint">
            Покупки и рекомендации недоступны, пока аккаунт заблокирован. История заказов — во вкладке «Заказы».
          </p>
        </section>
      </div>
    );
  }

  return (
    <div className="profile-home">
      <section className="profile-home-header">
        <h2 className="profile__title">Здравствуйте, {greetingName}!</h2>
      </section>

      <section className="profile-home-stats" aria-label="Краткая сводка">
        <StatCard label="Заказы" value={profileCounts.orders ?? 0} onClick={() => onTabChange?.('orders')} />
        <StatCard label="Избранное" value={profileCounts.favorites ?? 0} onClick={() => onTabChange?.('favorites')} />
        <StatCard label="Отзывы" value={profileCounts.reviews ?? 0} onClick={() => onTabChange?.('reviews')} />
      </section>

      <section className="profile-home-actions" aria-label="Что сделать дальше">
        <div className="profile-home-section-head">
          <h3>Что сделать дальше</h3>
          {actions.length > 1 && (
            <span className="profile-home-section-meta">{slideIndex + 1} из {actions.length}</span>
          )}
        </div>

        {hasActions && currentSlide && (
          <div className="profile-home-action-slider">
            {actions.length > 1 && (
              <button type="button" className="profile-home-slider-nav profile-home-slider-nav--prev" onClick={goPrevSlide} aria-label="Предыдущая подсказка">‹</button>
            )}
            <ActionSlide action={currentSlide} onAction={handleAction} />
            {actions.length > 1 && (
              <button type="button" className="profile-home-slider-nav profile-home-slider-nav--next" onClick={goNextSlide} aria-label="Следующая подсказка">›</button>
            )}
          </div>
        )}

        {hasActions && actions.length > 1 && (
          <div className="profile-home-dots" role="tablist" aria-label="Подсказки">
            {actions.map((action, index) => (
              <button
                key={action.id}
                type="button"
                role="tab"
                aria-selected={index === slideIndex}
                className={`profile-home-dot${index === slideIndex ? ' is-active' : ''}`}
                onClick={() => goToSlide(index)}
                aria-label={action.title}
              />
            ))}
          </div>
        )}

        {!hasActions && (
          <div className="profile-home-done-card">
            <span className="profile-home-done-icon" aria-hidden>✓</span>
            <div>
              <h3>Всё настроено</h3>
              <p>Основные шаги выполнены. Следите за заказами или загляните в каталог за новинками.</p>
            </div>
          </div>
        )}
      </section>

      {orders.length > 0 && (
        <section className="profile-home-orders" aria-label="Активные заказы">
          <div className="profile-home-section-head">
            <h3>Активные заказы</h3>
            <button type="button" className="profile-home-link-btn" onClick={() => onTabChange?.('orders')}>Все заказы</button>
          </div>
          <ul className="profile-home-orders-list">
            {orders.slice(0, 3).map((order) => (
              <li key={order.id}>
                <button type="button" className="profile-home-order-row" onClick={() => router.visit(`/orders/${order.id}`)}>
                  {order.items?.[0] && (
                    <img src={order.items[0].variant?.product?.image || '/img/products/default.png'} alt="" className="profile-home-order-thumb" />
                  )}
                  <span className="profile-home-order-info">
                    <span className="profile-home-order-num">Заказ №{order.number}</span>
                    <span className="profile-home-order-status">
                      {order.frontend_status === 'ready' && 'Можно забрать'}
                      {order.frontend_status === 'shipping' && 'В пути'}
                      {order.frontend_status === 'pending' && 'Обрабатывается'}
                    </span>
                  </span>
                  <span className="profile-home-order-price">{order.total} ₽</span>
                </button>
              </li>
            ))}
          </ul>
        </section>
      )}

      <section className="profile-home-shortcuts" aria-label="Быстрые разделы">
        <h3 className="profile-home-shortcuts-title">Быстрый доступ</h3>
        <div className="profile-home-shortcuts-grid">
          <button type="button" className="profile-home-shortcut" onClick={() => onTabChange?.('orders')}>Заказы</button>
          <button type="button" className="profile-home-shortcut" onClick={() => onTabChange?.('favorites')}>Избранное</button>
          <button type="button" className="profile-home-shortcut" onClick={() => onTabChange?.('pickup')}>Пункт выдачи</button>
          <button type="button" className="profile-home-shortcut" onClick={() => onTabChange?.('messages')}>Сообщения</button>
          <button type="button" className="profile-home-shortcut" onClick={() => onGoSettings?.(null)}>Настройки</button>
          {!isStaffUser && (
            <button type="button" className="profile-home-shortcut" onClick={() => onTabChange?.('company')}>
              {isSeller ? 'Компания' : 'Продавцу'}
            </button>
          )}
        </div>
      </section>

      {LikeProducts && LikeProducts.length > 0 && (
        <section className="profile-home-recommendations">
          <div className="profile-home-section-head">
            <h3>Может понравиться</h3>
            <Link href="/" className="profile-home-link-btn profile-home-link-btn--link">В каталог</Link>
          </div>
          <ProductRecommendationsSection
            products={LikeProducts}
            title=""
            initialCount={4}
            step={4}
            maxCount={8}
            headingClassName=""
          />
        </section>
      )}

      {(!LikeProducts || LikeProducts.length === 0) && (
        <section className="profile-home-recommendations profile-home-recommendations--empty">
          <h3>Может понравиться</h3>
          <p className="profile-home-empty-rec">Пока нет подборки — загляните в каталог.</p>
          <Link href="/" className="shop-now-btn">Перейти в каталог</Link>
        </section>
      )}
    </div>
  );
}
