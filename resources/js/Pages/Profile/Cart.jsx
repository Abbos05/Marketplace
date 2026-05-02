import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/cart/cart.css';

export default function Cart({ cartItems = [] }) {
    const [cart, setCart] = useState(cartItems);
    const [selected, setSelected] = useState([]);

    // Выбрать товар
    const toggleSelect = (id) => {
        setSelected(prev =>
            prev.includes(id)
                ? prev.filter(i => i !== id)
                : [...prev, id]
        );
    };

    // Выбрать все
    const selectAll = () => {
        if (selected.length === cart.length && cart.length > 0) {
            setSelected([]);
        } else {
            setSelected(cart.map(i => i.id));
        }
    };

    // Изменить количество
    const changeQty = (id, newQty) => {
        if (newQty < 1) return;
        
        const item = cart.find(i => i.id === id);
        if (!item) return;

        // Обновляем локально
        setCart(prev =>
            prev.map(item =>
                item.id === id ? { ...item, quantity: newQty } : item
            )
        );

        // Отправляем на сервер
        router.post(`/cart/update/${id}`, { quantity: newQty }, {
            preserveState: true,
            onError: (errors) => {
                console.error('Ошибка обновления:', errors);
                // Откатываем при ошибке
                setCart(prev =>
                    prev.map(item =>
                        item.id === id ? { ...item, quantity: item.quantity } : item
                    )
                );
            }
        });
    };

    // Удалить товар
    const removeItem = (id) => {
        setCart(prev => prev.filter(item => item.id !== id));
        setSelected(prev => prev.filter(i => i !== id));

        router.delete(`/cart/remove/${id}`, {
            preserveState: true,
            onError: (errors) => {
                console.error('Ошибка удаления:', errors);
                // Восстанавливаем при ошибке
                window.location.reload();
            }
        });
    };

    // Удалить выбранные
    const removeSelected = () => {
        if (selected.length === 0) return;
        
        const remaining = cart.filter(i => !selected.includes(i.id));
        setCart(remaining);
        
        selected.forEach(id => {
            router.delete(`/cart/remove/${id}`, {
                preserveState: true,
                onError: () => window.location.reload()
            });
        });
        
        setSelected([]);
    };

    // Подсчет итогов
    const selectedItems = cart.filter(i => selected.includes(i.id));
    const total = selectedItems.reduce((sum, i) => sum + (i.variant?.price || 0) * i.quantity, 0);
    const totalItems = selectedItems.reduce((sum, i) => sum + i.quantity, 0);

    // Оформление заказа
    const checkout = () => {
        if (selected.length === 0) {
            alert('Выберите товары для оформления');
            return;
        }
        
        router.post('/order/create', { 
            items: selected.map(id => ({
                cart_id: id,
                quantity: cart.find(i => i.id === id)?.quantity
            }))
        }, {
            preserveState: true,
            onSuccess: () => {
                // Удаляем выбранные товары из корзины
                const remainingItems = cart.filter(i => !selected.includes(i.id));
                setCart(remainingItems);
                setSelected([]);
            }
        });
    };

    return (
        <MainLayout>
            <Head title="Корзина" />

            <div className="basket">
                <h2 className='basket__title'>Корзина</h2>

                {cart.length > 0 ? (
                    <div className="cart-wrapper">
                        {/* Левая колонка - товары */}
                        <div className="cart-items">
                            {/* Шапка с выбором всех */}
                            <div className="cart-header">
                                <label className="select-all">
                                    <input
                                        type="checkbox"
                                        onChange={selectAll}
                                        checked={selected.length === cart.length && cart.length > 0}
                                    />
                                    Выбрать все
                                </label>
                                
                                {selected.length > 0 && (
                                    <button 
                                        className="remove-selected-btn"
                                        onClick={removeSelected}
                                    >
                                        Удалить выбранные ({selected.length})
                                    </button>
                                )}
                            </div>

                            {/* Список товаров */}
                            {cart.map(item => (
                                <div key={item.id} className="cart-item">
                                    <input
                                        type="checkbox"
                                        className="cart-item-checkbox"
                                        checked={selected.includes(item.id)}
                                        onChange={() => toggleSelect(item.id)}
                                    />

                                    <div className="cart-item-image">
                                        <img 
                                            src={item.product?.image || '/img/default.jpg'} 
                                            alt={item.product?.title}
                                        />
                                    </div>

                                    <div className="cart-item-info">
                                        <div className="cart-item-title">
                                            {item.product?.title || 'Товар'}
                                        </div>
                                        
                                        {item.variant?.options && (
                                            <div className="cart-item-options">
                                                {Object.entries(JSON.parse(item.variant.options)).map(([key, val]) => (
                                                    <span key={key}>{key}: {val}</span>
                                                ))}
                                            </div>
                                        )}
                                        
                                        {item.variant?.old_price && (
                                            <div className="cart-item-old-price">
                                                {item.variant.old_price} ₽
                                            </div>
                                        )}
                                    </div>

                                    <div className="cart-item-quantity">
                                        <button 
                                            onClick={() => changeQty(item.id, item.quantity - 1)}
                                            disabled={item.quantity <= 1}
                                        >
                                            -
                                        </button>
                                        <span>{item.quantity}</span>
                                        <button 
                                            onClick={() => changeQty(item.id, item.quantity + 1)}
                                            disabled={item.variant?.stock <= item.quantity}
                                        >
                                            +
                                        </button>
                                    </div>

                                    <div className="cart-item-price">
                                        {((item.variant?.price || 0) * item.quantity).toLocaleString()} ₽
                                    </div>

                                    <button 
                                        className="cart-item-remove" 
                                        onClick={() => removeItem(item.id)}
                                    >
                                        ✕
                                    </button>
                                </div>
                            ))}
                        </div>

                        {/* Правая колонка - итого */}
                        <div className="cart-summary">
                            <div className="summary-header">
                                <h3>Итого</h3>
                                <div className="summary-price">
                                    {total.toLocaleString()} ₽
                                </div>
                            </div>
                            
                            <div className="summary-details">
                                <div className="summary-row">
                                    <span>Товары ({totalItems} шт.)</span>
                                    <span>{total.toLocaleString()} ₽</span>
                                </div>
                                <div className="summary-row">
                                    <span>Скидка</span>
                                    <span className="discount">0 ₽</span>
                                </div>
                                <div className="summary-row total">
                                    <span>Итого к оплате</span>
                                    <span className="total-amount">{total.toLocaleString()} ₽</span>
                                </div>
                            </div>

                            <button 
                                className="checkout-btn" 
                                onClick={checkout}
                                disabled={selected.length === 0}
                            >
                                Оформить заказ
                            </button>
                            
                            {selected.length === 0 && (
                                <div className="checkout-hint">
                                    Выберите хотя бы один товар
                                </div>
                            )}
                        </div>

                    </div>
                ) : (
                    <div className="NoCart">
                        <img className='NoCart__image' src="/img/cart/NoCart.png" alt="NoCart" />
                        <p className='NoCart__title'>В Корзине пока пусто</p>
                        <p className="NoCart__text">Добавляйте товары в корзину, чтобы не потерять их и купить позже</p>
                    </div>
                )}
            </div>
        </MainLayout>
    );
}