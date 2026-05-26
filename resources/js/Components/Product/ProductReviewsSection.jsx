import { useMemo, useState } from 'react';

const FILTERS = [
  { id: 'all', label: 'Все' },
  { id: 'with_photos', label: 'С фото' },
  { id: 'without_photos', label: 'Без фото' },
];

function formatReviewsCountRu(n) {
  const abs = Math.abs(n) % 100;
  const d = abs % 10;
  const num = new Intl.NumberFormat('ru-RU').format(n);
  if (abs > 10 && abs < 20) return `${num} отзывов`;
  if (d === 1) return `${num} отзыв`;
  if (d >= 2 && d <= 4) return `${num} отзыва`;
  return `${num} отзывов`;
}

function StarRow({ rating, size = 'md' }) {
  return (
    <span className={`product-reviews__stars product-reviews__stars--${size}`} aria-hidden>
      {[1, 2, 3, 4, 5].map((star) => (
        <span key={star} className={rating >= star ? 'is-on' : ''}>
          ★
        </span>
      ))}
    </span>
  );
}

function ReviewLightbox({ images, index, onClose, onNavigate }) {
  if (!images?.length) return null;
  const current = images[index];

  return (
    <div className="product-reviews-lightbox" role="dialog" aria-modal="true" onClick={onClose}>
      <div className="product-reviews-lightbox__inner" onClick={(e) => e.stopPropagation()}>
        <button type="button" className="product-reviews-lightbox__close" onClick={onClose} aria-label="Закрыть">
          ×
        </button>
        {images.length > 1 && (
          <button
            type="button"
            className="product-reviews-lightbox__nav product-reviews-lightbox__nav--prev"
            onClick={() => onNavigate((index - 1 + images.length) % images.length)}
            aria-label="Предыдущее фото"
          >
            ‹
          </button>
        )}
        <img src={current.url} alt="" className="product-reviews-lightbox__img" />
        {images.length > 1 && (
          <button
            type="button"
            className="product-reviews-lightbox__nav product-reviews-lightbox__nav--next"
            onClick={() => onNavigate((index + 1) % images.length)}
            aria-label="Следующее фото"
          >
            ›
          </button>
        )}
        {images.length > 1 && (
          <div className="product-reviews-lightbox__counter">
            {index + 1} / {images.length}
          </div>
        )}
      </div>
    </div>
  );
}

function ReviewCard({ review, auth, onVote }) {
  const [expanded, setExpanded] = useState(false);
  const [lightbox, setLightbox] = useState(null);
  const images = review.images ?? [];
  const comment = review.comment?.trim() ?? '';
  const longComment = comment.length > 280;
  const displayComment = expanded || !longComment ? comment : `${comment.slice(0, 280)}…`;

  const openLightbox = (idx) => setLightbox({ images, index: idx });

  return (
    <li className="product-page__review-card product-reviews__card">
      <div className="product-page__review-top">
        <div className="product-page__review-user">
          <div className="product-page__review-avatar">
            {review.user_avatar ? (
              <img src={review.user_avatar} alt="" />
            ) : (
              <span>{(review.user_name || '?').charAt(0).toUpperCase()}</span>
            )}
          </div>
          <div>
            <div className="product-page__review-name">{review.user_name}</div>
            <div className="product-page__review-meta">{review.created_at}</div>
          </div>
        </div>
        <div className="product-page__review-score" aria-label={`Оценка ${review.rating}`}>
          <StarRow rating={review.rating} />
          <span className="product-page__review-score-num">{review.rating}/5</span>
        </div>
      </div>

      {images.length > 0 && (
        <div className="product-reviews__photos">
          {images.map((img, idx) => (
            <button
              key={img.id ?? idx}
              type="button"
              className="product-reviews__photo-thumb"
              onClick={() => openLightbox(idx)}
            >
              <img src={img.url} alt="" />
            </button>
          ))}
        </div>
      )}

      {comment ? (
        <p className="product-page__review-text">
          {displayComment}
          {longComment && !expanded && (
            <button type="button" className="product-reviews__read-more" onClick={() => setExpanded(true)}>
              Читать полностью
            </button>
          )}
        </p>
      ) : (
        <p className="product-page__review-text product-page__review-text--muted">Без текста</p>
      )}

      <div className="product-page__review-likes">
        <button
          type="button"
          className={`product-page__vote-btn${review.user_vote === 'helpful' ? ' is-active' : ''}`}
          onClick={() => onVote(review.id, 'helpful')}
          disabled={!auth?.user}
          title={auth?.user ? 'Отметить как полезный' : 'Войдите, чтобы оценить'}
        >
          👍 Полезно · {new Intl.NumberFormat('ru-RU').format(review.likes_count ?? 0)}
        </button>
        <button
          type="button"
          className={`product-page__vote-btn product-page__vote-btn--down${review.user_vote === 'unhelpful' ? ' is-active' : ''}`}
          onClick={() => onVote(review.id, 'unhelpful')}
          disabled={!auth?.user}
          title={auth?.user ? 'Отметить как неполезный' : 'Войдите, чтобы оценить'}
        >
          👎 Нет · {new Intl.NumberFormat('ru-RU').format(review.dislikes_count ?? 0)}
        </button>
      </div>

      {lightbox && (
        <ReviewLightbox
          images={lightbox.images}
          index={lightbox.index}
          onClose={() => setLightbox(null)}
          onNavigate={(idx) => setLightbox({ images: lightbox.images, index: idx })}
        />
      )}
    </li>
  );
}

