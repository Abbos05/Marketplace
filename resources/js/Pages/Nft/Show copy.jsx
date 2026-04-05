import { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/product/product_page.css';
import '../../../css/product/PaymentModal.css';


export default function NftShow({ auth, nft, cart, nftUser, flash }) {
  const [inCart, setInCart] = useState(cart);
  const [modalOpen, setModalOpen] = useState(false);
  const [topUpAmount, setTopUpAmount] = useState('');
  const [loading, setLoading] = useState(false);

  const price = parseFloat(nft.price) || 0;
  const balance = parseFloat(auth.user?.balance) || 0;
  const hasEnough = balance >= price;
  const lack = hasEnough ? 0 : (price - balance).toFixed(2);
  const [isFavorite, setIsFavorite] = useState(nft.is_favorite);

  const user = auth.user;

  useEffect(() => {
    // Авто-закрытие пополнения, если хватило
    if (topUpAmount > 0 && balance >= nft.price) {
      setTopUpAmount('');
    }
  }, [balance, nft.price, topUpAmount]);

  const addToCart = () => {
    router.post(route('cart.store'), { nft }, { onSuccess: () => setInCart(true) });
  };

  const removeFromCart = () => {
    router.delete(route('cart.destroy'), { data: { nft } }, { onSuccess: () => setInCart(false) });
  };


  const handleAction = () => {
    if (auth.user.is_blocked === 0 && auth.user.phone) {
      setModalOpen(true);
    } else {
      sessionStorage.setItem(
        'flashMessage',
        'Пройдите верификацию или вы заблакировани'
      );
      window.location.href = '/profile';
    }
  };
  const handleAdmin = () => {
    router.post(route('admin.nft.buy'), { nft }); // Переходим на страницу профиля

  };
  const handleAdminStop = () => {
    router.post(route('admin.nft.stop'), { nft }); // Переходим на страницу профиля
  };
  const handleAdminSold = () => {
    router.post(route('admin.nft.sold'), { nft }); // Переходим на страницу профиля
  };
  const closeModal = () => {
    setModalOpen(false);
    setTopUpAmount('');
  };

  const payWithWallet = () => {
    setLoading(true);
    router.post(route('payment.wallet'), { nft_id: nft.id }, {
      onSuccess: () => {
        closeModal();
        router.visit(route('nft.show', nft.id));
      },
      onError: () => setLoading(false),
    });
  };

  const payWithCard = () => {
    closeModal();
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    fetch('/stripe/checkout', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ nft_id: nft.id, title: nft.title, price: nft.price }),
    })
      .then(r => r.json())
      .then(s => window.location.href = s.url);
  };

  // ПОПОЛНИТЬ КОШЕЛЁК
  const topUpWallet = () => {
    const amount = parseFloat(topUpAmount);
    if (!amount || amount < 50) return alert('Минимум 50 ₽');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    fetch('/stripe/topup', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ amount }),
    })
      .then(r => r.json())
      .then(s => window.location.href = s.url);
  };

  // favorites
  const toggleFavorite = () => {
    setIsFavorite(p => !p);
    router.post(route('favorites.toggle', nft.id), {}, {
      onSuccess: () => router.reload({ only: ['nfts'] })
    });
  };

  return (
    <MainLayout auth={auth}>
      <Head title={`NFT: ${nft.title}`} />

      <section>
        <div className="container">
          <div className={`NFTBuy ${nft.status === 'moderation' ? 'NFT-status--moderation' :
            nft.status === 'sold' ? 'NFT-status--sold' :
              nft.status === 'rejection' ? 'NFT-status--rejection' :
                nft.status === 'relevant' ? 'NFT-status--relevant' : ''}`}>
            <div className="favorites-NFT">
              <div title={nft.status === 'rejection' ? 'Вам отказали обрашайтесь поддержку' : ''} className={
                nft.status === 'moderation' ? 'NFT-badge--moderation' :
                  nft.status === 'sold' ? 'NFT-badge--sold' :
                    nft.status === 'rejection' ? 'NFT-badge--sold NFT-status--rejection' :
                      nft.status === 'relevant' ? 'NFT-badge--relevant' :
                        'none'
              }>
                {nft.status === 'moderation' ? 'На проверке' :
                  nft.status === 'relevant' ? 'На продаже' :
                    nft.status === 'rejection' ? 'Отказано' : 'Снято с продаж'}
              </div>
              <div
                className=""
                onClick={toggleFavorite}
                style={{ cursor: 'pointer' }}
              >
                <img
                  src="/img/nft/favorites.svg"
                  alt="favorites"
                  id={`favorite-icon-${nft.id}`}
                  className={isFavorite ? 'active' : ''}
                  style={{
                    filter: isFavorite
                      ? 'none'
                      : 'brightness(30%)',
                    transition: 'all 0.3s ease',
                  }}
                />
              </div>
            </div>


            <img src={nft.image} alt={nft.title} />
            <div className="NFT__item">

              <h1>{nft.title}</h1>
              <div className="NFT__cash__block">

                <p className="NFT__cash">   {new Intl.NumberFormat('ru-RU', {
                  style: 'currency',
                  currency: 'RUB',
                  minimumFractionDigits: 2,
                }).format(nft.price)}</p>
                <p className={`NFT__percentage ${nft.percentage === 0 && ('none')}`} title={` Старая цена: ${nft.previous_price}₽`}
                  style={{ color: nft.percentage < 0 ? 'red' : '#24bd5e' }}
                >{nft.percentage}%</p>
              </div>

              <div className="NFT__title">
                <p>Описание</p>
                <p>{nft.description || 'Описание отсутствует'}</p>
              </div>

              <div className="NFT__owner">
                <p>Владелец</p>
                <div className="NFT__owner__item">
                  <img src={nftUser.owner_avatar ? `/${nftUser.owner_avatar}` : '/img/profiles/default-avatar.png'} alt="owner" />
                  <p>
                    {nftUser.owner_name === auth.user?.name
                      ? `${nftUser.owner_name} — это вы`
                      : nftUser.owner_name || 'Аноним'}
                  </p>

                </div>
              </div>
              {auth.user.role == 'admin' ? (
                <div className="NFT__btn__block">
                  {nft.status == 'moderation' ?
                    (<>
                      <button
                        onClick={handleAdmin}
                        className="NFT__btn ml-2"
                      >
                        {loading ? 'Обработка...' : 'Разрешить'}
                      </button>
                      <button
                        onClick={handleAdminStop}
                        className="NFT__btn ml-2"
                      >
                        {loading ? 'Обработка...' : 'Отказаться'}
                      </button>
                    </>) : (
                      <>
                        {nft.status == 'relevant' && (
                          <button
                            onClick={handleAdminSold}
                            className="NFT__btn ml-2"
                          >
                            {loading ? 'Обработка...' : 'Снять с продаж'}
                          </button>
                        )}
                        <button
                          onClick={() => router.delete(route('nft.destroy', nft.id))}
                          className="NFT__btn"
                        >
                          Удалить
                        </button>
                      </>
                    )
                  }



                </div>
              ) : (
                <>
                  {nft.user_id === auth.user.id ? (
                    <div className="">
                      {nft.status == 'sold' ? (
                        <div className="btn_buyNft">
                          <button
                            onClick={() => router.visit(route('nft.edit', nft.id))}
                            className="NFT__btn"
                          >
                            Продать
                          </button>
                          <button
                            onClick={() => router.delete(route('nft.destroy', nft.id))}
                            className="NFT__btn"
                          >
                            Удалить
                          </button>
                        </div>
                      ) : (
                        <div className="NFT__btn__block">

                          <button
                            onClick={() => router.visit(route('nft.edit', nft.id))}
                            className="NFT__btn"
                          >
                            Редактировать
                          </button>
                          {nft.status !== 'rejection' ? (
                            <button
                              onClick={() => router.post(route('nft.stop', nft.id))}
                              disabled={nft.status === 'rejection'}
                              className="NFT__btn"
                            >
                              Снять с продаж
                            </button>
                          ) : (
                            <button
                              onClick={() => router.delete(route('nft.destroy', nft.id))}
                              className="NFT__btn"
                            >
                              Удалить
                            </button>
                          )}
                        </div>
                      )}
                    </div>
                  ) : (
                    <div className="NFT__btn__block">
                      <button
                        disabled={loading || nft.status !== 'relevant'}
                        onClick={handleAction}
                        className="NFT__btn ml-2"
                      >
                        {loading ? 'Обработка...' : 'Купить'}
                      </button>

                      {inCart ? (
                        <button className="NFT__btn" onClick={removeFromCart}>
                          Удалить из корзины
                        </button>
                      ) : (
                        <button className="NFT__btn" onClick={addToCart}>
                          В корзину
                        </button>
                      )}

                    </div>
                  )}
                </>
              )}

              <div className="flesh">
                {flash?.success && (
                  <div className="successNft">
                    {flash.success}
                  </div>
                )}

                {flash?.error && (
                  <div className="errorNft">
                    {flash.error}
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </section>
      {modalOpen && (
        <div className="payment-overlay" onClick={closeModal}>
          <div className="payment-modal" onClick={e => e.stopPropagation()}>
            <h2 className="payment-title">Выберите способ оплаты</h2>

            {/* КОШЕЛЁК */}
            <div
              className="payment-option"
              onClick={() => hasEnough ? payWithWallet() : setTopUpAmount(lack)}
            >
              <div className="payment-option-title">💰 Кошелёк</div>
              <div className="payment-option-desc">
                <div className="payment-wallet-balance">
                  Баланс: {balance.toFixed(2)} ₽
                </div>
                {!hasEnough && (
                  <div className="payment-lack">
                    Не хватает: {lack} ₽
                  </div>
                )}
              </div>
            </div>

            {/* КАРТА */}
            <div className="payment-option" onClick={payWithCard}>
              <div className="payment-option-title">💳 Банковская карта</div>
              <div className="payment-option-desc">Оплата через Stripe</div>
            </div>

            {/* ПОПОЛНЕНИЕ */}
            {topUpAmount > 0 && (
              <div className="payment-topup">
                <p className="payment-topup-title">
                  Пополнить на {parseFloat(topUpAmount).toFixed(2)} ₽
                </p>
                <input
                  type="number"
                  value={topUpAmount}
                  onChange={e => setTopUpAmount(e.target.value)}
                  min="50"
                  step="0.01"
                  placeholder="Минимум 50"
                  className="payment-input"
                />
                <p className='payment-topup-title'>Минимум 50</p>
                <button onClick={topUpWallet} className="payment-topup-btn">
                  Пополнить картой
                </button>
              </div>
            )}

            <button onClick={closeModal} className="payment-close">
              Отмена
            </button>
          </div>
        </div>
      )}
    </MainLayout>
  );
}