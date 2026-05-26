import { Link } from '@inertiajs/react';

export default function BlockedAccountModal({ isOpen, onClose }) {
    if (!isOpen) return null;

    return (
        <div className="phone-modal-overlay blocked-modal-overlay" onClick={onClose} role="presentation">
            <div
                className="phone-modal-content blocked-modal"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="blocked-modal-title"
            >
                <button type="button" onClick={onClose} className="modal-close-btn" aria-label="Закрыть">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20">
                        <path
                            fill="currentColor"
                            d="M18.3 5.71a.996.996 0 0 0-1.41 0L12 10.59 7.11 5.7a.996.996 0 1 0-1.41 1.41L10.59 12l-4.89 4.89a.996.996 0 1 0 1.41 1.41L12 13.41l4.89 4.89a.996.996 0 1 0 1.41-1.41L13.41 12l4.89-4.89c.38-.38.38-1.02 0-1.4z"
                        />
                    </svg>
                </button>

                <div className="blocked-modal__badge">Ограничение доступа</div>
                <h3 id="blocked-modal-title">Аккаунт заблокирован</h3>
                <p>
                    Доступ к покупкам и продаже ограничен администратором. Чтобы восстановить доступ, напишите в поддержку.
                </p>
                <Link href="/messages?notifications=1" className="blocked-banner__btn blocked-banner__btn--primary">
                    Написать в поддержку
                </Link>
            </div>
        </div>
    );
}
