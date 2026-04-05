import React, { useState } from 'react';
import { router } from '@inertiajs/react';

export default function DeleteAccountModal({ isOpen, onClose }) {
  const [pending, setPending] = useState(false);

  const handleDelete = () => {
    setPending(true);
    router.delete('/user/delete', {
      onFinish: () => {
        setPending(false);
        onClose();
      },
    });
  };

  if (!isOpen) return null;

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="delete-modal" onClick={e => e.stopPropagation()}>
        <h5>Подтвердите удаление</h5>
        <p>Вы уверены, что хотите удалить свой аккаунт?<br />Это действие необратимо.</p>

        <div className="flex" style={{ gap: '16px', justifyContent: 'center', marginTop: '20px' }}>
          <button onClick={onClose} disabled={pending} className="btn-cancel">
            Отмена
          </button>
          <button onClick={handleDelete} disabled={pending} className="btn-delete">
            {pending ? 'Удаляется...' : 'Удалить аккаунт'}
          </button>
        </div>
      </div>
    </div>
  );
}