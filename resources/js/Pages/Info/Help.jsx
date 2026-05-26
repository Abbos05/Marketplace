import React from 'react';
import { Link } from '@inertiajs/react';
import InfoPageLayout, { InfoSection } from '@/Components/Info/InfoPageLayout';

export default function Help() {
  return (
    <InfoPageLayout
      title="Помощь"
      lead="Ответы на частые вопросы о заказах, оплате и доставке на маркетплейсе ALVORA."
    >
      <InfoSection title="Как оформить заказ?">
        <p>
          Выберите товар в каталоге, добавьте в корзину и перейдите к оформлению. Укажите пункт выдачи
          в профиле — срок доставки зависит от региона.
        </p>
      </InfoSection>

      <InfoSection title="Как оплатить?">
        <p>
          Оплата доступна после авторизации: банковской картой через защищённый платёжный сервис или
          с баланса кошелька в личном кабинете.
        </p>
      </InfoSection>

      <InfoSection title="Где забрать заказ?">
        <p>
          Заказы доставляются в пункты выдачи. Адрес и статус отображаются в разделе{' '}
          <Link href="/orders" className="info-page__link">
            Мои заказы
          </Link>
          .
        </p>
      </InfoSection>

      <InfoSection title="Нужна поддержка?">
        <p>
          Напишите нам в чате или на странице контактов — мы ответим в рабочее время.
        </p>
        <div className="info-page__actions">
          <Link href="/messages" className="info-page__btn info-page__btn--primary">
            Открыть сообщения
          </Link>
          <Link href="/contacts" className="info-page__btn info-page__btn--outline">
            Контакты
          </Link>
        </div>
      </InfoSection>
    </InfoPageLayout>
  );
}
