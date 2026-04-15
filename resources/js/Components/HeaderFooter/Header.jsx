import React, { useState, useEffect, useRef } from 'react';
import HeaderMenu from './HeaderMenu';
import { Link, router, usePage } from '@inertiajs/react';
import '../../../css/MainLayout.css';

const Header = ({ setIsModalOpen, setIsLogin }) => {
  const { auth: { user }, categories, filters } = usePage().props;

  // Меню категорий
  const [isStoreMenuOpen, setIsStoreMenuOpen] = useState(false);
  const storeMenuRef = useRef(null);

  // Поиск
  const [searchQuery, setSearchQuery] = useState('');

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

    const currentPath = window.location.pathname;
    let targetPath = '/';

    if (currentPath.startsWith('/admins')) targetPath = '/admins';
     else if (currentPath.startsWith('/category')) {
          targetPath = currentPath;
          router.visit(targetPath, {
            data: { search: searchQuery.trim(), price_from: filters?.price_from, price_to: filters?.price_to },
            preserveState: true,
            preserveScroll: true,
          });
          return;
        }
     else if (currentPath.startsWith('/sellerProfile')) {
          targetPath = currentPath;
          router.visit(targetPath, {
            data: { search: searchQuery.trim(), price_from: filters?.price_from, price_to: filters?.price_to },
            preserveState: true,
            preserveScroll: true,
          });
          return;
        }

    router.visit(targetPath, { data: { search: searchQuery.trim() }, preserveState: true });
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
    if (amount > user.balance) return setWithdrawError('Недостаточно средств');
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


  return (
    <header className="header">
      <div className="container">
        <ul className="header-ul">
          <li>
            <Link href="/" className="header-logo-name">
<svg class="logo" viewBox="0 0 400 100" width="100%" height="100%">
  <text x="50%" y="62%" text-anchor="middle" dominant-baseline="middle" 
        fill="currentColor">
   Alvora
  </text>
</svg>
            </Link>
          </li>

          <li className="header-menu-store-container" ref={storeMenuRef}>
            <div className="header-menu-store" onClick={() => setIsStoreMenuOpen(prev => !prev)}>
              <img src="/img/header/MenuStore.svg" alt="menu" />
              <p>Каталог</p>
            </div>

            {isStoreMenuOpen && (
              <ul className="header-store-menu-dropdown">
                <li className="header-store-menu-item no-select" style={{ cursor: 'auto', background: '#111826' }}>
                  Каталог
                </li>
                {categories?.map((catalog) => (
                  <li key={catalog.id} className="header-store-menu-item">
                    <Link href={`/category/${catalog.id}`} className="header-store-menu-link">
                      <img src={catalog.img} alt={catalog.name} className="header-store-menu-image" />
                      <span>{catalog.name}</span>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </li>

          <li className='header-search'>
            <input
              type="text"
              placeholder="Поиск по названию или тегу..."
              maxLength="45"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
            />
            <div onClick={handleSearch} className="search-btn">
              <img src="/img/header/Search.png" alt="search" />
            </div>
          </li>

          <div className="header-wallet-block">
            <li>
              <Link href="/profiles?filter=myCart" className="header-wallet">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="26" viewBox="0 0 24 24" class="products__basket">
              <path fill="currentColor" d="M14.692 5.694c.368-.205.365-.469-.009-.664C13.367 4.343 12.708 4 12 4s-1.367.343-2.683 1.03l-2 1.044c-1.614.842-2.42 1.263-2.869 2.02C4 8.85 4 9.79 4 11.673v1.652c0 1.883 0 2.824.448 3.58s1.255 1.178 2.869 2.02l2 1.044C10.633 20.657 11.292 21 12 21s1.367-.343 2.683-1.03l2-1.044c1.614-.842 2.42-1.263 2.869-2.02.448-.756.448-1.697.448-3.58v-1.652c0-1.883 0-2.824-.448-3.58-.329-.556-.851-.93-1.744-1.423-.367-.203-.389-.204-.763.004L11 10c-.344.19-.739.394-.91.77-.09.197-.09.375-.09.73V14a1 1 0 0 1-2 0v-4a1 1 0 0 1 .514-.874z"></path>
              </svg>
                <p>Заказы</p>
              </Link>
            </li>
            <li>
              <Link href="/profiles?filter=myCart" className="header-wallet">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="products__basket"><path fill="currentColor" d="M9.925 5.371a1 1 0 1 0-1.858-.742L6.317 9h-1.2c-1.076 0-1.614 0-1.913.346-.3.346-.222.878-.067 1.942l.271 1.864c.475 3.265.902 4.898 2.03 5.873s2.778.975 6.08.975h.96c3.302 0 4.953 0 6.08-.975 1.128-.975 1.559-2.608 2.034-5.873l.271-1.864c.155-1.064.233-1.596-.067-1.942S19.96 9 18.883 9h-1.205l-1.75-4.371a1 1 0 0 0-1.857.742L15.523 9h-7.05zM10.997 14v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 2 0M14 13a1 1 0 0 1 1 1v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 1-1"></path></svg>
                <p>Профиль</p>
              </Link>
            </li>

            <li className="wallet-btnRelative">
              <div className="wallet-btn">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M3 10.163C3 7.262 5.13 5 8 5c1.929 0 3.244 1.102 4 2.066C12.756 6.102 14.071 5 16 5c2.87 0 5 2.264 5 5.163 0 4.561-4.568 7.856-8.243 9.66a1.71 1.71 0 0 1-1.514 0C7.568 18.02 3 14.724 3 10.163"></path></svg>
                <p>Избранное</p>
              </div>


            </li>

            <HeaderMenu setIsModalOpen={setIsModalOpen} setIsLogin={setIsLogin} />
          </div>
        </ul>
      </div>
    </header>
  );
};

export default Header;