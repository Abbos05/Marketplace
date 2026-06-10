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
          <a href="mailto:supportalvoraplace@gmail.com" className="info-page__link">
          SupportAlvoraPlace@gmail.com
          </a>
        </p>
        {/* <p>
          Telegram-канал:{' '}
          <a href="https://t.me/AlvoraPlace" className="info-page__link" target="_blank" rel="noopener noreferrer">
            @AlvoraPlace
          </a>
        </p> */}
        <p>
          MAX:{' '}
          <a href="https://max.ru/join/uTTd84ZCWV6LDqeiR1KOFZnBPp-2ar4mgwWMtSsmfmQ" className="info-page__link" target="_blank" rel="noopener noreferrer">
            Открыть канал ALVORA в MAX
          </a>
        </p>
      </InfoSection>

      <InfoSection title="Основатель платформы">
        <p>
          Дадоматов Аббос Нурмахмадович — создатель платформы ALVORA.
        </p>
        <p>
          Email:{' '}
          <span className="info-page__link">Abbos••••@gmail.com</span>
        </p>
        <p>
          Телефон: <span className="info-page__link">+7 9•• ••• 11 05</span>
        </p>
        <p>
          Полные личные контакты предоставляются через службу поддержки только для рабочих обращений.
        </p>
        {/* <p>
          Telegram:{' '}
          <a href="https://t.me/id_a_005_a" className="info-page__link" target="_blank" rel="noopener noreferrer">
            t.me/id_a_005_a
          </a>
        </p> */}
        <p>
          VK:{' '}
          <a href="https://vk.com/id_a_i_09_05_i_a" className="info-page__link" target="_blank" rel="noopener noreferrer">
            vk.com/id_a_i_09_05_i_a
          </a>
        </p>
      </InfoSection>

      <InfoSection title="Для продавцов">
        <p>
          Вопросы по заявке и модерации магазина — через личный кабинет в разделе «Компания» или в чате
          поддержки после авторизации.
        </p>
      </InfoSection>


      <InfoSection>
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
