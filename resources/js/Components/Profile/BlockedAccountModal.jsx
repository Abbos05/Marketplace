export default function BlockedAccountModal({ isOpen, onClose }) {
    if (!isOpen) return null;
  
    return (
      <div className="phone-modal-overlay" onClick={onClose}>
        <div className="phone-modal-content" onClick={e => e.stopPropagation()}>
          <button onClick={onClose} className="modal-close-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
              <path fill="currentColor" d="M18.3 5.71a.996.996 0 0 0-1.41 0L12 10.59 7.11 5.7a.996.996 0 1 0-1.41 1.41L10.59 12l-4.89 4.89a.996.996 0 1 0 1.41 1.41L12 13.41l4.89 4.89a.996.996 0 1 0 1.41-1.41L13.41 12l4.89-4.89c.38-.38.38-1.02 0-1.4z" />
            </svg>
          </button>
  
          <h3>Ваш аккаунт заблокирован</h3>
          <p style={{ lineHeight: '1.6', color: '#ccc' }}>
            Ваш аккаунт был заблокирован из-за нарушения правил.<br />
            Для разблокировки свяжитесь с поддержкой:<br />
            <strong>+7 (999) 999-99-99</strong>
          </p>
        </div>
      </div>
    );
  }