import React from 'react';
import { Link } from '@inertiajs/react';
import InfoPageLayout, { InfoSection } from '@/Components/Info/InfoPageLayout';

export default function Contacts() {
  return (
    <InfoPageLayout
      title="Контакты"
      lead="Свяжитесь с командой ALVORA по вопросам заказов, продаж на платформе и сотрудничества."
    >
      <InfoSection title="Служба поддержки">
        <p>
          Email:{' '}
          <a href="mailto:support@alvora.ru" className="info-page__link">
            support@alvora.ru
          </a>
        </p>
        <p>
          Telegram:{' '}
          <a href="https://t.me/alvora_support" className="info-page__link" target="_blank" rel="noopener noreferrer">
            @alvora_support
          </a>
        </p>
      </InfoSection>

      <InfoSection title="Для продавцов">
        <p>
          Вопросы по заявке и модерации магазина — через личный кабинет в разделе «Компания» или в чате
          поддержки после авторизации.
        </p>
      </InfoSection>

      <InfoSection title="Написать нам">
        <p>Вы также можете отправить сообщение через форму ниже. Ответ придёт на указанный email.</p>
        <form
          className="contacts-form"
          onSubmit={(e) => {
            e.preventDefault();
          }}
          style={{ display: 'flex', flexDirection: 'column', gap: 12, marginTop: 16 }}
        >
          <input
            type="text"
            placeholder="Ваше имя"
            className="info-page__btn info-page__btn--outline"
            style={{ textAlign: 'left', cursor: 'text' }}
          />
          <input
            type="email"
            placeholder="Email"
            className="info-page__btn info-page__btn--outline"
            style={{ textAlign: 'left', cursor: 'text' }}
          />
          <textarea
            rows={5}
            placeholder="Сообщение"
            className="info-page__btn info-page__btn--outline"
            style={{ textAlign: 'left', cursor: 'text', resize: 'vertical' }}
          />
          <button type="submit" className="info-page__btn info-page__btn--primary" style={{ border: 'none', cursor: 'pointer' }}>
            Отправить
          </button>
        </form>
      </InfoSection>

      <InfoSection title="Быстрые ссылки">
        <div className="info-page__actions">
          <Link href="/help" className="info-page__btn info-page__btn--outline">
            Помощь
          </Link>
          <Link href="/messages" className="info-page__btn info-page__btn--primary">
            Чат поддержки
          </Link>
        </div>
      </InfoSection>
    </InfoPageLayout>
  );
}
