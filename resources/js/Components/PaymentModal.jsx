// resources/js/Components/PaymentModal.jsx
import { useState } from 'react';
import '../../css/product/PaymentModal.css';

export default function PaymentModal({ 
    isOpen, 
    onClose, 
    item,      // для товара
    order,     // для заказа
    type = 'product' // 'product' или 'order'
}) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    // Определяем что оплачиваем
    const isOrder = type === 'order';
    const target = isOrder ? order : item;
    
    // Получаем сумму
    const amount = isOrder 
        ? parseFloat(target?.total) || 0 
        : parseFloat(target?.price) || 0;

    console.log('PaymentModal data:', { isOrder, target, amount, type });

    if (!isOpen || !target) return null;

    // Оплата картой через Stripe
    const handleCardPay = async () => {
        setLoading(true);
        setError('');
        
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        
        const endpoint = isOrder 
            ? '/stripe/order-checkout' 
            : '/stripe/checkout';
        
        const data = isOrder 
            ? { order_id: target.id } 
            : { 
                product_id: target.id, 
                variant_id: target.variant_id,
                title: target.title, 
                price: amount
              };
        
        console.log('Sending request to:', endpoint, data);
        
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'X-CSRF-TOKEN': csrf 
                },
                body: JSON.stringify(data),
            });
            
            const result = await response.json();
            console.log('Response:', result);
            
            if (result.redirect) {
                window.location.href = result.redirect;
            } else if (result.url) {
                window.location.href = result.url;
            } else if (result.error) {
                setError(result.error);
            } else {
                setError('Ошибка при создании платежа');
            }
        } catch (err) {
            console.error('Fetch error:', err);
            setError('Ошибка подключения к платежному шлюзу. Попробуйте позже.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="payment-overlay" onClick={onClose}>
            <div className="payment-modal" onClick={e => e.stopPropagation()}>
                <button className="payment-close-top" onClick={onClose}>✕</button>
                
                <h2 className="payment-title">
                    {isOrder ? 'Оплата заказа' : 'Оплата товара'}
                </h2>
                
                <div className="payment-amount">
                    Сумма: <span>{amount.toLocaleString()} ₽</span>
                </div>
                
                {isOrder && target?.number && (
                    <div className="payment-order-number">
                        Заказ #{target.number}
                    </div>
                )}

                {error && (
                    <div className="payment-error">{error}</div>
                )}

                {/* Только карта */}
                <div className="payment-option" onClick={handleCardPay}>
                    <div className="payment-option-icon">💳</div>
                    <div className="payment-option-content">
                        <div className="payment-option-title">Банковская карта</div>
                        <div className="payment-option-desc">
                            Visa, Mastercard, МИР
                        </div>
                    </div>
                    <div className="payment-option-arrow">→</div>
                </div>

                {loading && (
                    <div className="payment-loading">
                        <div className="spinner"></div>
                        <p>Перенаправление на оплату...</p>
                    </div>
                )}

                <button onClick={onClose} className="payment-cancel">
                    Отмена
                </button>
            </div>
        </div>
    );
}