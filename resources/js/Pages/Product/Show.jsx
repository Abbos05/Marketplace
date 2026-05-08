import { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/product/product_page.css';

export default function ProductShow({ auth, product, seller, hasOrdered, existingOrderId }) {
  const [inCart, setInCart] = useState(product.in_cart);
  const [isFavorite, setIsFavorite] = useState(product.is_favorite);
  const [isToggling, setIsToggling] = useState(false);

  const addToCart = () => {
    router.post(route('cart.store'), {
      variant_id: product.variant_id
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

  useEffect(() => {
    setInCart(product.in_cart);
  }, [product.in_cart]);

  useEffect(() => {
    setIsFavorite(product.is_favorite);
  }, [product.is_favorite]);

  const toggleFavorite = () => {
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

  // Функция для оформления заказа
  const buyNow = () => {
    // Проверка на блокировку/верификацию
    if (auth.user.phone) {
      sessionStorage.setItem('flashMessage', 'Пройдите верификацию или вы заблокированы');
      window.location.href = '/profile';
      return;
    }

    // Отправляем товар на оформление заказа
    router.post('/order/create', {
      items: [{
        variant_id: product.variant_id,
        quantity: 1
      }]
    }, {
      preserveState: true,
      onSuccess: () => {
        alert('Заказ успешно оформлен!');
      },  
      onError: (errors) => {
        alert('Ошибка при оформлении заказа');
      }
    });
  };

  // Функция для перехода к существующему заказу
  const goToOrder = () => {
    if (existingOrderId) {
      router.get(`/order/${existingOrderId}`);
    }
  };

  const ShowSeller = () => {
    if (!seller?.id) {
      console.warn('Не удалось получить ID продавца');
      return;
    }

    router.get(
      route('seller.index', seller.id),
      {},
      {
        preserveState: true,
        replace: true,
        preserveScroll: true,
      }
    );
  };

  return (
    <MainLayout auth={auth}>
      <Head title={`Alvora - ${product.title}`} />
      <section className="product-page">
        <div className="product-page__content">
          {/* Левая колонка */}
          <div className="product-page__main">
            <div className="product-page__gallery">
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

              <div className="product-page__thumbnails">
                {product?.gallery?.map((image, index) => (
                  <img key={index} src={image} alt={`${product.title} ${index + 1}`} />
                ))}
              </div>

              <div className="product-page__description">
                <h2 className="product-page__section-title">О товаре</h2>
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

          {/* Правая колонка */}
          <div className="product-page__sidebar">
            <div className="product-page__card">
              <div className="product-page__payment">
                <h3 className="product-page__payment-title">Оплата</h3>

                <div className="product-page__price-block">
                  <div className="product-page__current-price">
                    <div>
                      <h2 className='product-page__price'>
                        {(product.variant_price || 0).toLocaleString()}₽
                      </h2>
                      <span className='product-page__discount-info'> со скидкой</span>
                    </div>
                    <span className="product-page__discount-info">
                      <del>{(product.old_price || 0).toLocaleString()}₽</del> -16%
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
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" className="products__basket">
                        <path fill="currentColor" d="M9.925 5.371a1 1 0 1 0-1.858-.742L6.317 9h-1.2c-1.076 0-1.614 0-1.913.346-.3.346-.222.878-.067 1.942l.271 1.864c.475 3.265.902 4.898 2.03 5.873s2.778.975 6.08.975h.96c3.302 0 4.953 0 6.08-.975 1.128-.975 1.559-2.608 2.034-5.873l.271-1.864c.155-1.064.233-1.596-.067-1.942S19.96 9 18.883 9h-1.205l-1.75-4.371a1 1 0 0 0-1.857.742L15.523 9h-7.05zM10.997 14v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 2 0M14 13a1 1 0 0 1 1 1v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 1-1"></path>
                      </svg>
                    </button>
                    
                    <button className="product-page__wishlist-btn" onClick={toggleFavorite}>
                      {isFavorite ? 'Убрать из' : 'Добавить в'}
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 5C5.93913 5 4.92172 5.42143 4.17157 6.17157C3.42143 6.92172 3 7.93913 3 9C3 12.552 5.218 15.296 7.621 17.22C8.96786 18.2885 10.438 19.1916 12 19.91C13.5608 19.1907 15.0302 18.2876 16.377 17.22C18.78 15.294 21 12.551 21 9C21 7.93913 20.5786 6.92172 19.8284 6.17157C19.0783 5.42143 18.0609 5 17 5C15.043 5 13.348 6.396 12.98 8.2C12.9341 8.42606 12.8115 8.62929 12.6329 8.77528C12.4542 8.92126 12.2307 9.001 12 9.001C11.7693 9.001 11.5458 8.92126 11.3671 8.77528C11.1885 8.62929 11.0659 8.42606 11.02 8.2C10.652 6.396 8.957 5 7 5Z" fill="#FF0202" />
                        <path d="M12 22C11.684 21.98 11.44 21.853 11.152 21.722C9.44651 20.9359 7.84139 19.9482 6.371 18.78C3.777 16.705 1 13.449 1 9C1 7.4087 1.63214 5.88258 2.75736 4.75736C3.88258 3.63214 5.4087 3 7 3C7.97708 3.0023 8.9397 3.23625 9.80885 3.68265C10.678 4.12905 11.4289 4.77517 12 5.568C12.5711 4.77517 13.322 4.12905 14.1911 3.68265C15.0603 3.23625 16.0229 3.0023 17 3C18.5913 3 20.1174 3.63214 21.2426 4.75736C22.3679 5.88258 23 7.4087 23 9C23 13.448 20.22 16.705 17.625 18.78C16.1544 19.9473 14.5497 20.935 12.845 21.722C12.302 21.971 12.113 22 12 22ZM7 5C5.93913 5 4.92172 5.42143 4.17157 6.17157C3.42143 6.92172 3 7.93913 3 9C3 12.552 5.218 15.296 7.621 17.22C8.96786 18.2885 10.438 19.1916 12 19.91C13.5608 19.1907 15.0302 18.2876 16.377 17.22C18.78 15.294 21 12.551 21 9C21 7.93913 20.5786 6.92172 19.8284 6.17157C19.0783 5.42143 18.0609 5 17 5C15.043 5 13.348 6.396 12.98 8.2C12.9341 8.42606 12.8115 8.62929 12.6329 8.77528C12.4542 8.92126 12.2307 9.001 12 9.001C11.7693 9.001 11.5458 8.92126 11.3671 8.77528C11.1885 8.62929 11.0659 8.42606 11.02 8.2C10.652 6.396 8.957 5 7 5Z" fill="#FF0000" />
                      </svg>
                    </button>
                    
                    {hasOrdered ? (
                      <button className="product-page__buy-btn active" onClick={goToOrder}>
                        Перейти к заказу
                      </button>
                    ) : (
                      <button className="product-page__buy-btn" onClick={buyNow}>
                        Купить
                      </button>
                    )}
                  </div>

                  <p className="product-page__delivery">Доставим завтра</p>
                </div>
              </div>

              <div className="product-page__seller">
                <div className="product-page__seller-header">
                  <img src="/img/products/1/company.png" alt="Логотип магазина" />
                  <h2 className="product-page__seller-name">
                    {seller?.name || 'LabuMarket'}
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
    </MainLayout>
  );
}