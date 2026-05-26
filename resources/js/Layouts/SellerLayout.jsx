// resources/js/Layouts/SellerLayout.jsx
import React, { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import MessagesFloatingWidget from '@/Components/MessagesFloatingWidget';
import FlashBanner from '@/Components/FlashBanner';
import SellerNavIcon from '@/Components/Seller/SellerNavIcon';
import '../../css/seller/dashboard.css';
import '../../css/messages-widget.css';

function navItemActive(url, href) {
    if (href === '/seller/dashboard') {
        return url === href || url.startsWith('/seller/dashboard');
    }
    if (href === '/seller/products/create') {
        return url === href || url.startsWith('/seller/products/create');
    }
    if (href === '/seller/products') {
        return url === href || /^\/seller\/products\/\d+\/edit/.test(url);
    }
    if (href === '/seller/orders') {
        return url === href || /^\/seller\/orders\/\d+/.test(url);
    }
    if (href === '/seller/statistics') {
        return url.startsWith('/seller/statistics');
    }
    if (href === '/seller/promocodes') {
        return url.startsWith('/seller/promocodes');
    }
    if (href === '/seller/promotions') {
        return url.startsWith('/seller/promotions');
    }
    if (href === '/seller/settings') {
        return url.startsWith('/seller/settings');
    }
    if (href === '/messages') {
        return url.startsWith('/messages');
    }
    return url === href;
}

export default function SellerLayout({ children, title, sellerProfile = null }) {
    // Загружаем состояние сайдбара из localStorage при монтировании
    const [sidebarOpen, setSidebarOpen] = useState(() => {
        const saved = localStorage.getItem('sellerSidebarOpen');
        return saved !== null ? saved === 'true' : true; // по умолчанию открыт
    });

    // Сохраняем состояние в localStorage при изменении
    useEffect(() => {
        localStorage.setItem('sellerSidebarOpen', sidebarOpen);
    }, [sidebarOpen]);

    const { url, props } = usePage();
    const shopName =
        sellerProfile?.shop_name
        ?? props.sellerCabinet?.shop_name
        ?? 'Мой магазин';
    
    const menuItems = [
        { label: 'Главная', href: '/seller/dashboard', icon: 'dashboard' },
        { label: 'Товары', href: '/seller/products', icon: 'products' },
        { label: 'Добавить товар', href: '/seller/products/create', icon: 'create' },
        { label: 'Заказы', href: '/seller/orders', icon: 'orders' },
        { label: 'Статистика', href: '/seller/statistics', icon: 'statistics' },
        { label: 'Промокоды', href: '/seller/promocodes', icon: 'promocodes' },
        { label: 'Акции', href: '/seller/promotions', icon: 'promocodes' },
        { label: 'Настройки', href: '/seller/settings', icon: 'settings' },
    ];

    return (
        <div className="seller-layout">
            <div className="seller-mobile-unavailable">
                <h2>Панель продавца доступна с компьютера</h2>
                <p>Для управления товарами, заказами и статистикой нужен широкий экран. Откройте панель на ноутбуке или ПК.</p>
                <Link href="/profile" className="seller-mobile-unavailable__link">Вернуться в профиль</Link>
            </div>

            {/* Левый сайдбар */}
            <aside className={`seller-sidebar ${sidebarOpen ? 'open' : 'closed'}`}>
                <div className="sidebar-header">
                    {sidebarOpen && <h2>{shopName}</h2>}
                    <button 
                        onClick={(e) => {
                            e.stopPropagation(); // предотвращаем всплытие
                            setSidebarOpen(!sidebarOpen);
                        }}
                        className="sidebar-toggle-btn"
                    >
                        ☰
                    </button>
                </div>
                
                <nav className="sidebar-nav">
                    {menuItems.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`nav-item ${navItemActive(url, item.href) ? 'active' : ''}`}
                            // Опционально: если нужно закрывать сайдбар на мобильных при клике
                            // onClick={() => window.innerWidth < 768 && setSidebarOpen(false)}
                        >
                            <SellerNavIcon name={item.icon} />
                            {sidebarOpen && <span className="nav-label">{item.label}</span>}
                        </Link>
                    ))}
                </nav>
            </aside>
            
            {/* Основной контент */}
            <main className="seller-main">
                <FlashBanner classNamePrefix="ord-flash" />
                <div className="seller-header">
                    <h1>{title}</h1>
                    <div className="seller-user">
                        <span>{props.auth.user.name}</span>
                        <Link href="/messages" className="idx-action-btn">Сообщения</Link>
                        <Link href="/profile" className="back-to-site">На сайт</Link>
                    </div>
                </div>
                
                <div className="seller-content">
                    {children}
                </div>
            </main>
            {props.auth?.user && <MessagesFloatingWidget />}
        </div>
    );
}