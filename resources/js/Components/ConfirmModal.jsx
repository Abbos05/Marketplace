export default function ConfirmModal({
    isOpen,
    onClose,
    onConfirm,
    title = 'Подтверждение',
    message,
    confirmText = 'Подтвердить',
    cancelText = 'Отмена',
    variant = 'default',
    processing = false,
    children = null,
    confirmDisabled = false,
}) {
    if (!isOpen) return null;

    return (
        <div className="phone-modal-overlay" onClick={onClose} role="presentation">
            <div
                className="phone-modal-content confirm-modal"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-modal-title"
            >
                <button type="button" onClick={onClose} className="modal-close-btn" aria-label="Закрыть">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20">
                        <path
                            fill="currentColor"
                            d="M18.3 5.71a.996.996 0 0 0-1.41 0L12 10.59 7.11 5.7a.996.996 0 1 0-1.41 1.41L10.59 12l-4.89 4.89a.996.996 0 1 0 1.41 1.41L12 13.41l4.89 4.89a.996.996 0 1 0 1.41-1.41L13.41 12l4.89-4.89c.38-.38.38-1.02 0-1.4z"
                        />
                    </svg>
                </button>

                <h3 id="confirm-modal-title">{title}</h3>
                {message && <p className="confirm-modal__message">{message}</p>}
                {children}

                <div className="phone-modal-buttons">
                    <button type="button" className="phone-modal-btn phone-modal-btn--cancel" onClick={onClose} disabled={processing}>
                        {cancelText}
                    </button>
                    <button
                        type="button"
                        className={`phone-modal-btn phone-modal-btn--submit${variant === 'danger' ? ' phone-modal-btn--danger' : ''}`}
                        onClick={onConfirm}
                        disabled={processing || confirmDisabled}
                    >
                        {processing ? 'Подождите…' : confirmText}
                    </button>
                </div>
            </div>
        </div>
    );
}
