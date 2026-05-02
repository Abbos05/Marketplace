import { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/product/product_page.css';
import '../../../css/product/PaymentModal.css';


export default function ProductShow({ auth, product, cart, favorite, flash, seller }) {
  const [inCart, setInCart] = useState(cart);
  const [isFavorite, setIsFavorite] = useState(product.is_favorite);
  const [isToggling, setIsToggling] = useState(false);

  const [modalOpen, setModalOpen] = useState(false);
  const [topUpAmount, setTopUpAmount] = useState('');
  const [loading, setLoading] = useState(false);
  const price = parseFloat(product.price) || 0;
  const balance = parseFloat(auth.user?.balance) || 0;
  const hasEnough = balance >= price;
  const lack = hasEnough ? 0 : (price - balance).toFixed(2);

  const user = auth.user;


  const toggleCard = () => {
    // Защита от спама
    if (isToggling) return;

    if (!product?.id) return;


    router.post(route('cart.toggle', product.id), {}, {
      preserveState: true,
      preserveScroll: true,
      onError: () => {
        setIsFavorite(previousValue);
        alert('Ошибка, попробуйте позже');
      },
      onFinish: () => {
        setIsToggling(false);
      }
    });
  };


  useEffect(() => {
    // Авто-закрытие пополнения, если хватило
    if (topUpAmount > 0 && balance >= product.price) {
      setTopUpAmount('');
    }
  }, [balance, product.price, topUpAmount]);

 // В ProductShow.jsx
const addToCart = () => {
    router.post(route('cart.store'), { 
        variant_id: product.variant_id // Теперь это поле есть в объекте
    }, {
        preserveScroll: true,
        onSuccess: () => setInCart(true)
    });
};

const removeFromCart = () => {
    router.delete(route('cart.destroy', product.variant_id), {
        preserveScroll: true,
        onSuccess: () => setInCart(false)
    });
};

// Также обновите useEffect для отслеживания пропа
useEffect(() => {
    setInCart(product.in_cart); // Используем поле из объекта
}, [product.in_cart]);


  // Следим за изменением пропсов
  useEffect(() => {
    setIsFavorite(product.is_favorite);
  }, [product.is_favorite]);

  const toggleFavorite = () => {
    // Защита от спама
    if (isToggling) return;

    if (!product?.id) return;

    const previousValue = isFavorite;
    const newValue = !isFavorite;

    setIsToggling(true);
    setIsFavorite(newValue);

    router.post(route('favorites.toggle', product.id), {}, {
      preserveState: true,
      preserveScroll: true,
      onError: () => {
        setIsFavorite(previousValue);
        alert('Ошибка, попробуйте позже');
      },
      onFinish: () => {
        setIsToggling(false);
      }
    });
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
    router.post(route('admin.product.buy'), { product }); // Переходим на страницу профиля

  };
  const handleAdminStop = () => {
    router.post(route('admin.product.stop'), { product }); // Переходим на страницу профиля
  };
  const handleAdminSold = () => {
    router.post(route('admin.product.sold'), { product }); // Переходим на страницу профиля
  };
  const closeModal = () => {
    setModalOpen(false);
    setTopUpAmount('');
  };

  const payWithWallet = () => {
    setLoading(true);
    router.post(route('payment.wallet'), { product_id: product.id }, {
      onSuccess: () => {
        closeModal();
        router.visit(route('product.show', product.id));
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
      body: JSON.stringify({ product_id: product.id, title: product.title, price: product.price }),
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


  const ShowSeller = () => {
    if (!seller?.id) {
      console.warn('Не удалось получить ID продавца');
      return;
    }

    router.get(
      route('seller.index', seller.id),
      {}, // Данные формы
      {
        preserveState: true,
        replace: true,
        preserveScroll: true,
      }
    );
  };

  // ...

  <button className="product-page__seller-profile" onClick={ShowSeller}>
    Посмотреть
  </button>


  return (
    <MainLayout auth={auth}>
      <Head title={`Alvora - ${product.title}`} />
      <section className="product-page"> {/* section на product-page для конкретной страницы */}
        <div className="product-page__content"> {/* Основной контейнер страницы товара */}

          {/* Левая колонка с основной информацией */}
          <div className="product-page__main">
            <div className="product-page__gallery"> {/* Галерея изображений */}
              <div className="product-page__main-image">
                <img src={product.image ?? '/img/products/default.jpg'} alt={product.title} />
              </div>
            </div>

            <div className="product-page__info">
              <h1 className="product-page__title">{product.title}</h1>

              <div className="product-page__rating">
                <div className="product-page__stars">
                  <img src="/img/products/star-icon.png" alt="Рейтинг" />
                  <span>5.0</span>
                </div>
                <div className="product-page__reviews-count">
                  <img src="/img/products/reviews-icon.png" alt="Отзывы" />
                  <span>100 отзывов</span>
                </div>
              </div>
              {/* Миниатюры для галереи */}
              <div className="product-page__thumbnails">
                <img src={product.image ?? "/img/products/default.jpg"} />
                <img src={product.image ?? "/img/products/default.jpg"} />
                <img src={product.image ?? "/img/products/default.jpg"} />
                <img src={product.image ?? "/img/products/default.jpg"} />
                <img src={product.image ?? "/img/products/default.jpg"} />
                <img src={product.image ?? "/img/products/default.jpg"} />
                <img src={product.image ?? "/img/products/default.jpg"} />
                <img src={product.image ?? "/img/products/default.jpg"} />
                <img src={product.image ?? "/img/products/default.jpg"} />
                <img src={product.image ?? "/img/products/default.jpg"} />
                {product?.gallery?.map((image, index) => (
                  <img key={index} src={image} alt={`${product.title} ${index + 1}`} />
                ))}
              </div>

              <div className="product-page__description">
                <h2 className="product-page__section-title">О товаре</h2> {/* h2 для подзаголовка */}
                <div className="product-page__specs">
                  <table>
                    <tbody>
                      <tr>
                        <th>Тип</th>
                        <td>Фигурка</td>
                      </tr>
                      <tr>
                        <th>Вид детской фигурки</th>
                        <td>Статичная</td>
                      </tr>
                      <tr>
                        <th>Цвет</th>
                        <td>Белый</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          {/* Правая колонка с покупкой */}
          <div className="product-page__sidebar">
            <div className="product-page__card">
              <div className="product-page__payment">
                <h3 className="product-page__payment-title">Оплата</h3>

                <div className="product-page__price-block">
                  <div className="product-page__current-price">
                    <div>
                      <h2 className='product-page__price'>
                        10 000₽
                      </h2>
                      <span className='product-page__discount-info'> со скидкой</span>
                    </div>
                    <span className="product-page__discount-info">
                      <del>12 000₽</del> -16%
                    </span>
                  </div>

                  <div className="product-page__credit">
                    <button className="product-page__credit-btn" onClick={() => alert('Пока не доступно')}>Оплатить позже</button>
                    <span className="product-page__credit-hint">без % в месяц</span>
                  </div>

                  <div className="product-page__actions">
                    <button
                      className="product-page__cart-btn"
                      onClick={inCart ? removeFromCart : addToCart}
                    >
                      {inCart ? 'Убрать из ' : 'В корзину'}
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="products__basket"><path fill="currentColor" d="M9.925 5.371a1 1 0 1 0-1.858-.742L6.317 9h-1.2c-1.076 0-1.614 0-1.913.346-.3.346-.222.878-.067 1.942l.271 1.864c.475 3.265.902 4.898 2.03 5.873s2.778.975 6.08.975h.96c3.302 0 4.953 0 6.08-.975 1.128-.975 1.559-2.608 2.034-5.873l.271-1.864c.155-1.064.233-1.596-.067-1.942S19.96 9 18.883 9h-1.205l-1.75-4.371a1 1 0 0 0-1.857.742L15.523 9h-7.05zM10.997 14v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 2 0M14 13a1 1 0 0 1 1 1v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 1-1"></path></svg>
                    </button>
                    <button className="product-page__wishlist-btn"
                      onClick={() => toggleFavorite()}
                    >
                      {isFavorite ? 'Убрать из' : 'Добавить в'}
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 5C5.93913 5 4.92172 5.42143 4.17157 6.17157C3.42143 6.92172 3 7.93913 3 9C3 12.552 5.218 15.296 7.621 17.22C8.96786 18.2885 10.438 19.1916 12 19.91C13.5608 19.1907 15.0302 18.2876 16.377 17.22C18.78 15.294 21 12.551 21 9C21 7.93913 20.5786 6.92172 19.8284 6.17157C19.0783 5.42143 18.0609 5 17 5C15.043 5 13.348 6.396 12.98 8.2C12.9341 8.42606 12.8115 8.62929 12.6329 8.77528C12.4542 8.92126 12.2307 9.001 12 9.001C11.7693 9.001 11.5458 8.92126 11.3671 8.77528C11.1885 8.62929 11.0659 8.42606 11.02 8.2C10.652 6.396 8.957 5 7 5Z" fill="#FF0202" />
                        <path d="M12 22C11.684 21.98 11.44 21.853 11.152 21.722C9.44651 20.9359 7.84139 19.9482 6.371 18.78C3.777 16.705 1 13.449 1 9C1 7.4087 1.63214 5.88258 2.75736 4.75736C3.88258 3.63214 5.4087 3 7 3C7.97708 3.0023 8.9397 3.23625 9.80885 3.68265C10.678 4.12905 11.4289 4.77517 12 5.568C12.5711 4.77517 13.322 4.12905 14.1911 3.68265C15.0603 3.23625 16.0229 3.0023 17 3C18.5913 3 20.1174 3.63214 21.2426 4.75736C22.3679 5.88258 23 7.4087 23 9C23 13.448 20.22 16.705 17.625 18.78C16.1544 19.9473 14.5497 20.935 12.845 21.722C12.302 21.971 12.113 22 12 22ZM7 5C5.93913 5 4.92172 5.42143 4.17157 6.17157C3.42143 6.92172 3 7.93913 3 9C3 12.552 5.218 15.296 7.621 17.22C8.96786 18.2885 10.438 19.1916 12 19.91C13.5608 19.1907 15.0302 18.2876 16.377 17.22C18.78 15.294 21 12.551 21 9C21 7.93913 20.5786 6.92172 19.8284 6.17157C19.0783 5.42143 18.0609 5 17 5C15.043 5 13.348 6.396 12.98 8.2C12.9341 8.42606 12.8115 8.62929 12.6329 8.77528C12.4542 8.92126 12.2307 9.001 12 9.001C11.7693 9.001 11.5458 8.92126 11.3671 8.77528C11.1885 8.62929 11.0659 8.42606 11.02 8.2C10.652 6.396 8.957 5 7 5Z" fill="#FF0000" />
                      </svg>
                    </button>
                    <button className="product-page__buy-btn">Купить</button>
                  </div>

                  <p className="product-page__delivery">Доставим завтра</p>
                </div>
              </div>

              <div className="product-page__seller">
                <div className="product-page__seller-header">
                  <img src="/img/products/1/company.png" alt="Логотип магазина" />
                  <h2 className="product-page__seller-name">
                    LabuMarket
                    <span className="product-page__seller-verified">✓</span>
                  </h2>
                </div>

                <div className="product-page__seller-stats">
                  <button className="product-page__seller-chat">

                    <img src="/img/products/reviews-icon.svg" alt="Отзывы" />
                    Написать
                  </button>



                  <div className="product-page__seller-sales">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M7 5C5.93913 5 4.92172 5.42143 4.17157 6.17157C3.42143 6.92172 3 7.93913 3 9C3 12.552 5.218 15.296 7.621 17.22C8.96786 18.2885 10.438 19.1916 12 19.91C13.5608 19.1907 15.0302 18.2876 16.377 17.22C18.78 15.294 21 12.551 21 9C21 7.93913 20.5786 6.92172 19.8284 6.17157C19.0783 5.42143 18.0609 5 17 5C15.043 5 13.348 6.396 12.98 8.2C12.9341 8.42606 12.8115 8.62929 12.6329 8.77528C12.4542 8.92126 12.2307 9.001 12 9.001C11.7693 9.001 11.5458 8.92126 11.3671 8.77528C11.1885 8.62929 11.0659 8.42606 11.02 8.2C10.652 6.396 8.957 5 7 5Z" fill="#FF0202" />
                      <path d="M12 22C11.684 21.98 11.44 21.853 11.152 21.722C9.44651 20.9359 7.84139 19.9482 6.371 18.78C3.777 16.705 1 13.449 1 9C1 7.4087 1.63214 5.88258 2.75736 4.75736C3.88258 3.63214 5.4087 3 7 3C7.97708 3.0023 8.9397 3.23625 9.80885 3.68265C10.678 4.12905 11.4289 4.77517 12 5.568C12.5711 4.77517 13.322 4.12905 14.1911 3.68265C15.0603 3.23625 16.0229 3.0023 17 3C18.5913 3 20.1174 3.63214 21.2426 4.75736C22.3679 5.88258 23 7.4087 23 9C23 13.448 20.22 16.705 17.625 18.78C16.1544 19.9473 14.5497 20.935 12.845 21.722C12.302 21.971 12.113 22 12 22ZM7 5C5.93913 5 4.92172 5.42143 4.17157 6.17157C3.42143 6.92172 3 7.93913 3 9C3 12.552 5.218 15.296 7.621 17.22C8.96786 18.2885 10.438 19.1916 12 19.91C13.5608 19.1907 15.0302 18.2876 16.377 17.22C18.78 15.294 21 12.551 21 9C21 7.93913 20.5786 6.92172 19.8284 6.17157C19.0783 5.42143 18.0609 5 17 5C15.043 5 13.348 6.396 12.98 8.2C12.9341 8.42606 12.8115 8.62929 12.6329 8.77528C12.4542 8.92126 12.2307 9.001 12 9.001C11.7693 9.001 11.5458 8.92126 11.3671 8.77528C11.1885 8.62929 11.0659 8.42606 11.02 8.2C10.652 6.396 8.957 5 7 5Z" fill="#FF0000" />
                    </svg>
                    <span>999k</span>
                  </div>
                  <div className="product-page__seller-rating">
                    <img src="/img/products/star-icon.png" alt="Рейтинг" />
                    <span>5.0</span>
                  </div>
                  <button className="product-page__seller-profile" onClick={ShowSeller}>Посмотреть</button>
                </div>
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