import React from 'react';
import { Link } from '@inertiajs/react';
import InfoPageLayout, { InfoSection } from '@/Components/Info/InfoPageLayout';

export default function Returns() {
  return (
    <InfoPageLayout
      title="Возвраты"
      lead="Условия возврата товаров и возмещения средств на маркетплейсе ALVORA."
    >
      <InfoSection title="Когда можно оформить возврат?">
        <p>
          Возврат доступен для оплаченных заказов в соответствии с правилами платформы и статусом
          заказа. Заявку можно подать из карточки заказа в разделе «Мои заказы».
        </p>
      </InfoSection>

      <InfoSection title="Как оформить возврат?">
        <ol style={{ paddingLeft: 20, marginBottom: 12 }}>
          <li style={{ listStyle: 'decimal', marginBottom: 8 }}>Откройте заказ в личном кабинете.</li>
          <li style={{ listStyle: 'decimal', marginBottom: 8 }}>Нажмите «Оформить возврат», если кнопка доступна.</li>
          <li style={{ listStyle: 'decimal', marginBottom: 8 }}>Подтвердите возврат на странице проверки.</li>
        </ol>
        <p>
          Средства возвращаются на тот же способ оплаты. Срок зачисления зависит от банка (обычно 3–10
          рабочих дней).
        </p>
      </InfoSection>

      <InfoSection title="Вопросы по возврату">
        <div className="info-page__actions">
          <Link href="/orders" className="info-page__btn info-page__btn--primary">
            Мои заказы
          </Link>
          <Link href="/help" className="info-page__btn info-page__btn--outline">
            Помощь
          </Link>
        </div>
      </InfoSection>
    </InfoPageLayout>
  );
}
