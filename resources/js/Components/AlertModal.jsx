import { Link } from '@inertiajs/react';

export default function AlertModal({
    isOpen,
    onClose,
    title = 'Уведомление',
    message,
    buttonText = 'Понятно',
    showSupportLink = false,
}) {
    if (!isOpen) return null;

    return (
        <div className="phone-modal-overlay" onClick={onClose} role="presentation">
            <div
                className="phone-modal-content alert-modal"
                onClick={(e) => e.stopPropagation()}
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="alert-modal-title"
            >
                <button type="button" onClick={onClose} className="modal-close-btn" aria-label="Закрыть">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20">
                        <path
                            fill="currentColor"
                            d="M18.3 5.71a.996.996 0 0 0-1.41 0L12 10.59 7.11 5.7a.996.996 0 1 0-1.41 1.41L10.59 12l-4.89 4.89a.996.996 0 1 0 1.41 1.41L12 13.41l4.89 4.89a.996.996 0 1 0 1.41-1.41L13.41 12l4.89-4.89c.38-.38.38-1.02 0-1.4z"
                        />
                    </svg>
                </button>

                <h3 id="alert-modal-title">{title}</h3>
                {message && <p className="alert-modal__message">{message}</p>}

                <div className="phone-modal-buttons phone-modal-buttons--single">
                    {showSupportLink ? (
                        <Link
                            href="/messages?notifications=1"
                            className="phone-modal-btn phone-modal-btn--submit"
                            onClick={onClose}
                        >
                            Написать в поддержку
                        </Link>
                    ) : (
                        <button type="button" className="phone-modal-btn phone-modal-btn--submit" onClick={onClose}>
                            {buttonText}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
