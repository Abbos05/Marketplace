import React from 'react';
import { Link } from '@inertiajs/react';
import InfoPageLayout, { InfoSection } from '@/Components/Info/InfoPageLayout';

export default function Delivery() {
  return (
    <InfoPageLayout
      title="Доставка и оплата"
      lead="Как работает доставка заказов в пункты выдачи и какие способы оплаты доступны на ALVORA."
    >
      <InfoSection title="Пункты выдачи">
        <p>
          При оформлении заказа используется пункт выдачи, выбранный в профиле. Срок доставки
          рассчитывается по региону пункта — ориентировочное время показывается при просмотре товара
          и в карточке заказа.
        </p>
      </InfoSection>

      <InfoSection title="Статусы заказа">
        <ul>
          <li>Ожидание оплаты — заказ создан, требуется оплата.</li>
          <li>В обработке — оплата получена, заказ готовится к отправке.</li>
          <li>В пути — заказ передан в доставку.</li>
          <li>Готов к выдаче — можно забрать в пункте.</li>
          <li>Выдан — заказ получен покупателем.</li>
        </ul>
      </InfoSection>

      <InfoSection title="Оплата">
        <p>
          Доступна оплата картой (Stripe) и списание с внутреннего баланса. Чек и документы по заказу
          доступны в личном кабинете после оплаты.
        </p>
        <div className="info-page__actions">
          <Link href="/profile" className="info-page__btn info-page__btn--outline">
            Настроить пункт выдачи
          </Link>
        </div>
      </InfoSection>
    </InfoPageLayout>
  );
}
