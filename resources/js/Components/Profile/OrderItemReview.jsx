import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import ConfirmModal from '@/Components/ConfirmModal';

const MAX_PHOTOS = 5;

function statusLabel(review) {
  if (!review) return null;
  if (review.moderation_status === 'rejected') return { text: 'Отклонён', className: 'rejected' };
  if (review.is_moderated || review.moderation_status === 'published') {
    return { text: 'Опубликован', className: 'ok' };
  }
  return { text: 'На модерации', className: 'pending' };
}

export default function OrderItemReview({ item, orderId }) {
  const existingReview = item.review;
  const canReview = !!item.can_review;
  const unavailableReason = item.review_unavailable_reason;

  const [open, setOpen] = useState(false);
  const [rating, setRating] = useState(existingReview?.rating || 0);
  const [comment, setComment] = useState(existingReview?.comment || '');
  const [hover, setHover] = useState(0);
  const [newPhotos, setNewPhotos] = useState([]);
  const [keepImageIds, setKeepImageIds] = useState(
    () => (existingReview?.images ?? []).map((img) => img.id)
  );
  const [submitting, setSubmitting] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const fileInputRef = useRef(null);

  const existingImages = existingReview?.images ?? [];
  const keptExisting = existingImages.filter((img) => keepImageIds.includes(img.id));
  const totalPhotos = keptExisting.length + newPhotos.length;
  const canAddMore = totalPhotos < MAX_PHOTOS;

  const handlePhotoPick = (e) => {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;
    const slots = MAX_PHOTOS - totalPhotos;
    const next = files.slice(0, Math.max(0, slots));
    setNewPhotos((prev) => [...prev, ...next].slice(0, MAX_PHOTOS - keptExisting.length));
    e.target.value = '';
  };

  const removeNewPhoto = (index) => {
    setNewPhotos((prev) => prev.filter((_, i) => i !== index));
  };

  const removeKeptPhoto = (id) => {
    setKeepImageIds((prev) => prev.filter((x) => x !== id));
  };

  const buildFormData = () => {
    const fd = new FormData();
    fd.append('rating', String(rating));
    fd.append('comment', comment || '');
    keepImageIds.forEach((id) => fd.append('keep_image_ids[]', String(id)));
    newPhotos.forEach((file) => fd.append('photos[]', file));
    return fd;
  };

  const handleSave = () => {
    if (rating < 1) return;
    if (submitting) return;
    setSubmitting(true);

    const productId = item.variant?.product?.id ?? item.variant?.product_id;
    const variantId = item.variant?.id ?? item.variant_id;

    if (existingReview) {
      const fd = buildFormData();
      fd.append('_method', 'PUT');
      router.post(`/reviews/${existingReview.id}`, fd, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
          setSubmitting(false);
          setOpen(false);
          setNewPhotos([]);
        },
        onError: () => setSubmitting(false),
      });
    } else {
      const fd = buildFormData();
      fd.append('product_id', String(productId));
      fd.append('variant_id', String(variantId));
      fd.append('order_id', String(orderId));
      router.post('/reviews', fd, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
          setSubmitting(false);
          setOpen(false);
          setNewPhotos([]);
        },
        onError: () => setSubmitting(false),
      });
    }
  };

  const handleDelete = () => {
    if (!existingReview) return;
    setSubmitting(true);
    router.delete(`/reviews/${existingReview.id}`, {
      preserveScroll: true,
      onFinish: () => {
        setSubmitting(false);
        setOpen(false);
        setConfirmDelete(false);
      },
      onError: () => setSubmitting(false),
    });
  };

  const status = statusLabel(existingReview);
  const showReviewBlock = existingReview || canReview || unavailableReason;

  if (!showReviewBlock) {
    return null;
  }

  return (
    <div className="order-item-review">
      {!existingReview && !canReview && unavailableReason && (
        <p className="order-item-review__hint">{unavailableReason}</p>
      )}

      {existingReview && !open && (
        <div className="review-view order-item-review__view">
          <div className="review-top">
            <div className="review-left">
              {status && (
                <div className={`review-status order-item-review__status ${status.className}`}>
                  {status.text}
                </div>
              )}
            </div>
            <div className="review-right">
              <div className="review-stars" aria-hidden>
                {[1, 2, 3, 4, 5].map((star) => (
                  <span
                    key={star}
                    className={`star star-public ${existingReview.rating >= star ? 'active' : ''}`}
                  >
                    ★
                  </span>
                ))}
              </div>
              {canReview && (
                <button type="button" className="review-open-btn" onClick={() => setOpen(true)}>
                  Редактировать
                </button>
              )}
            </div>
          </div>

          {existingReview.moderation_status === 'rejected' && existingReview.moderation_comment && (
            <div className="order-item-review__reject">
              <strong>Причина отклонения:</strong> {existingReview.moderation_comment}
            </div>
          )}

          {existingReview.comment && <div className="review-text">{existingReview.comment}</div>}

          {existingImages.length > 0 && (
            <div className="order-item-review__gallery">
              {existingImages.map((img) => (
                <a key={img.id} href={img.url} target="_blank" rel="noreferrer" className="order-item-review__thumb">
                  <img src={img.url} alt="" />
                </a>
              ))}
            </div>
          )}
        </div>
      )}

      {!existingReview && canReview && !open && (
        <button type="button" className="review-open-btn order-item-review__cta" onClick={() => setOpen(true)}>
          Оставить отзыв
        </button>
      )}

      {open && (
        <div className="review-box order-item-review__form">
          <p className="order-item-review__form-title">
            {existingReview ? 'Редактирование отзыва' : 'Новый отзыв'}
          </p>

          <div className="review-stars">
            {[1, 2, 3, 4, 5].map((star) => (
              <button
                key={star}
                type="button"
                className={`star star-btn ${(hover || rating) >= star ? 'active' : ''}`}
                onMouseEnter={() => setHover(star)}
                onMouseLeave={() => setHover(0)}
                onClick={() => setRating(star)}
                aria-label={`Оценка ${star}`}
              >
                ★
              </button>
            ))}
          </div>
          {rating < 1 && <p className="order-item-review__error-inline">Выберите оценку от 1 до 5</p>}

          <textarea
            className="review-textarea"
            placeholder="Расскажите о товаре (необязательно)"
            value={comment}
            onChange={(e) => {
              setComment(e.target.value);
              e.target.style.height = 'auto';
              e.target.style.height = `${e.target.scrollHeight}px`;
            }}
          />

          <div className="order-item-review__photos-block">
            <div className="order-item-review__photos-head">
              <span>Фото к отзыву</span>
              <span className="order-item-review__photos-count">
                {totalPhotos} / {MAX_PHOTOS}
              </span>
            </div>

            <div className="order-item-review__photos-grid">
              {keptExisting.map((img) => (
                <div key={img.id} className="order-item-review__photo-item">
                  <img src={img.url} alt="" />
                  <button
                    type="button"
                    className="order-item-review__photo-remove"
                    onClick={() => removeKeptPhoto(img.id)}
                    aria-label="Удалить фото"
                  >
                    ×
                  </button>
                </div>
              ))}
              {newPhotos.map((file, idx) => (
                <div key={`new-${idx}`} className="order-item-review__photo-item">
                  <img src={URL.createObjectURL(file)} alt="" />
                  <button
                    type="button"
                    className="order-item-review__photo-remove"
                    onClick={() => removeNewPhoto(idx)}
                    aria-label="Удалить фото"
                  >
                    ×
                  </button>
                </div>
              ))}
              {canAddMore && (
                <button
                  type="button"
                  className="order-item-review__photo-add"
                  onClick={() => fileInputRef.current?.click()}
                >
                  + Фото
                </button>
              )}
            </div>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              multiple
              className="order-item-review__file-input"
              onChange={handlePhotoPick}
            />
            <p className="order-item-review__photos-hint">До 5 фото, JPG/PNG/WebP, необязательно</p>
          </div>

          <div className="review-actions">
            <button
              type="button"
              className="save-btn"
              onClick={handleSave}
              disabled={submitting || rating < 1}
            >
              {submitting ? 'Сохранение…' : 'Сохранить'}
            </button>
            <button type="button" className="cancel-btn" onClick={() => setOpen(false)} disabled={submitting}>
              Отмена
            </button>
            {existingReview && (
              <button
                type="button"
                className="delete-btn"
                onClick={() => setConfirmDelete(true)}
                disabled={submitting}
              >
                Удалить
              </button>
            )}
          </div>
        </div>
      )}

      <ConfirmModal
        isOpen={confirmDelete}
        onClose={() => setConfirmDelete(false)}
        title="Удалить отзыв?"
        message="Отзыв будет удалён без возможности восстановления."
        confirmText="Удалить"
        variant="danger"
        onConfirm={handleDelete}
      />
    </div>
  );
}
