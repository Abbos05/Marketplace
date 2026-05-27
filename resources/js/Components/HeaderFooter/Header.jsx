import React, { useState, useEffect, useRef } from 'react';
import HeaderMenu from './HeaderMenu';
import { Link, router, usePage } from '@inertiajs/react';
import { pinWidgetFromHeaderDrag } from '@/lib/messagesWidget';
import BarcodeScannerModal from '@/Components/BarcodeScannerModal';
import HeaderSearchWithSuggestions from '@/Components/HeaderFooter/HeaderSearchWithSuggestions';
import { mergeCatalogSearchParams } from '@/lib/catalogFilters';
import '../../../css/MainLayout.css';

const Header = ({ setIsModalOpen }) => {
  const {
    auth,
    categories,
    filters,
    search: pageSearch = '',
    catalogSearchQuery = '',
    messagesHubUnreadCount = 0,
  } = usePage().props;
  const user = auth?.user;
  const [hubBadge, setHubBadge] = useState(() => Number(messagesHubUnreadCount) || 0);

  useEffect(() => {
    setHubBadge(Number(messagesHubUnreadCount) || 0);
  }, [messagesHubUnreadCount]);

  useEffect(() => {
    const onHub = (e) => {
      setHubBadge(Number(e.detail) || 0);
    };
    window.addEventListener('inertia:messages-hub-unread', onHub);
    return () => window.removeEventListener('inertia:messages-hub-unread', onHub);
  }, []);

  // Меню категорий
  const [isStoreMenuOpen, setIsStoreMenuOpen] = useState(false);
  const [isMobileCatalogOpen, setIsMobileCatalogOpen] = useState(false);
  const [isMobileSearchOpen, setIsMobileSearchOpen] = useState(false);
  const [scannerOpen, setScannerOpen] = useState(false);
  const storeMenuRef = useRef(null);
  const msgDragRef = useRef(null);
  const suppressMsgClickRef = useRef(false);

  const MSG_DRAG_THRESHOLD = 12;

  const onMessagesPointerDown = (e) => {
    if (!user || e.button !== 0) return;
    msgDragRef.current = { x: e.clientX, y: e.clientY, dragging: false };
    const onMove = (ev) => {
      const d = msgDragRef.current;
      if (!d) return;
      if (Math.hypot(ev.clientX - d.x, ev.clientY - d.y) > MSG_DRAG_THRESHOLD) {
        d.dragging = true;
      }
    };
    const onUp = (ev) => {
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('pointerup', onUp);
      window.removeEventListener('pointercancel', onUp);
      const d = msgDragRef.current;
      msgDragRef.current = null;
      if (!d) return;
      if (d.dragging) {
        suppressMsgClickRef.current = true;
        pinWidgetFromHeaderDrag(ev.clientX, ev.clientY);
      }
    };
    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
    window.addEventListener('pointercancel', onUp);
  };

  const onMessagesClick = (e) => {
    if (suppressMsgClickRef.current) {
      e.preventDefault();
      suppressMsgClickRef.current = false;
      return;
    }
    router.visit(route('messages.index'));
  };

  const hasFiltersSearch = !!filters && Object.prototype.hasOwnProperty.call(filters, 'search');
  const rawUrlSearch = hasFiltersSearch
    ? filters?.search
    : (catalogSearchQuery ?? pageSearch ?? '');
  const urlSearch = String(rawUrlSearch ?? '').trim();
  const [searchQuery, setSearchQuery] = useState(urlSearch);

  useEffect(() => {
    setSearchQuery(urlSearch);
  }, [urlSearch]);

  // Кошелёк
  const [activeTab, setActiveTab] = useState('topup'); // 'topup' | 'withdraw'

  // Пополнение
  const [topupAmount, setTopupAmount] = useState('');
  const [topupLoading, setTopupLoading] = useState(false);

  // Вывод
  const [withdrawAmount, setWithdrawAmount] = useState('');
  const [cardNumber, setCardNumber] = useState('');
  const [withdrawLoading, setWithdrawLoading] = useState(false);
  const [withdrawError, setWithdrawError] = useState('');
  const [withdrawSuccess, setWithdrawSuccess] = useState(false);

  // Поиск
  const handleSearch = () => {
    if (!searchQuery.trim()) return;
    setIsMobileSearchOpen(false);

    const trimmed = searchQuery.trim();
    if (/^\d+$/.test(trimmed) && trimmed.startsWith('000') && trimmed.length >= 4) {
      router.visit(`/article/${trimmed}`);
      return;
    }

    const params = mergeCatalogSearchParams(trimmed, filters || {});

    const currentPath = window.location.pathname;
    let targetPath = '/';

    if (currentPath.startsWith('/admins')) targetPath = '/admins';
    else if (currentPath.startsWith('/category')) targetPath = currentPath;
    else if (currentPath.startsWith('/sellerProfile')) targetPath = currentPath;

    router.get(targetPath, params, {
      preserveScroll: false,
      preserveState: false,
    });
  };

  // Закрытие меню по клику вне
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (storeMenuRef.current && !storeMenuRef.current.contains(e.target)) {
        setIsStoreMenuOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    if (!isMobileCatalogOpen) return undefined;

    const previousOverflow = document.body.style.overflow;
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        setIsMobileCatalogOpen(false);
      }
    };

    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', handleKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isMobileCatalogOpen]);

  // Пополнение (через Stripe — как было)
  const handleTopUp = () => {
    if (!topupAmount || topupAmount < 50) return;
    setTopupLoading(true);

    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    fetch('/stripe/topup', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      body: JSON.stringify({ amount: parseInt(topupAmount) }),
    })
      .then(response => {
        if (!response.ok) {
          throw new Error('Stripe request failed');
        }
        return response.json();
      })
      .then(data => {
        if (data.url) {
          window.location.href = data.url;
        } else {
          throw new Error('No URL in response');
        }
      })
      .catch(error => {
        fetch('/stripe/topupImitatin', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf
          },
          body: JSON.stringify({ amount: parseInt(topupAmount) }),
        })
          .then(response => {
            if (response.ok) {
              alert('Пополнение успешно (имитация)');
              // Обновляем данные страницы, чтобы баланс обновился
              router.reload({ only: ['auth'] }); // Обновляем только auth данные
            } else {
              alert('Ошибка пополнения');
            }
            setTopupLoading(false);
          })
          .catch(() => {
            alert('Ошибка пополнения');
            setTopupLoading(false);
          });
      });
  };

  // ВЫВОД — с мгновенным обновлением баланса через Inertia
  const handleWithdraw = () => {
    const amount = parseFloat(withdrawAmount);
    if (amount < 50) return setWithdrawError('Минимум 50 ₽');
    if (!user || amount > user.balance) return setWithdrawError('Недостаточно средств');
    if (cardNumber.length !== 16) return setWithdrawError('Номер карты — 16 цифр');

    setWithdrawLoading(true);
    setWithdrawError('');
    setWithdrawSuccess(false);

    router.post('/wallet/withdraw', {
      amount,
      card_number: cardNumber,
    }, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        setWithdrawSuccess(true);
        setWithdrawAmount('');
        setCardNumber('');
        setWithdrawError(errors.message || errors.card_number || 'Ошибка вывода');
        setActiveTab('topup');
        // Баланс обновится автоматически — Inertia перерисует компонент с новым user.balance
      },
      onError: (errors) => {
      },
      onFinish: () => setWithdrawLoading(false),
    });
  };

  // Закрытие кошелька

  const closeCatalogMenus = () => {
    setIsStoreMenuOpen(false);
    setIsMobileCatalogOpen(false);
  };

  const closeMobileSearch = () => {
    setIsMobileSearchOpen(false);
  };

  const handleBarcodeScan = (value) => {
    const trimmed = String(value ?? '').trim();
    setScannerOpen(false);
    if (/^\d+$/.test(trimmed) && trimmed.startsWith('000') && trimmed.length >= 4) {
      router.visit(`/article/${trimmed}`);
      return;
    }
    setSearchQuery(trimmed);
  };

  const catalogItems = categories ?? [];

  const renderCatalogList = (extraClassName = '') => (
    <ul className={`header-store-menu-dropdown ${extraClassName}`.trim()}>
      <li className="header-store-menu-item header-store-menu-item--title no-select">
        Каталог
      </li>
      {catalogItems.map((catalog) => (
        <li key={catalog.id} className="header-store-menu-item">
          <Link
            href={`/category/${catalog.id}`}
            className="header-store-menu-link"
            onClick={closeCatalogMenus}
          >
            <img
              src={catalog.img || catalog.icon || '/img/products/default.png'}
              alt=""
              className="header-store-menu-image"
            />
            <span>{catalog.name}</span>
          </Link>
        </li>
      ))}
      <li className="header-store-menu-item header-store-menu-item--all">
        <Link href="/category" className="header-store-menu-link" onClick={closeCatalogMenus}>
          <span>Перейти в каталог</span>
        </Link>
      </li>
    </ul>
  );

  const renderSearch = (className = '') => (
    <HeaderSearchWithSuggestions
      className={className}
      searchQuery={searchQuery}
      setSearchQuery={setSearchQuery}
      onSearch={handleSearch}
      onOpenScanner={() => setScannerOpen(true)}
      onNavigate={() => setIsMobileSearchOpen(false)}
      filters={filters}
    />
  );

  const renderMessagesAction = () => {
    const badge = Number(hubBadge) || 0;
    const badgeNode = badge > 0 ? (
      <span className="header-action-badge">
        {badge > 99 ? '99+' : badge}
      </span>
    ) : null;

    const icon = (
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" className="products__basket">
        <path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z" />
            <line x1="8" y1="9" x2="17" y2="9" stroke="currentColor" strokeWidth="2.6" />
            <line x1="8" y1="13" x2="15" y2="13" stroke="currentColor" strokeWidth="2.2" />
      </svg>
    );

    if (!user) {
      return (
        <Link href={route('messages.index')} className="header-wallet">
          {icon}
          <p>Сообщения</p>
          {badgeNode}
        </Link>
      );
    }

    return (
      <div
        className="header-wallet header-messages-drag"
        onPointerDown={onMessagesPointerDown}
        onClick={onMessagesClick}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            router.visit(route('messages.index'));
          }
        }}
        aria-label="Сообщения. Клик — открыть чат. Удерживайте и перетащите — закрепить окно чата"
      >
        {icon}
        <p>Сообщения</p>
        {badgeNode}
      </div>
    );
  };

  const renderHeaderActions = () => (
    <>
      <div className="header-action-item">{renderMessagesAction()}</div>
      <div className="header-action-item">
        <Link href="/orders" className="header-wallet">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="26" viewBox="0 0 24 24" className="products__basket">
            <path fill="currentColor" d="M14.692 5.694c.368-.205.365-.469-.009-.664C13.367 4.343 12.708 4 12 4s-1.367.343-2.683 1.03l-2 1.044c-1.614.842-2.42 1.263-2.869 2.02C4 8.85 4 9.79 4 11.673v1.652c0 1.883 0 2.824.448 3.58s1.255 1.178 2.869 2.02l2 1.044C10.633 20.657 11.292 21 12 21s1.367-.343 2.683-1.03l2-1.044c1.614-.842 2.42-1.263 2.869-2.02.448-.756.448-1.697.448-3.58v-1.652c0-1.883 0-2.824-.448-3.58-.329-.556-.851-.93-1.744-1.423-.367-.203-.389-.204-.763.004L11 10c-.344.19-.739.394-.91.77-.09.197-.09.375-.09.73V14a1 1 0 0 1-2 0v-4a1 1 0 0 1 .514-.874z" />
          </svg>
          <p>Заказы</p>
        </Link>
      </div>
      <div className="header-action-item">
        <Link href="/cart" className="header-wallet">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" className="products__basket">
            <path fill="currentColor" d="M9.925 5.371a1 1 0 1 0-1.858-.742L6.317 9h-1.2c-1.076 0-1.614 0-1.913.346-.3.346-.222.878-.067 1.942l.271 1.864c.475 3.265.902 4.898 2.03 5.873s2.778.975 6.08.975h.96c3.302 0 4.953 0 6.08-.975 1.128-.975 1.559-2.608 2.034-5.873l.271-1.864c.155-1.064.233-1.596-.067-1.942S19.96 9 18.883 9h-1.205l-1.75-4.371a1 1 0 0 0-1.857.742L15.523 9h-7.05zM10.997 14v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 2 0M14 13a1 1 0 0 1 1 1v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 1-1" />
          </svg>
          <p>Корзина</p>
        </Link>
      </div>
      <div className="header-action-item wallet-btnRelative">
        <Link href="/favorites" className="header-wallet">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
            <path fill="currentColor" d="M3 10.163C3 7.262 5.13 5 8 5c1.929 0 3.244 1.102 4 2.066C12.756 6.102 14.071 5 16 5c2.87 0 5 2.264 5 5.163 0 4.561-4.568 7.856-8.243 9.66a1.71 1.71 0 0 1-1.514 0C7.568 18.02 3 14.724 3 10.163" />
          </svg>
          <p>Избранное</p>
        </Link>
      </div>
      <HeaderMenu setIsModalOpen={setIsModalOpen} />
    </>
  );

  const renderSearchIcon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
      <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="2.3" />
      <path d="M16.2 16.2L21 21" stroke="currentColor" strokeWidth="2.3" strokeLinecap="round" />
    </svg>
  );

  const renderHomeIcon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" className="products__basket">
      <path fill="currentColor" d="M3 10.6 12 3l9 7.6V20a1 1 0 0 1-1 1h-5.2a1 1 0 0 1-1-1v-5.2h-3.6V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z" />
    </svg>
  );

  const renderMobileHeaderActions = () => (
    <>
      <div className="header-action-item header-action-item--mobile-home">
        <Link href="/" className="header-wallet">
          {renderHomeIcon()}
          <p>Домой</p>
        </Link>
      </div>
      <div className="header-action-item header-action-item--mobile-search">
        <button
          type="button"
          className="header-wallet header-mobile-search-toggle"
          onClick={() => setIsMobileSearchOpen(true)}
          aria-expanded={isMobileSearchOpen}
          aria-label="Открыть поиск"
        >
          {renderSearchIcon()}
          <p>Поиск</p>
        </button>
      </div>
      <div className="header-action-item">
        <Link href="/orders" className="header-wallet">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="26" viewBox="0 0 24 24" className="products__basket">
            <path fill="currentColor" d="M14.692 5.694c.368-.205.365-.469-.009-.664C13.367 4.343 12.708 4 12 4s-1.367.343-2.683 1.03l-2 1.044c-1.614.842-2.42 1.263-2.869 2.02C4 8.85 4 9.79 4 11.673v1.652c0 1.883 0 2.824.448 3.58s1.255 1.178 2.869 2.02l2 1.044C10.633 20.657 11.292 21 12 21s1.367-.343 2.683-1.03l2-1.044c1.614-.842 2.42-1.263 2.869-2.02.448-.756.448-1.697.448-3.58v-1.652c0-1.883 0-2.824-.448-3.58-.329-.556-.851-.93-1.744-1.423-.367-.203-.389-.204-.763.004L11 10c-.344.19-.739.394-.91.77-.09.197-.09.375-.09.73V14a1 1 0 0 1-2 0v-4a1 1 0 0 1 .514-.874z" />
          </svg>
          <p>Заказы</p>
        </Link>
      </div>
      <div className="header-action-item">
        <Link href="/cart" className="header-wallet">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" className="products__basket">
            <path fill="currentColor" d="M9.925 5.371a1 1 0 1 0-1.858-.742L6.317 9h-1.2c-1.076 0-1.614 0-1.913.346-.3.346-.222.878-.067 1.942l.271 1.864c.475 3.265.902 4.898 2.03 5.873s2.778.975 6.08.975h.96c3.302 0 4.953 0 6.08-.975 1.128-.975 1.559-2.608 2.034-5.873l.271-1.864c.155-1.064.233-1.596-.067-1.942S19.96 9 18.883 9h-1.205l-1.75-4.371a1 1 0 0 0-1.857.742L15.523 9h-7.05zM10.997 14v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 2 0M14 13a1 1 0 0 1 1 1v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 1-1" />
          </svg>
          <p>Корзина</p>
        </Link>
      </div>
      <div className="header-action-item wallet-btnRelative">
        <Link href="/favorites" className="header-wallet">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
            <path fill="currentColor" d="M3 10.163C3 7.262 5.13 5 8 5c1.929 0 3.244 1.102 4 2.066C12.756 6.102 14.071 5 16 5c2.87 0 5 2.264 5 5.163 0 4.561-4.568 7.856-8.243 9.66a1.71 1.71 0 0 1-1.514 0C7.568 18.02 3 14.724 3 10.163" />
          </svg>
          <p>Избранное</p>
        </Link>
      </div>
      <HeaderMenu setIsModalOpen={setIsModalOpen} />
    </>
  );


  return (
    <header className="header">
      <div className="header-shell">
        <div className="header-ul">
          <div className="header-top">
            <Link href="/" className="header-logo-name">
              <svg className="logo" viewBox="0 0 400 100" width="100%" height="100%">
                <text x="50%" y="62%" textAnchor="middle" dominantBaseline="middle"
                  fill="currentColor">
                  Alvora
                </text>
              </svg>
            </Link>

            <div className="header-menu-store-container header-menu-store-container--desktop" ref={storeMenuRef}>
              <button
                type="button"
                className="header-menu-store"
                onClick={() => setIsStoreMenuOpen(prev => !prev)}
                aria-expanded={isStoreMenuOpen}
              >
                <img src="/img/header/MenuStore.svg" alt="" />
                <p>Каталог</p>
              </button>

              {isStoreMenuOpen ? renderCatalogList('header-store-menu-dropdown--desktop') : null}
            </div>

            <div className="header-search-slot header-search-slot--desktop">
              {renderSearch()}
            </div>

            <div className="header-wallet-block">
              <div className="header-wallet-block__desktop">
                {renderHeaderActions()}
              </div>
              <div className="header-wallet-block__mobile">
                {renderMobileHeaderActions()}
              </div>
            </div>
          </div>

          <div className="header-mobile-search-row">
            {renderSearch('header-search--mobile')}
          </div>
        </div>
      </div>

      {isMobileCatalogOpen ? (
        <div className="header-catalog-sheet" role="dialog" aria-modal="true" aria-labelledby="mobile-catalog-title">
          <button
            type="button"
            className="header-catalog-sheet__backdrop"
            aria-label="Закрыть каталог"
            onClick={() => setIsMobileCatalogOpen(false)}
          />
          <div className="header-catalog-sheet__panel">
            <div className="header-catalog-sheet__head">
              <h2 id="mobile-catalog-title">Каталог</h2>
              <button
                type="button"
                className="header-catalog-sheet__close"
                aria-label="Закрыть каталог"
                onClick={() => setIsMobileCatalogOpen(false)}
              >
                ×
              </button>
            </div>
            {renderCatalogList('header-store-menu-dropdown--mobile')}
          </div>
        </div>
      ) : null}

      {isMobileSearchOpen ? (
        <div className="header-mobile-search-panel" role="dialog" aria-modal="true" aria-label="Расширенный поиск">
          <button
            type="button"
            className="header-mobile-search-panel__backdrop"
            aria-label="Закрыть поиск"
            onClick={closeMobileSearch}
          />
          <div className="header-mobile-search-panel__box">
            <div className="header-mobile-search-panel__head">
              <strong>Поиск</strong>
              <button type="button" onClick={closeMobileSearch} aria-label="Закрыть поиск">×</button>
            </div>
            <div className="header-mobile-search-panel__searchline">
              {renderSearch('header-search--expanded-mobile')}
            </div>
            <div className="header-mobile-search-panel__categories">
              <span>Категории</span>
              <div className="header-mobile-search-panel__chips">
                <Link href="/category" onClick={closeMobileSearch}>Все товары</Link>
                {catalogItems.slice(0, 8).map((catalog) => (
                  <Link key={catalog.id} href={`/category/${catalog.id}`} onClick={closeMobileSearch}>
                    {catalog.name}
                  </Link>
                ))}
              </div>
            </div>
          </div>
        </div>
      ) : null}

      <BarcodeScannerModal
        open={scannerOpen}
        onClose={() => setScannerOpen(false)}
        onScan={handleBarcodeScan}
        title="Сканировать артикул"
        hint="Наведите камеру на штрихкод товара. Найденные цифры появятся в поиске."
      />
    </header>
  );
};

export default Header;