import { Link } from '@inertiajs/react';

export default function BlockedAccountBanner({ onDetailsClick }) {
    return (
        <div className="blocked-banner" role="alert">
            <div className="blocked-banner__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"
                        fill="currentColor"
                    />
                </svg>
            </div>
            <div className="blocked-banner__body">
                <h2>Аккаунт заблокирован</h2>
                <p>
                    Покупки, продажа и управление компанией временно недоступны. Для разблокировки обратитесь в поддержку.
                </p>
                <ul>
                    <li>Новые заказы оформить нельзя</li>
                    <li>Товары продавца скрыты с витрины</li>
                    <li>Для разблокировки обратитесь в поддержку</li>
                </ul>
            </div>
            <div className="blocked-banner__actions">
                <Link href="/messages?notifications=1" className="blocked-banner__btn blocked-banner__btn--primary">
                    Написать в поддержку
                </Link>
                {onDetailsClick && (
                    <button type="button" className="blocked-banner__btn blocked-banner__btn--ghost" onClick={onDetailsClick}>
                        Подробнее
                    </button>
                )}
            </div>
        </div>
    );
}
