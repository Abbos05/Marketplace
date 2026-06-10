import React, { useState, useRef, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import ProductRecommendationsSection from '@/Components/Product/ProductRecommendationsSection';
import '../../../css/cart/cart.css';

export default function Cart({ cartItems = [], pickupPoints = [], LikeProducts = [] }) {
    const { auth } = usePage().props;
    const [cart, setCart]             = useState(cartItems);
    const [selected, setSelected]     = useState([]);
    const [promoCode, setPromoCode]   = useState('');
    const [promoResult, setPromoResult] = useState(null); // { valid, message, discount_amount, code }
    const [promoLoading, setPromoLoading] = useState(false);
    const [pickupId, setPickupId] = useState(() => auth?.user?.default_pickup_point_id ?? '');

    useEffect(() => {
        setPickupId(auth?.user?.default_pickup_point_id ?? '');
    }, [auth?.user?.default_pickup_point_id]);

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
    const selectedItems  = cart.filter(i => selected.includes(i.id));
    const subtotal       = selectedItems.reduce((sum, i) => sum + (i.variant?.price || 0) * i.quantity, 0);
    const totalItems     = selectedItems.reduce((sum, i) => sum + i.quantity, 0);
    const discountAmount = (promoResult?.valid && promoResult?.discount_amount) ? promoResult.discount_amount : 0;
    const total          = Math.max(0, subtotal - discountAmount);

    // Apply promo code
    const applyPromo = () => {
        if (!promoCode.trim() || selected.length === 0) return;
        setPromoLoading(true);

        fetch('/promo/validate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ code: promoCode.trim(), cart_ids: selected }),
        })
            .then(r => r.json())
            .then(data => setPromoResult(data))
            .catch(() => setPromoResult({ valid: false, message: 'Ошибка при проверке промокода.' }))
            .finally(() => setPromoLoading(false));
    };

    const clearPromo = () => {
        setPromoCode('');
        setPromoResult(null);
    };

    // Оформление заказа
    const checkout = () => {
        if (selected.length === 0) {
            alert('Выберите товары для оформления');
            return;
        }

        if (!auth?.user?.phone) {
            alert('Подтвердите номер телефона в профиле, чтобы оформить заказ.');
            router.visit(route('profile'));
            return;
        }

        const resolvedPickup = pickupId || auth?.user?.default_pickup_point_id;
        if (!resolvedPickup) {
            alert('Выберите пункт выдачи в профиле или в списке ниже.');
            return;
        }

        router.post('/order/create', {
            items: selected.map(id => ({
                cart_id: id,
                quantity: cart.find(i => i.id === id)?.quantity,
            })),
            promo_code: promoResult?.valid ? promoResult.code : null,
            pickup_point_id: resolvedPickup,
        }, {
            preserveState: true,
            onSuccess: () => {
                const remainingItems = cart.filter(i => !selected.includes(i.id));
                setCart(remainingItems);
                setSelected([]);
                setPromoCode('');
                setPromoResult(null);
            },
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

                            {/* Promo code input */}
                            <div className="cart-promo">
                                {promoResult?.valid ? (
                                    <div className="cart-promo-applied">
                                        <span>🏷️ {promoResult.code} применён</span>
                                        <button onClick={clearPromo}>✕</button>
                                    </div>
                                ) : (
                                    <div className="cart-promo-form">
                                        <input
                                            type="text"
                                            value={promoCode}
                                            onChange={e => { setPromoCode(e.target.value.toUpperCase()); setPromoResult(null); }}
                                            placeholder="Промокод"
                                            onKeyDown={e => e.key === 'Enter' && applyPromo()}
                                        />
                                        <button
                                            onClick={applyPromo}
                                            disabled={promoLoading || !promoCode.trim() || selected.length === 0}
                                        >
                                            {promoLoading ? '...' : 'Применить'}
                                        </button>
                                    </div>
                                )}
                                {promoResult && !promoResult.valid && (
                                    <div style={{ marginTop: '6px', fontSize: '12px', color: '#dc2626', fontWeight: 500 }}>
                                        {promoResult.message}
                                    </div>
                                )}
                            </div>

                            {pickupPoints.length > 0 && (
                                <div className="cart-pickup">
                                    <label
                                        htmlFor="cart-pickup-select"
                                    >
                                        Пункт выдачи
                                    </label>
                                    <select
                                        id="cart-pickup-select"
                                        value={pickupId === '' || pickupId == null ? '' : String(pickupId)}
                                        onChange={(e) =>
                                            setPickupId(e.target.value === '' ? '' : Number(e.target.value))
                                        }
                                    >
                                        {!auth?.user?.default_pickup_point_id ? (
                                            <option value="">Выберите ПВЗ</option>
                                        ) : null}
                                        {pickupPoints.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            <div className="summary-details">
                                <div className="summary-row">
                                    <span>Товары ({totalItems} шт.)</span>
                                    <span>{subtotal.toLocaleString()} ₽</span>
                                </div>
                                <div className="summary-row">
                                    <span>Скидка</span>
                                    <span className="discount" style={discountAmount > 0 ? { color: '#10b981', fontWeight: 700 } : {}}>
                                        {discountAmount > 0 ? `−${discountAmount.toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ₽` : '0 ₽'}
                                    </span>
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
                        <img className='NoCart__image' src="https://ir-3.ozone.ru/s3/cms/82/t5b/wc1200/box-open-empty_yellow.png" alt="NoCart" />
                        <p className='NoCart__title'>В Корзине пока пусто</p>
                        <p className="NoCart__text">Добавляйте товары в корзину, чтобы не потерять их и купить позже</p>
                    </div>
                )}
            </div>

            <ProductRecommendationsSection products={LikeProducts} />
        </MainLayout>
    );
}