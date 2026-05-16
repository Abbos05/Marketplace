import { useState, useEffect, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/product/product_page.css';

function IconStar({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 24" width="22" height="22" aria-hidden>
      <path
        fill="currentColor"
        d="M12 3.5l2.6 5.3 5.8.8-4.2 4.1 1 5.8L12 16.9 6.8 19.5l1-5.8-4.2-4.1 5.8-.8L12 3.5z"
      />
    </svg>
  );
}

function IconReviews({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 24" width="22" height="22" aria-hidden>
      <path
        fill="none"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinejoin="round"
        d="M7 8h10M7 12h7M7 16h5M5 4h14a1 1 0 011 1v12l-3-2H5a1 1 0 01-1-1V5a1 1 0 011-1z"
      />
    </svg>
  );
}

function IconHeartOutline({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 24" width="24" height="24" aria-hidden>
      <path
        fill="none"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.27 2 8.5 2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.41 22 8.5c0 3.77-3.4 6.86-8.55 11.54L12 21.35z"
      />
    </svg>
  );
}

function IconHeartSolid({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 24" width="24" height="24" aria-hidden>
      <path
        fill="currentColor"
        d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.27 2 8.5 2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.41 22 8.5c0 3.77-3.4 6.86-8.55 11.54L12 21.35z"
      />
    </svg>
  );
}

function IconChat({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 24" width="22" height="22" aria-hidden>
      <path
        fill="none"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinejoin="round"
        d="M5 5h14a1 1 0 011 1v9a1 1 0 01-1 1h-6l-4 3v-3H5a1 1 0 01-1-1V6a1 1 0 011-1z"
      />
    </svg>
  );
}

function formatRub(n) {
  const v = Number(n);
  if (Number.isNaN(v)) return '—';
  return `${new Intl.NumberFormat('ru-RU').format(Math.round(v))} ₽`;
}

function formatCompact(n) {
  const v = Number(n);
  if (Number.isNaN(v)) return '0';
  if (v >= 1_000_000) return `${(v / 1_000_000).toFixed(1).replace(/\.0$/, '')}M`;
  if (v >= 1_000) return `${(v / 1_000).toFixed(1).replace(/\.0$/, '')}k`;
  return String(v);
}

function formatReviewsCountRu(n) {
  const abs = Math.abs(n) % 100;
  const d = abs % 10;
  const num = new Intl.NumberFormat('ru-RU').format(n);
  if (abs > 10 && abs < 20) return `${num} отзывов`;
  if (d === 1) return `${num} отзыв`;
  if (d >= 2 && d <= 4) return `${num} отзыва`;
  return `${num} отзывов`;
}

export default function ProductShow({
  auth,
  product,
  seller,
  hasOrdered,
  existingOrderId,
  pickupPoints = [],
  flash,
}) {
  const gallery = useMemo(() => {
    const g = product.gallery;
    if (Array.isArray(g) && g.length > 0) return g;
    if (product.image) return [product.image];
    return ['/img/products/default.png'];
  }, [product.gallery, product.image]);

  const [activeImage, setActiveImage] = useState(gallery[0] ?? '/img/products/default.png');
  const [inCart, setInCart] = useState(!!product.in_cart);
  const [isFavorite, setIsFavorite] = useState(!!product.is_favorite);
  const [isToggling, setIsToggling] = useState(false);
  const [checkoutPickupId, setCheckoutPickupId] = useState(
    () => auth?.user?.default_pickup_point_id ?? ''
  );
  const [detailTab, setDetailTab] = useState('description');

  useEffect(() => {
    setCheckoutPickupId(auth?.user?.default_pickup_point_id ?? '');
  }, [auth?.user?.default_pickup_point_id]);

  useEffect(() => {
    setActiveImage(gallery[0] ?? '/img/products/default.png');
  }, [gallery, product.selected_variant_id]);

  useEffect(() => {
    setInCart(!!product.in_cart);
  }, [product.in_cart]);

  useEffect(() => {
    setIsFavorite(!!product.is_favorite);
  }, [product.is_favorite]);

  const purchaseBreakdown = product.purchase_breakdown ?? null;

  const baseReviewsList = Array.isArray(product.reviews_list) ? product.reviews_list : [];
  const [reviewOverrides, setReviewOverrides] = useState({});

  useEffect(() => {
    const patch = flash?.review_vote;
    if (!patch?.review_id) return;

    setReviewOverrides((prev) => ({
      ...prev,
      [patch.review_id]: {
        likes_count: patch.likes_count,
        dislikes_count: patch.dislikes_count,
        user_vote: patch.user_vote,
      },
    }));
  }, [flash?.review_vote]);

  useEffect(() => {
    setReviewOverrides({});
  }, [product.reviews_list]);

  const reviewsList = baseReviewsList.map((r) => {
    const patch = reviewOverrides[r.id];
    if (!patch) return r;
    return { ...r, ...patch };
  });
  const specs = Array.isArray(product.specs) ? product.specs : [];

  const ratingAvg =
    product.reviews_avg_rating != null ? Number(product.reviews_avg_rating).toFixed(1) : null;
  const reviewsTotal = Number(product.reviews_total ?? 0);
  const ratingDisplayShort = reviewsTotal === 0 ? '—' : ratingAvg ?? '—';

  const addToCart = () => {
    if (!auth?.user) {
      router.visit(route('login'));
      return;
    }
    if (!product.variant_id) return;
    router.post(
      route('cart.store'),
      { variant_id: product.variant_id },
      {
        preserveScroll: true,
        onSuccess: () => setInCart(true),
      }
    );
  };

  const removeFromCart = () => {
    if (!auth?.user || !product.variant_id) return;
    router.delete(route('cart.destroy', product.variant_id), {
      preserveScroll: true,
      onSuccess: () => setInCart(false),
    });
  };

  const toggleFavorite = () => {
    if (!auth?.user) {
      router.visit(route('login'));
      return;
    }
    if (isToggling || !product?.id) return;

    const previousValue = isFavorite;
    const newValue = !isFavorite;

    setIsToggling(true);
    setIsFavorite(newValue);

    router.post(
      route('favorites.toggle', product.id),
      {},
      {
        preserveState: true,
        preserveScroll: true,
        onError: () => {
          setIsFavorite(previousValue);
          alert('Ошибка, попробуйте позже');
        },
        onFinish: () => setIsToggling(false),
      }
    );
  };

  const buyNow = () => {
    if (!auth?.user) {
      router.visit(route('login'));
      return;
    }
    if (auth.user.is_blocked) {
      alert('Доступ ограничен. Обратитесь в поддержку.');
      return;
    }
    if (!auth.user.phone) {
      router.visit(route('profile'));
      return;
    }
    if (!product.variant_id) {
      alert('Нет доступного варианта для заказа');
      return;
    }

    const pickupId = checkoutPickupId || auth?.user?.default_pickup_point_id;
    if (!pickupId) {
      alert('Выберите пункт выдачи в профиле или в списке ниже.');
      return;
    }

    router.post(
      route('order.create'),
      {
        items: [{ variant_id: product.variant_id, quantity: 1 }],
        promo_code: null,
        pickup_point_id: pickupId,
      },
      { preserveScroll: true }
    );
  };

  const goToOrder = () => {
    if (existingOrderId) {
      router.get(route('order.show', existingOrderId));
    }
  };

  const showSeller = () => {
    if (!seller?.id) return;
    router.get(route('seller.index', seller.id), {}, { preserveScroll: true });
  };

  const shortDesc = (product.lead_text ?? product.short_description)?.trim();
  const longDesc = product.description?.trim();
  const variantStock = Number(product.variant_stock ?? 0);

  const variantsCatalog = Array.isArray(product.variants_catalog)
    ? product.variants_catalog
    : [];

  const displayTitle = product.display_title ?? product.title;
  const variantLabel = product.variant_label ?? '';

  const selectedVariantId =
    product.selected_variant_id ?? product.variant_id ?? null;

  const pickVariant = (id) => {
    if (id === selectedVariantId) return;
    router.get(
      route('product.show', product.id),
      { variant: id },
      {
        preserveScroll: true,
        only: [
          'product',
          'seller',
          'auth',
          'hasOrdered',
          'existingOrderId',
          'canPayWithWallet',
          'walletBalance',
          'nftUser',
          'flash',
          'pickupPoints',
        ],
      }
    );
  };

  const voteReview = (reviewId, vote) => {
    if (!auth?.user) {
      router.visit(route('login'));
      return;
    }
    router.post(
      route('reviews.vote', reviewId),
      { vote },
      {
        preserveScroll: true,
        only: ['product', 'flash'],
      }
    );
  };

  const sellerRating =
    seller?.rating != null && !Number.isNaN(Number(seller.rating))
      ? Number(seller.rating).toFixed(1)
      : null;

  const sellerAvatar = seller?.avatar || '/img/products/1/company.png';

  return (
    <MainLayout auth={auth}>
      <Head title={`${product.title} — Alvora`} />
      <section className="product-page">
        <div className="product-page__content">
          <div className="product-page__left">
            <div className="product-page__main">
              <div className="product-page__gallery">
                <div className="product-page__main-image">
                  <img src={activeImage} alt={product.title} />
                </div>
              </div>

              <div className="product-page__info">
                <h1 className="product-page__title">{displayTitle}</h1>
                <div className="product-page__rating">
                  <div className="product-page__stars">
                    <IconStar className="product-page__ui-icon product-page__ui-icon--amber" />
                    <span>{ratingDisplayShort}</span>
                  </div>
                  <div className="product-page__reviews-count">
                    <IconReviews className="product-page__ui-icon" />
                    <span>{formatReviewsCountRu(reviewsTotal)}</span>
                  </div>
                </div>
                {gallery.length > 1 && (
                  <div className="product-page__thumbnails">
                    {gallery.map((src, index) => (
                      <img
                        key={`${src}-${index}`}
                        src={src}
                        alt=""
                        className={
                          src === activeImage
                            ? 'product-page__thumb product-page__thumb--active'
                            : 'product-page__thumb'
                        }
                        onClick={() => setActiveImage(src)}
                      />
                    ))}
                  </div>
                )}
                {variantsCatalog.length > 1 ? (
                  <div className="product-page__variant-wrap">
                    <p className="product-page__variant-title">Вариант</p>
                    <div className="product-page__variant-chips" role="list">
                      {variantsCatalog.map((row) => {
                        const active = row.id === selectedVariantId;
                        return (
                          <button
                            key={row.id}
                            type="button"
                            role="listitem"
                            className={
                              active
                                ? 'product-page__variant-chip is-active'
                                : 'product-page__variant-chip'
                            }
                            onClick={() => pickVariant(row.id)}
                          >
                            <img
                              className="product-page__variant-chip-image"
                              src={row.image || '/img/products/default.png'}
                              alt=""
                            />
                            <span className="product-page__variant-chip-meta">
                              <span className="product-page__variant-chip-label">{row.label}</span>
                              <span className="product-page__variant-chip-price">{formatRub(row.price)}</span>
                            </span>
                          </button>
                        );
                      })}
                    </div>
                  </div>
                ) : null}

                {variantLabel && variantsCatalog.length > 1 && (
                  <p className="product-page__variant-selected">
                    Выбрано: <strong>{variantLabel}</strong>
                  </p>
                )}

                <div className="product-page__description">
                  <h2 className="product-page__section-title">Кратко</h2>
                  <p className="product-page__lead">
                    {shortDesc ||
                      'Краткого описания нет — ниже полный текст карточки, если продавец его заполнил.'}
                  </p>
                </div>

              </div>

            </div>
            <div className="product-page__below">
              <div className="product-page__full-block">
                <div className="product-page__detail-head">
                  <h2 className="product-page__section-title product-page__detail-head-title">
                    {detailTab === 'description' ? 'Полное описание' : 'Характеристики'}
                  </h2>
                  {specs.length > 0 ? (
                    <button
                      type="button"
                      className={`product-page__detail-switch${detailTab === 'specs' ? ' is-active' : ''}`}
                      onClick={() =>
                        setDetailTab((t) => (t === 'description' ? 'specs' : 'description'))
                      }
                    >
                      {detailTab === 'description' ? 'Характеристики' : 'Полное описание'}
                    </button>
                  ) : null}
                </div>

                {detailTab === 'description' ? (
                  longDesc ? (
                    <div className="product-page__full-text">
                      {longDesc
                        .split(/\n\s*\n/)
                        .map((p) => p.trim())
                        .filter(Boolean)
                        .map((para, i) => (
                          <p key={i} className="product-page__para">
                            {para.split(/\r?\n/).map((line, j, arr) => (
                              <span key={j}>
                                {line}
                                {j < arr.length - 1 ? <br /> : null}
                              </span>
                            ))}
                          </p>
                        ))}
                    </div>
                  ) : (
                    <p className="product-page__empty-note">
                      Продавец ещё не добавил подробное описание.
                    </p>
                  )
                ) : (
                  <div className="product-page__specs">
                    <table>
                      <tbody>
                        {specs.map((row) => (
                          <tr key={row.name + row.value}>
                            <th>{row.name}</th>
                            <td>{row.value}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>

              <div className="product-page__reviews-ozon" id="product-reviews">
                <div className="product-page__reviews-head">
                  <h2 className="product-page__section-title">Отзывы</h2>
                  <p className="product-page__reviews-sub">
                    Показаны отзывы, прошедшие модерацию. Оставить отзыв здесь нельзя — только просмотр.
                  </p>
                </div>

                {reviewsList.length === 0 ? (
                  <div className="product-page__reviews-empty">
                    <p>Пока нет отзывов на этот товар.</p>
                    <span>Когда покупатели оставят оценки, они появятся в этом блоке.</span>
                  </div>
                ) : (
                  <ul className="product-page__reviews-list">
                    {reviewsList.map((r) => (
                      <li key={r.id} className="product-page__review-card">
                        <div className="product-page__review-top">
                          <div className="product-page__review-user">
                            <div className="product-page__review-avatar">
                              {r.user_avatar ? (
                                <img src={r.user_avatar} alt="" />
                              ) : (
                                <span>{(r.user_name || '?').charAt(0).toUpperCase()}</span>
                              )}
                            </div>
                            <div>
                              <div className="product-page__review-name">{r.user_name}</div>
                              <div className="product-page__review-meta">{r.created_at}</div>
                            </div>
                          </div>
                          <div className="product-page__review-score" aria-label={`Оценка ${r.rating}`}>
                            {'★'.repeat(r.rating)}
                            <span className="product-page__review-score-num">{r.rating}/5</span>
                          </div>
                        </div>
                        {r.comment ? (
                          <p className="product-page__review-text">{r.comment}</p>
                        ) : (
                          <p className="product-page__review-text product-page__review-text--muted">
                            Без текста
                          </p>
                        )}
                        <div className="product-page__review-likes">
                          <button
                            type="button"
                            className={`product-page__vote-btn${r.user_vote === 'helpful' ? ' is-active' : ''}`}
                            onClick={() => voteReview(r.id, 'helpful')}
                            disabled={!auth?.user}
                            title={auth?.user ? 'Отметить как полезный' : 'Войдите, чтобы оценить'}
                          >
                            👍 Полезно · {new Intl.NumberFormat('ru-RU').format(r.likes_count ?? 0)}
                          </button>
                          <button
                            type="button"
                            className={`product-page__vote-btn product-page__vote-btn--down${r.user_vote === 'unhelpful' ? ' is-active' : ''}`}
                            onClick={() => voteReview(r.id, 'unhelpful')}
                            disabled={!auth?.user}
                            title={auth?.user ? 'Отметить как неполезный' : 'Войдите, чтобы оценить'}
                          >
                            👎 Нет · {new Intl.NumberFormat('ru-RU').format(r.dislikes_count ?? 0)}
                          </button>
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </div>
          <div className="product-page__sidebar">
            <div className="product-page__card">

              <div className="product-page__payment">
                <h3 className="product-page__payment-title">Оплата</h3>

                <div className="product-page__price-block">
                  <div className="product-page__current-price">
                    <div>
                      <h2 className="product-page__price">
                        {formatRub(product.variant_price ?? 0)}
                      </h2>
                      {product.variant_old_price &&
                        Number(product.variant_old_price) >
                        Number(product.variant_price ?? 0) ? (
                        <span className="product-page__discount-info">со скидкой</span>
                      ) : null}
                    </div>
                    {product.variant_old_price &&
                      Number(product.variant_old_price) >
                      Number(product.variant_price ?? 0) ? (
                      <span className="product-page__discount-info">
                        <del>{formatRub(product.variant_old_price)}</del>
                        {product.discount_label_percent != null ? (
                          <span> −{product.discount_label_percent}%</span>
                        ) : null}
                      </span>
                    ) : null}
                  </div>

                  {auth?.user && Array.isArray(pickupPoints) && pickupPoints.length > 0 ? (
                    <div style={{ marginBottom: 14 }}>
                      <label
                        htmlFor="product-checkout-pickup"
                        style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 6, color: '#475569' }}
                      >
                        Пункт выдачи
                      </label>
                      <select
                        id="product-checkout-pickup"
                        className="product-page__credit-btn"
                        style={{ width: '100%', cursor: 'pointer', textAlign: 'left' }}
                        value={checkoutPickupId === '' || checkoutPickupId == null ? '' : String(checkoutPickupId)}
                        onChange={(e) =>
                          setCheckoutPickupId(e.target.value === '' ? '' : Number(e.target.value))
                        }
                      >
                        {!auth.user.default_pickup_point_id ? (
                          <option value="">Выберите ПВЗ</option>
                        ) : null}
                        {pickupPoints.map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.label}
                          </option>
                        ))}
                      </select>
                    </div>
                  ) : null}

                  <div className="product-page__credit">
                    <button
                      type="button"
                      className="product-page__credit-btn"
                      onClick={() => alert('Сервис «Оплатить позже» в разработке')}
                    >
                      Оплатить позже
                    </button>
                    <span className="product-page__credit-hint">без % в месяц</span>
                  </div>

                  <div className="product-page__actions">
                    <button
                      type="button"
                      className="product-page__cart-btn"
                      onClick={inCart ? removeFromCart : addToCart}
                    >
                      {inCart ? 'Убрать из' : 'В корзину'}
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        className="products__basket"
                      >
                        <path
                          fill="currentColor"
                          d="M9.925 5.371a1 1 0 1 0-1.858-.742L6.317 9h-1.2c-1.076 0-1.614 0-1.913.346-.3.346-.222.878-.067 1.942l.271 1.864c.475 3.265.902 4.898 2.03 5.873s2.778.975 6.08.975h.96c3.302 0 4.953 0 6.08-.975 1.128-.975 1.559-2.608 2.034-5.873l.271-1.864c.155-1.064.233-1.596-.067-1.942S19.96 9 18.883 9h-1.205l-1.75-4.371a1 1 0 0 0-1.857.742L15.523 9h-7.05zM10.997 14v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 2 0M14 13a1 1 0 0 1 1 1v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 1-1"
                        />
                      </svg>
                    </button>

                    <button type="button" className="product-page__wishlist-btn" onClick={toggleFavorite}>
                      {isFavorite ? 'В избранном' : 'В избранное'}
                      {isFavorite ? (
                        <IconHeartSolid className="product-page__ui-icon product-page__ui-icon--heart" />
                      ) : (
                        <IconHeartOutline className="product-page__ui-icon product-page__ui-icon--heart" />
                      )}
                    </button>

                    {hasOrdered ? (
                      <button type="button" className="product-page__buy-btn active" onClick={goToOrder}>
                        Перейти к заказу
                      </button>
                    ) : (
                      <button type="button" className="product-page__buy-btn" onClick={buyNow}>
                        Купить
                      </button>
                    )}
                  </div>

                  <p className="product-page__stock-hint">
                    В наличии:{' '}
                    <strong>
                      {variantStock > 0
                        ? `${new Intl.NumberFormat('ru-RU').format(variantStock)} шт.`
                        : '0'}
                    </strong>
                  </p>
                </div>
              </div>

              <div className="product-page__seller">
                <div className="product-page__seller-header">
                  <img src={sellerAvatar} alt="" />
                  <h2 className="product-page__seller-name">
                  {seller?.name?.split(' ')[0] || 'Продавец'}
                  {seller?.verified ? (
                      <span className="product-page__seller-verified" title="Профиль магазина">
                        ✓
                      </span>
                    ) : null}
                  </h2>
                </div>

                <div className="product-page__seller-stats">
                  <button
                    type="button"
                    className="product-page__seller-chat"
                    onClick={() => {
                      if (!auth?.user) {
                        router.visit(route('login'));
                        return;
                      }
                      if (seller?.id && auth.user.id === seller.id) {
                        alert('Это ваш товар');
                        return;
                      }
                      router.post(route('messages.open'), {
                        type: 'seller_product',
                        product_id: product.id,
                      });
                    }}
                  >
                    <IconChat className="product-page__ui-icon product-page__ui-icon--on-green" />
                    Написать
                  </button>

                  <div className="product-page__seller-sales" title="Продажи продавца">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path
                        d="M7 5C5.93913 5 4.92172 5.42143 4.17157 6.17157C3.42143 6.92172 3 7.93913 3 9C3 12.552 5.218 15.296 7.621 17.22C8.96786 18.2885 10.438 19.1916 12 19.91C13.5608 19.1907 15.0302 18.2876 16.377 17.22C18.78 15.294 21 12.551 21 9C21 7.93913 20.5786 6.92172 19.8284 6.17157C19.0783 5.42143 18.0609 5 17 5C15.043 5 13.348 6.396 12.98 8.2C12.9341 8.42606 12.8115 8.62929 12.6329 8.77528C12.4542 8.92126 12.2307 9.001 12 9.001C11.7693 9.001 11.5458 8.92126 11.3671 8.77528C11.1885 8.62929 11.0659 8.42606 11.02 8.2C10.652 6.396 8.957 5 7 5Z"
                        fill="#FF0202"
                      />
                      <path
                        d="M12 22C11.684 21.98 11.44 21.853 11.152 21.722C9.44651 20.9359 7.84139 19.9482 6.371 18.78C3.777 16.705 1 13.449 1 9C1 7.4087 1.63214 5.88258 2.75736 4.75736C3.88258 3.63214 5.4087 3 7 3C7.97708 3.0023 8.9397 3.23625 9.80885 3.68265C10.678 4.12905 11.4289 4.77517 12 5.568C12.5711 4.77517 13.322 4.12905 14.1911 3.68265C15.0603 3.23625 16.0229 3.0023 17 3C18.5913 3 20.1174 3.63214 21.2426 4.75736C22.3679 5.88258 23 7.4087 23 9C23 13.448 20.22 16.705 17.625 18.78C16.1544 19.9473 14.5497 20.935 12.845 21.722C12.302 21.971 12.113 22 12 22ZM7 5C5.93913 5 4.92172 5.42143 4.17157 6.17157C3.42143 6.92172 3 7.93913 3 9C3 12.552 5.218 15.296 7.621 17.22C8.96786 18.2885 10.438 19.1916 12 19.91C13.5608 19.1907 15.0302 18.2876 16.377 17.22C18.78 15.294 21 12.551 21 9C21 7.93913 20.5786 6.92172 19.8284 6.17157C19.0783 5.42143 18.0609 5 17 5C15.043 5 13.348 6.396 12.98 8.2C12.9341 8.42606 12.8115 8.62929 12.6329 8.77528C12.4542 8.92126 12.2307 9.001 12 9.001C11.7693 9.001 11.5458 8.92126 11.3671 8.77528C11.1885 8.62929 11.0659 8.42606 11.02 8.2C10.652 6.396 8.957 5 7 5Z"
                        fill="#FF0000"
                      />
                    </svg>
                    <span>{formatCompact(seller?.total_sales ?? 0)}</span>
                  </div>
                  <div className="product-page__seller-rating">
                    <IconStar className="product-page__ui-icon product-page__ui-icon--amber" />
                    <span>{sellerRating ?? '—'}</span>
                  </div>
                  <button type="button" className="product-page__seller-profile" onClick={showSeller}>
                    Посмотреть
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>


      </section>
    </MainLayout>
  );
}
