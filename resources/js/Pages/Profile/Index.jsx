import React, { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { router, useForm } from '@inertiajs/react';

// Модалки (скопируйте эти компоненты из старого профиля)
import ProfileEditModal from '@/Components/Profile/ProfileEditModal';
import PhoneVerificationModal from '@/Components/Profile/PhoneVerificationModal';
import BlockedAccountModal from '@/Components/Profile/BlockedAccountModal';
import DeleteAccountModal from '@/Components/Profile/DeleteAccountModal';
import MyFavorites from '@/Components/Product/ProductPage';
import Recommendations from '@/Components/Product/ProductPage';
import Barcode from 'react-barcode';

import '../../../css/profile/style.css';

import CompanyFormModal from '@/Components/Company/CompanyFormModal';
import CompanyProfile from '@/Components/Company/CompanyProfile';

export default function Profile({ auth, products = [], LikeProducts = [], orders = [], myFavorites = [] }) {
  const [barcodeOpen, setBarcodeOpen] = useState(false);
  const [selectedOrder, setSelectedOrder] = useState(null);

  const getDailyCode = () => {
    const today = new Date().toDateString();
    const saved = localStorage.getItem('pickup_code');
    if (saved) {
      const parsed = JSON.parse(saved);
      if (parsed.date === today) return parsed.code;
    }

    const newCode = `${Math.floor(1000 + Math.random() * 9000)} ${Math.floor(1000 + Math.random() * 9000)}`;

    localStorage.setItem('pickup_code', JSON.stringify({
      date: today,
      code: newCode
    }));

    return newCode;
  };
  const code = getDailyCode();
  const [activeTab, setActiveTab] = useState('main');
  // Используем хук состояния для хранения количества отображаемых элементов
  const [displayCount, setDisplayCount] = useState(8);
  // Функция для увеличения количества отображаемых элементов
  const showMore = () => {
    // Увеличиваем количество на 10, но не больше, чем длина массива LikeProducts
    setDisplayCount(prevCount => Math.min(prevCount + 8, LikeProducts.length));
  };

  // Состояния модалок
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [isPhoneOpen, setIsPhoneOpen] = useState(false);
  const [isBlockedOpen, setIsBlockedOpen] = useState(auth.user.is_blocked === 1);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [message, setMessage] = useState(null);

  // Показываем модалку блокировки если аккаунт заблокирован
  useEffect(() => {
    if (auth.user.is_blocked === 1) {
      setIsBlockedOpen(true);
    }
  }, [auth.user.is_blocked]);

  // Форматирование имени (обрезаем если длинное)
  const formatName = (name) => {
    if (!name) return 'Пользователь';
    return name.length > 20 ? name.slice(0, 20) + '...' : name;
  };
  // ты нажатии на ссылке страницы листается до верха
  useEffect(() => {
    // Прокручиваем к началу контента при смене вкладки
    const contentElement = document.querySelector('body');
    if (contentElement) {
      contentElement.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }
  }, [activeTab]); // Срабатывает при каждом изменении activeTab




  // Добавьте в начало компонента:
  const [sellerProfile, setSellerProfile] = useState(auth.user?.seller_profile || null);
  const [showCompanyForm, setShowCompanyForm] = useState(false);

  // Форма для создания компании
  const { data, setData, post, processing, errors, reset } = useForm({
    inn: '',
    shop_name: '',
    legal_address: '',
    pickup_address: '',
    description: '',
    working_hours: {
      mon: { enabled: true, from: '09:00', to: '18:00' },
      tue: { enabled: true, from: '09:00', to: '18:00' },
      wed: { enabled: true, from: '09:00', to: '18:00' },
      thu: { enabled: true, from: '09:00', to: '18:00' },
      fri: { enabled: true, from: '09:00', to: '18:00' },
      sat: { enabled: false, from: '10:00', to: '15:00' },
      sun: { enabled: false, from: '10:00', to: '15:00' },
    },
  });

  // Дни недели для отображения
  const weekDays = {
    mon: 'Понедельник',
    tue: 'Вторник',
    wed: 'Среда',
    thu: 'Четверг',
    fri: 'Пятница',
    sat: 'Суббота',
    sun: 'Воскресенье',
  };

  const handleSubmitCompany = (e) => {
    e.preventDefault();

    // Преобразуем working_hours в JSON строку
    const formData = {
      ...data,
      working_hours: JSON.stringify(data.working_hours),
    };

    post(route('seller-profile.store'), {
      data: formData,
      onSuccess: () => {
        setShowCompanyForm(false);
        reset();
        router.reload();
      },
    });
  };

  const handleWorkingHoursChange = (day, field, value) => {
    setData('working_hours', {
      ...data.working_hours,
      [day]: {
        ...data.working_hours[day],
        [field]: value,
      },
    });
  };
  return (
    <MainLayout>
      <Head title="Профиль" />

      <div className="profile-page">

        {/* Уведомление */}
        {message && (
          <div className="alert" onClick={() => setMessage(null)}>
            {message}
          </div>
        )}

        {/* LEFT MENU */}
        <div className="profile-sidebar">

          <div className="profile-user">
            <img
              src={auth.user.avatar || '/img/profiles/profile.png'}
              className="avatar"
              alt="avatar"
            />

            <div className="name" title={auth.user.name}>
              {formatName(auth.user.name)}
              {auth.user.phone && (
                <img src="/img/profiles/check.png" alt="Верифицирован" className="verify-icon" />
              )}
            </div>

            {/* Кнопка Редактировать профиль */}
            <div
              className="edit "
              onClick={() => setIsEditOpen(true)}
            >
              Изменить профиль
            </div>


          </div>

          <div className="profile-menu">
            {/* Кнопка верификации (если нет телефона) */}
            {!auth.user.phone && auth.user.is_active !== 0 && (
              <div
                className="verify"
                onClick={() => setIsPhoneOpen(true)}
              >
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

            <div onClick={() => setActiveTab('company')} className={activeTab === 'company' ? 'active' : ''}>
              Мои компании
            </div>

            {/* Кнопка удаления аккаунта (скрыта для админа) */}
            {auth.user?.role !== 'admin' && (
              <div
                className="danger"
                onClick={() => setIsDeleteOpen(true)}
              >
                Удалить аккаунт
              </div>
            )}

          </div>
        </div>


        {/* RIGHT CONTENT */}
        <div className="profile-content">
          {/* Блок для неверифицированных пользователей */}
          {!auth.user.phone && auth.user.is_active !== 0 && (
            <div className="verify-banner">
              <div className="verify-banner-content">
                <div className="verify-banner-text">
                  <h2>Получите полный доступ</h2>
                  <p>Пройдите верификацию, чтобы открыть все возможности платформы:</p>
                  <ul>
                    <li>Оформление заказов</li>
                    <li>Покупка товаров со скидкой</li>
                    <li>Получение и использование промокодов</li>
                    <li>Участие в эксклюзивных акциях</li>
                  </ul>
                </div>
                <div className="verify-banner-button">
                  <button
                    className="verify-green-btn"
                    onClick={() => setIsPhoneOpen(true)}
                  >
                    Пройти верификацию
                  </button>
                </div>
              </div>
            </div>
          )}
          {/* === ГЛАВНАЯ === */}
          {activeTab === 'main' && (

            <div className="Profile__recommendated">
              {/* Рекомендации */}
              {LikeProducts && LikeProducts.length > 0 && (
                <>
                  <section className="category-header">
                    <h2>Рекомендации для вас</h2>

                  </section>
                  {/* Передаём только первые `displayCount` элементов */}
                  <Recommendations
                    products={LikeProducts.slice(0, displayCount)}
                  />
                  {/* Кнопка показывается только если есть еще элементы для отображения */}
                  {displayCount < LikeProducts.length && (
                    <button className="showMore__btn" onClick={showMore}>Показать еще</button>
                  )}
                </>
              )}
            </div>
          )}

          {activeTab === 'orders' && (
            <div className="orders-section">
              <div className="orders-header">
                <h2>Мои заказы</h2>
              </div>

              {orders && orders.length > 0 ? (
                <>
                  <div className="orders-list">
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
                              src={order.items[0].variant?.product?.image || '/img/products/default.jpg'}
                              className="order-image"
                            />
                          )}
                          <div className="order-details">
                            <h3>Заказ #{order.number}</h3>
                            <p className="order-price">{order.total} ₽</p>
                            {/* СТАТУС ДОЛЖЕН ПОКАЗЫВАТЬСЯ КРАСИВО */}
                            <div className={`order-status-badge status-${order.frontend_status}`}>
                              {order.frontend_status === 'ready' && 'Можно забрать'}
                              {order.frontend_status === 'shipping' && 'В пути'}
                              {order.frontend_status === 'pending' && 'Обрабатывается'}
                            </div>
                          </div>
                        </div>

                        <div className="order-actions">
                          <div
                            className="qr-code-box"
                            onClick={(e) => {
                              e.stopPropagation();
                              setSelectedOrder(order);
                            }}
                          >
                            <Barcode
                              value={order.order_code}
                              width={2}
                              height={40}
                            />
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>

                  {/* КНОПКА "ВСЕ ЗАКАЗЫ" - ВЕРНУЛ */}
                  <div className="all-orders-btn-container">
                    <Link href="/orders" className="all-orders-btn">
                      Все заказы
                    </Link>
                  </div>
                </>
              ) : (
                <div className="empty-orders">
                  <p>У вас пока нет заказов</p>
                  <Link href="/" className="shop-now-btn">
                    Перейти в каталог
                  </Link>
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
                  <Barcode
                    value={selectedOrder.order_code}
                    width={3}
                    height={100}
                  />
                  <p className="pickup-code-text">Код: {selectedOrder.order_code}</p>
                  <p className="pickup-instruction">Покажите код при получении</p>
                </div>
              </div>
            </div>
          )}
          {/* === ИЗБРАННОЕ === */}
          {activeTab === 'favorites' && (
            <div className="Profile__recommendated">
              {/* Рекомендации */}
              {myFavorites && myFavorites.length > 0 && (
                <>
                  <section className="category-header">
                    <h2>Мои избранные товары</h2>

                  </section>
                  {/* Передаём только первые `displayCount` элементов */}
                  <MyFavorites
                    products={myFavorites.slice(0, 4)}
                  />
                  <Link href="/favorites" className="showMore__btn">
                    Показать еще
                  </Link>
                </>
              )}
            </div>
          )}

          {/* === КОМПАНИИ === */}

          {activeTab === 'company' && (
            <div className="company-page">
              {/* Если нет компании - показываем форму создания */}
              {!sellerProfile && !showCompanyForm && (
                <div className="company-empty-state">
                  <div className="empty-state-icon">🏢</div>
                  <h2>Добавьте компанию</h2>
                  <p>Укажите ИНН компании, которая будет оплачивать заказы</p>
                  <button
                    className="add-company-btn"
                    onClick={() => setShowCompanyForm(true)}
                  >
                    + Добавить компанию
                  </button>

                  <div className="company-benefits">
                    <div className="benefit-item">
                      <div className="benefit-icon">⚡</div>
                      <h4>Регистрация за минуту</h4>
                      <p>Потребуется только ИНН</p>
                    </div>
                    <div className="benefit-item">
                      <div className="benefit-icon">💳</div>
                      <h4>Оплата по счёту</h4>
                      <p>Возмещение НДС до 20%</p>
                    </div>
                    <div className="benefit-item">
                      <div className="benefit-icon">📄</div>
                      <h4>Документы онлайн</h4>
                      <p>В ЭДО или личном кабинете</p>
                    </div>
                  </div>
                </div>
              )}

              {/* Форма создания компании */}
              {showCompanyForm && (
                <div className="company-form-container">
                  <div className="form-header">
                    <h2>Добавьте компанию</h2>
                    <button
                      className="back-btn"
                      onClick={() => setShowCompanyForm(false)}
                    >
                      ← Назад
                    </button>
                  </div>

                  <form onSubmit={handleSubmitCompany} className="company-form">
                    {/* ИНН */}
                    <div className="form-group">
                      <label>ИНН компании <span className="required">*</span></label>
                      <input
                        type="text"
                        value={data.inn}
                        onChange={(e) => setData('inn', e.target.value)}
                        placeholder="Введите 10 или 12 цифр"
                        maxLength={12}
                        className={errors.inn ? 'error' : ''}
                      />
                      {errors.inn && <span className="error-text">{errors.inn}</span>}
                      <p className="hint">Укажите ИНН компании, которая будет оплачивать заказы</p>
                    </div>

                    {/* Название магазина */}
                    <div className="form-group">
                      <label>Название магазина <span className="required">*</span></label>
                      <input
                        type="text"
                        value={data.shop_name}
                        onChange={(e) => setData('shop_name', e.target.value)}
                        placeholder="Например: ООО 'Ромашка'"
                        className={errors.shop_name ? 'error' : ''}
                      />
                      {errors.shop_name && <span className="error-text">{errors.shop_name}</span>}
                    </div>

                    {/* Юридический адрес */}
                    <div className="form-group">
                      <label>Юридический адрес</label>
                      <input
                        type="text"
                        value={data.legal_address}
                        onChange={(e) => setData('legal_address', e.target.value)}
                        placeholder="Город, улица, дом, офис"
                      />
                    </div>

                    {/* Адрес самовывоза */}
                    <div className="form-group">
                      <label>Адрес самовывоза <span className="required">*</span></label>
                      <input
                        type="text"
                        value={data.pickup_address}
                        onChange={(e) => setData('pickup_address', e.target.value)}
                        placeholder="Адрес, где покупатели могут забрать товар"
                        className={errors.pickup_address ? 'error' : ''}
                      />
                      {errors.pickup_address && <span className="error-text">{errors.pickup_address}</span>}
                    </div>

                    {/* Описание */}
                    <div className="form-group">
                      <label>Описание магазина</label>
                      <textarea
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        placeholder="Расскажите о вашей компании..."
                        rows={3}
                      />
                    </div>

                    {/* Часы работы */}
                    <div className="form-group working-hours-group">
                      <label>Часы работы</label>
                      <div className="working-hours-grid">
                        {Object.entries(weekDays).map(([day, dayName]) => (
                          <div key={day} className="working-hour-row">
                            <label className="day-checkbox">
                              <input
                                type="checkbox"
                                checked={data.working_hours[day].enabled}
                                onChange={(e) => handleWorkingHoursChange(day, 'enabled', e.target.checked)}
                              />
                              <span>{dayName}</span>
                            </label>

                            {data.working_hours[day].enabled && (
                              <div className="time-range">
                                <input
                                  type="time"
                                  value={data.working_hours[day].from}
                                  onChange={(e) => handleWorkingHoursChange(day, 'from', e.target.value)}
                                  className="time-input"
                                />
                                <span>—</span>
                                <input
                                  type="time"
                                  value={data.working_hours[day].to}
                                  onChange={(e) => handleWorkingHoursChange(day, 'to', e.target.value)}
                                  className="time-input"
                                />
                              </div>
                            )}
                          </div>
                        ))}
                      </div>
                    </div>

                    <div className="form-actions">
                      <button type="button" className="cancel-btn" onClick={() => setShowCompanyForm(false)}>
                        Отмена
                      </button>
                      <button type="submit" className="submit-btn" disabled={processing}>
                        {processing ? 'Сохранение...' : 'Добавить компанию'}
                      </button>
                    </div>
                  </form>
                </div>
              )}

              {/* Если есть компания - показываем карточку */}
              {sellerProfile && (
                <div className="company-card">
                  <div className="company-card-header">
                    <div className="company-logo">
                      <div className="logo-placeholder">🏢</div>
                    </div>
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
                      <div className="info-row">
                        <span className="info-label">Юридический адрес:</span>
                        <span className="info-value">{sellerProfile.legal_address || '—'}</span>
                      </div>
                      <div className="info-row">
                        <span className="info-label">Адрес самовывоза:</span>
                        <span className="info-value">{sellerProfile.pickup_address}</span>
                      </div>
                      {sellerProfile.description && (
                        <div className="info-row">
                          <span className="info-label">Описание:</span>
                          <span className="info-value">{sellerProfile.description}</span>
                        </div>
                      )}
                    </div>

                    {sellerProfile.working_hours && (
                      <div className="info-section">
                        <h3>Часы работы</h3>
                        <div className="working-hours-list">
                          {Object.entries(sellerProfile.working_hours).map(([day, hours]) => {
                            const dayName = weekDays[day];
                            if (!dayName) return null;
                            return (
                              <div key={day} className="hours-row">
                                <span className="day">{dayName}</span>
                                {hours.enabled ? (
                                  <span className="time">{hours.from} — {hours.to}</span>
                                ) : (
                                  <span className="closed">Выходной</span>
                                )}
                              </div>
                            );
                          })}
                        </div>
                      </div>
                    )}

                    {sellerProfile.rating > 0 && (
                      <div className="info-section stats">
                        <div className="stat-item">
                          <span className="stat-value">⭐ {sellerProfile.rating}</span>
                          <span className="stat-label">Рейтинг</span>
                        </div>
                        <div className="stat-item">
                          <span className="stat-value">{sellerProfile.total_sales}</span>
                          <span className="stat-label">Продаж</span>
                        </div>
                      </div>
                    )}

                    {auth.user.role === 'seller' && (
                      <Link href="/seller/dashboard" className="dashboard-link">
                        Перейти в панель продавца →
                      </Link>
                    )}
                  </div>
                </div>
              )}
            </div>
          )}

        </div>

      </div>

      {/* ВСЕ МОДАЛКИ */}
      <ProfileEditModal
        isOpen={isEditOpen}
        onClose={() => setIsEditOpen(false)}
        auth={auth}
        onSuccess={(msg) => setMessage(msg || 'Профиль обновлен!')}
      />

      <PhoneVerificationModal
        isOpen={isPhoneOpen}
        onClose={() => setIsPhoneOpen(false)}
        auth={auth}
        onSuccess={(msg) => setMessage(msg || 'Телефон верифицирован!')}
      />

      <BlockedAccountModal
        isOpen={isBlockedOpen}
        onClose={() => setIsBlockedOpen(false)}
      />

      <DeleteAccountModal
        isOpen={isDeleteOpen}
        onClose={() => setIsDeleteOpen(false)}
        onSuccess={(msg) => setMessage(msg || 'Аккаунт удален')}
      />
      {barcodeOpen && (
        <div className="barcode-modal" onClick={() => setBarcodeOpen(false)}>
          <div className="barcode-box" onClick={(e) => e.stopPropagation()}>
            <Barcode value={code.replace(/\s/g, '')} />
          </div>
        </div>
      )}
    </MainLayout>
  );
}