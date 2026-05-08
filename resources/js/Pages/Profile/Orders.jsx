import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import PaymentModal from '@/Components/PaymentModal';
import Barcode from 'react-barcode';
import '../../../css/profile/orders.css';

export default function Orders({ auth, orders = [] }) {
    const [paymentModal, setPaymentModal] = useState({ isOpen: false, order: null });
    const [barcodeOpen, setBarcodeOpen] = useState(false);

    // 🔥 код на сутки
    const getDailyCode = () => {
        const today = new Date().toDateString();
        const saved = localStorage.getItem('pickup_code');

        if (saved) {
            const parsed = JSON.parse(saved);
            if (parsed.date === today) return parsed.code;
        }

        const newCode = `${Math.floor(1000 + Math.random() * 9000)} ${Math.floor(1000 + Math.random() * 9000)}`;

        localStorage.setItem('pickup_code', JSON.stringify({
            date: today,
            code: newCode
        }));

        return newCode;
    };

    const code = getDailyCode();


    const canPay = (o) =>
        o.payment_status !== 'paid' &&
        !['canceled', 'returned'].includes(o.status);

    // статус текст
    const statusText = (status) => {
        switch (status) {
            case 'new': return 'Заказ оформлен';
            case 'paid': return 'Оплачен';
            case 'processing': return 'Собирается';
            case 'in_transit': return 'В пути';
            case 'at_pvz': return 'Готов к выдаче';
            case 'issued': return 'Получен';
            case 'canceled': return 'Отменён';
            case 'returned': return 'Возврат';
            default: return status;
        }
    };

    // цвет статуса
    const statusClass = (status) => {
        switch (status) {
            case 'new': return 'status-yellow';
            case 'processing':
            case 'in_transit':
                return 'status-blue';
            case 'paid':
            case 'issued':
                return 'status-green';
            case 'canceled':
            case 'returned':
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
        !['issued', 'canceled', 'returned'].includes(o.status)
    );

    const completedOrders = orders.filter(o =>
        ['issued', 'canceled', 'returned'].includes(o.status)
    );

    const [tab, setTab] = useState('active');

    const currentOrders = tab === 'active' ? activeOrders : completedOrders;
    return (
        <MainLayout auth={auth}>
            <Head title="Мои заказы" />

            <div className="orders-page">

                {/* КОД */}
                {orders.length > 0 && (
                    <div className="pickup-block" onClick={() => setBarcodeOpen(true)}>
                        <div className=""><div className="pickup-title">Код получения</div>

                            <div className="pickup-code">{code}</div>
                            <div className="pickup-hint">Нажмите, чтобы открыть штрихкод</div>
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
        

                    {currentOrders.map(order => {

                        return (
                            <div className="order-main"  key={order.id} onClick={() => router.visit(route('order.show', order.id))}>

                                {/* ЛЕВАЯ ЧАСТЬ */}
                                <div className="order-main-left">

                                    <div className="order-title">
                                        {order.status === 'at_pvz' ? 'Можно забрать' : statusText(order.status)}
                                    </div>

                                    <div className="order-date">
                                        {order.status === 'at_pvz'
                                            ? `До ${new Date(order.updated_at).toLocaleDateString('ru-RU')}`
                                            : `Ожидается ${new Date(order.created_at).toLocaleDateString('ru-RU')}`
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
                    })}
                </div>
            </div>

            {/* 🔥 МОДАЛКА ШТРИХКОДА */}
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