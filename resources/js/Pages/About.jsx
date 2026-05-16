import React from 'react';
import { Link } from '@inertiajs/react';
import InfoPageLayout, { InfoSection } from '@/Components/Info/InfoPageLayout';

export default function About() {
  return (
    <InfoPageLayout
      title="О маркетплейсе ALVORA"
      lead="ALVORA — платформа для онлайн-торговли, где покупатели и продавцы встречаются в одном каталоге с удобной доставкой и оплатой."
    >
      <InfoSection title="Для покупателей">
        <p>
          Просматривайте каталог по категориям, добавляйте товары в корзину и избранное, оформляйте заказы с
          доставкой в пункт выдачи. Отслеживайте статус заказа и общайтесь с поддержкой в чате.
        </p>
      </InfoSection>

      <InfoSection title="Для продавцов">
        <p>
          Подайте заявку на продажу, размещайте товары после модерации, управляйте остатками и смотрите
          статистику продаж в панели продавца.
        </p>
      </InfoSection>

      <InfoSection title="Безопасность и прозрачность">
        <p>
          Оплата проходит через защищённые платёжные сервисы. Отзывы проходят модерацию. Возвраты оформляются
          через личный кабинет в соответствии с правилами платформы.
        </p>
      </InfoSection>

      <InfoSection title="Начать покупки">
        <div className="info-page__actions">
          <Link href="/category" className="info-page__btn info-page__btn--primary">
            Перейти в каталог
          </Link>
          <Link href="/contacts" className="info-page__btn info-page__btn--outline">
            Связаться с нами
          </Link>
        </div>
      </InfoSection>
    </InfoPageLayout>
  );
}
