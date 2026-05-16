import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import PaymentModal from '@/Components/PaymentModal';
import Barcode from 'react-barcode';
import '../../../css/profile/orders.css';

export default function Orders({ auth, orders = [], dailyPickupCode = '' }) {
    const [paymentModal, setPaymentModal] = useState({ isOpen: false, order: null });
    const [barcodeOpen, setBarcodeOpen] = useState(false);

    const code = dailyPickupCode;


    const canPay = (o) =>
        o.payment_status !== 'paid' &&
        !['CANCELED', 'REFUSED'].includes(o.status);

    // статус доставки (оплата — отдельно payment_status)
    const statusText = (status) => {
        switch (status) {
            case 'NEW': return 'Заказ оформлен';
            case 'INTRANSIT': return 'В пути';
            case 'DELIVERED': return 'В пункте выдачи';
            case 'ISSUED': return 'Выдан';
            case 'CANCELED': return 'Отменён';
            case 'REFUSED': return 'Отказ от получения';
            default: return status;
        }
    };

    // цвет статуса
    const statusClass = (status) => {
        switch (status) {
            case 'NEW': return 'status-yellow';
            case 'INTRANSIT':
                return 'status-blue';
            case 'DELIVERED':
            case 'ISSUED':
                return 'status-green';
            case 'CANCELED':
            case 'REFUSED':
                return 'status-red';
            default:
                return '';
        }
    };

    // копирование
    const copy = (text) => {
        navigator.clipboard.writeText(text);
    };

    const activeOrders = orders.filter(o =>
        !['DELIVERED', 'CANCELED', 'REFUSED'].includes(o.status)
    );

    const completedOrders = orders.filter(o =>
        ['DELIVERED', 'CANCELED', 'REFUSED'].includes(o.status)
    );

    const [tab, setTab] = useState('active');

    const currentOrders = tab === 'active' ? activeOrders : completedOrders;
    return (
        <MainLayout auth={auth}>
            <Head title="Мои заказы" />

            <div className="orders-page">

                {/* КОД */}
                {orders.length > 0 && code && (
                    <div className="pickup-block" onClick={() => setBarcodeOpen(true)}>
                        <div className=""><div className="pickup-title">Код получения</div>

                            <div className="pickup-code">{code}</div>
                            <div className="pickup-hint">Действует сутки на все ваши заказы · нажмите для штрихкода</div>
                        </div>
                        <div className="">
                            <div className="barcode-box-pink" onClick={() => setBarcodeOpen(false)}>
                                <Barcode value={code.replace(/\s/g, '')} />
                            </div>
                        </div>
                    </div>

                )}
                <div className="orders-switch">
                    <button
                        className={tab === 'active' ? 'active' : ''}
                        onClick={() => setTab('active')}
                    >
                        Активные
                    </button>

                    <button
                        className={tab === 'completed' ? 'active' : ''}
                        onClick={() => setTab('completed')}
                    >
                        Архив
                    </button>
                </div>
                {/* СПИСОК */}
                <div className="order-items order-card" >


                    {currentOrders.length > 0 ? currentOrders.map(order => {

                        return (
                            <div className="order-main" key={order.id} onClick={() => router.visit(route('order.show', order.id))}>

                                {/* ЛЕВАЯ ЧАСТЬ */}
                                <div className="order-main-left">

                                    <div className="order-title">
                                        {order.status === 'INTRANSIT' ? 'В пути к вам' : statusText(order.status)}
                                    </div>

                                    <div className="order-date">
                                        {order.status === 'INTRANSIT'
                                            ? `Обновлено ${new Date(order.updated_at).toLocaleDateString('ru-RU')}`
                                            : `Оформлен ${new Date(order.created_at).toLocaleDateString('ru-RU')}`
                                        }
                                    </div>

                                    <div className="order-address">
                                        {order.delivery_address || 'Адрес уточняется'}
                                    </div>

                                </div>

                                {/* ПРАВАЯ КАРТИНКА */}
                                <div className="order-main-right">
                                    <img
                                        src={order.items[0]?.variant?.product?.image || '/img/default.jpg'}
                                        alt=""
                                    />
                                </div>

                            </div>
                        );
                    }) : (
                        <div className="NoCart">
                            <img className='NoCart__image' src="https://ir-3.ozone.ru/s3/cms/82/t5b/wc1200/box-open-empty_yellow.png" alt="NoCart" />
                            <p className='NoCart__title'>У вас пока нет актуальных заказов
                            </p>
                            <p className="NoCart__text">Когда появятся, будут отображаться здесь. Остальные заказы находятся в завершённых
                            </p>
                            <button className='NoCart__button' onClick={() => router.visit('/')}>К покупкам</button>
                        </div>
                    )}
                </div>
            </div>

            {barcodeOpen && (
                <div className="barcode-modal" onClick={() => setBarcodeOpen(false)}>
                    <div className="barcode-box" onClick={(e) => e.stopPropagation()}>
                        <Barcode value={code.replace(/\s/g, '')} />
                    </div>
                </div>
            )}

            <PaymentModal
                isOpen={paymentModal.isOpen}
                onClose={() => setPaymentModal({ isOpen: false, order: null })}
                order={paymentModal.order}
                type="order"
            />
        </MainLayout>
    );
}