export default function ProductReviewsSection({
  reviews = [],
  avgRating = null,
  total = 0,
  distribution = {},
  withPhotosCount = 0,
  auth,
  onVote,
}) {
  const [filter, setFilter] = useState('all');
  const [sort, setSort] = useState('newest');

  const filtered = useMemo(() => {
    let list = [...reviews];
    if (filter === 'with_photos') {
      list = list.filter((r) => r.has_photos);
    } else if (filter === 'without_photos') {
      list = list.filter((r) => !r.has_photos);
    }
    if (sort === 'rating_high') {
      list.sort((a, b) => b.rating - a.rating || b.id - a.id);
    } else if (sort === 'rating_low') {
      list.sort((a, b) => a.rating - b.rating || b.id - a.id);
    }
    return list;
  }, [reviews, filter, sort]);

  const avgDisplay = total === 0 ? '0.0' : (avgRating != null ? Number(avgRating).toFixed(1) : '0.0');
  const maxBar = Math.max(1, ...[5, 4, 3, 2, 1].map((s) => Number(distribution[String(s)] ?? 0)));

  return (
    <div className="product-page__reviews-ozon product-reviews" id="product-reviews">
      <div className="product-page__reviews-head">
        <h2 className="product-page__section-title">Отзывы</h2>
        <p className="product-page__reviews-sub">
          Показаны отзывы, прошедшие модерацию. Оставить отзыв можно в разделе «Мои заказы» после получения товара.
        </p>
      </div>

      {total === 0 ? (
        <div className="product-page__reviews-empty">
          <p>Пока нет отзывов на этот товар.</p>
          <span>Когда покупатели оставят оценки, они появятся в этом блоке.</span>
        </div>
      ) : (
        <>
          <div className="product-reviews__summary">
            <div className="product-reviews__summary-score">
              <div className="product-reviews__avg">{avgDisplay}</div>
              <StarRow rating={Math.round(Number(avgDisplay))} size="lg" />
              <div className="product-reviews__total">{formatReviewsCountRu(total)}</div>
            </div>
            <div className="product-reviews__histogram">
              {[5, 4, 3, 2, 1].map((star) => {
                const count = Number(distribution[String(star)] ?? 0);
                const pct = total > 0 ? Math.round((count / total) * 100) : 0;
                return (
                  <div key={star} className="product-reviews__bar-row">
                    <span className="product-reviews__bar-label">{star} ★</span>
                    <div className="product-reviews__bar-track">
                      <div
                        className="product-reviews__bar-fill"
                        style={{ width: `${(count / maxBar) * 100}%` }}
                      />
                    </div>
                    <span className="product-reviews__bar-pct">{pct}%</span>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="product-reviews__toolbar">
            <div className="product-reviews__filters">
              {FILTERS.map((f) => (
                <button
                  key={f.id}
                  type="button"
                  className={`product-reviews__chip${filter === f.id ? ' is-active' : ''}`}
                  onClick={() => setFilter(f.id)}
                >
                  {f.label}
                  {f.id === 'with_photos' && withPhotosCount > 0 ? ` (${withPhotosCount})` : ''}
                </button>
              ))}
            </div>
            <select
              className="product-reviews__sort"
              value={sort}
              onChange={(e) => setSort(e.target.value)}
              aria-label="Сортировка отзывов"
            >
              <option value="newest">Сначала новые</option>
              <option value="rating_high">С высокой оценкой</option>
              <option value="rating_low">С низкой оценкой</option>
            </select>
          </div>

          {filtered.length === 0 ? (
            <div className="product-page__reviews-empty product-reviews__empty-filter">
              <p>Нет отзывов по выбранному фильтру.</p>
            </div>
          ) : (
            <ul className="product-page__reviews-list">
              {filtered.map((r) => (
                <ReviewCard key={r.id} review={r} auth={auth} onVote={onVote} />
              ))}
            </ul>
          )}
        </>
      )}
    </div>
  );
}
