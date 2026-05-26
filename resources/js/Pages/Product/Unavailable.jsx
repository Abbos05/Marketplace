import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/product/product_page.css';

const REASON_HEADINGS = {
  moderation: 'Товар на модерации',
  hidden: 'Товар скрыт',
  rejected: 'Товар отклонён',
  archived: 'Товар снят с продажи',
  draft: 'Товар не опубликован',
};

export default function ProductUnavailable({ reason, message, title }) {
  const heading = REASON_HEADINGS[reason] ?? 'Товар недоступен';

  return (
    <MainLayout>
      <Head title={heading} />
      <div className="product-unavailable">
        <div className="product-unavailable__card">
          <p className="product-unavailable__eyebrow">Витрина</p>
          <h1 className="product-unavailable__title">{heading}</h1>
          {title ? <p className="product-unavailable__product-name">{title}</p> : null}
          <p className="product-unavailable__message">
            {message ?? 'Этот товар сейчас нельзя посмотреть или купить на витрине.'}
          </p>
          <Link href={route('home')} className="product-unavailable__link">
            На главную
          </Link>
        </div>
      </div>
    </MainLayout>
  );
}
