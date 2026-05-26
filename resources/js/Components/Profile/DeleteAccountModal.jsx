import { useState } from 'react';
import { router } from '@inertiajs/react';
import ConfirmModal from '@/Components/ConfirmModal';

export default function DeleteAccountModal({ isOpen, onClose, onError }) {
    const [pending, setPending] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const openConfirm = () => {
        setConfirmOpen(true);
    };

    const handleDelete = () => {
        setPending(true);
        router.delete(route('profile.destroy'), {
            onError: (errors) => {
                const msg = errors?.error || errors?.message || 'Не удалось удалить аккаунт.';
                onError?.(msg);
            },
            onFinish: () => {
                setPending(false);
                setConfirmOpen(false);
                onClose();
            },
        });
    };

    if (!isOpen) return null;

    return (
        <>
            <div className="phone-modal-overlay" onClick={onClose} role="presentation">
                <div
                    className="phone-modal-content delete-account-modal"
                    onClick={(e) => e.stopPropagation()}
                    role="dialog"
                    aria-modal="true"
                >
                    <button type="button" onClick={onClose} className="modal-close-btn" aria-label="Закрыть">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20">
                            <path
                                fill="currentColor"
                                d="M18.3 5.71a.996.996 0 0 0-1.41 0L12 10.59 7.11 5.7a.996.996 0 1 0-1.41 1.41L10.59 12l-4.89 4.89a.996.996 0 1 0 1.41 1.41L12 13.41l4.89 4.89a.996.996 0 1 0 1.41-1.41L13.41 12l4.89-4.89c.38-.38.38-1.02 0-1.4z"
                            />
                        </svg>
                    </button>

                    <h3>Удаление аккаунта</h3>
                    <p>
                        Аккаунт будет деактивирован. Восстановление возможно через поддержку в течение 30 дней.
                        Активные заказы как покупатель должны быть завершены.
                    </p>

                    <div className="phone-modal-buttons phone-modal-buttons--single">
                        <button type="button" className="phone-modal-btn phone-modal-btn--cancel" onClick={onClose}>
                            Отмена
                        </button>
                        <button
                            type="button"
                            className="phone-modal-btn phone-modal-btn--danger"
                            onClick={openConfirm}
                            disabled={pending}
                        >
                            Продолжить
                        </button>
                    </div>
                </div>
            </div>

            <ConfirmModal
                isOpen={confirmOpen}
                onClose={() => setConfirmOpen(false)}
                onConfirm={handleDelete}
                title="Удалить аккаунт навсегда?"
                message="Вы выйдете из системы, покупки и продажи станут недоступны. Это действие сложно отменить самостоятельно."
                confirmText="Да, удалить"
                cancelText="Нет, оставить"
                variant="danger"
                processing={pending}
            />
        </>
    );
}
