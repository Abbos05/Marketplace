// resources/js/Layouts/SellerLayout.jsx
import React, { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import MessagesFloatingWidget from '@/Components/MessagesFloatingWidget';
import '../../css/seller/dashboard.css';
import '../../css/messages-widget.css';
function navItemActive(url, href) {
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
    if (href === '/seller/settings') {
        return url.startsWith('/seller/settings');
    }
    if (href === '/messages') {
        return url.startsWith('/messages');
    }
    return url === href;
}

export default function SellerLayout({ children, title, sellerProfile = null }) {
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const { url, props, } = usePage();
    
    const menuItems = [
        { icon: '📊', label: 'Главная', href: '/seller/dashboard' },
        { icon: '📦', label: 'Товары', href: '/seller/products' },
        { icon: '➕', label: 'Добавить товар', href: '/seller/products/create' },
        { icon: '🛒', label: 'Заказы', href: '/seller/orders' },
        { icon: '💬', label: 'Сообщения', href: '/messages' },
        { icon: '📈', label: 'Статистика', href: '/seller/statistics' },
        { icon: '🏷️', label: 'Промокоды', href: '/seller/promocodes' },
        { icon: '⚙️', label: 'Настройки', href: '/seller/settings' },
    ];
    console.log(sellerProfile);
  
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
                    <h2>{sellerProfile?.shop_name || 'Мой магазин'}</h2>
                    <button onClick={() => setSidebarOpen(!sidebarOpen)}>☰</button>
                </div>
                
                <nav className="sidebar-nav">
                    {menuItems.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`nav-item ${navItemActive(url, item.href) ? 'active' : ''}`}
                        >
                            <span className="nav-icon">{item.icon}</span>
                            <span className="nav-label">{item.label}</span>
                        </Link>
                    ))}
                </nav>
            </aside>
            
            {/* Основной контент */}
            <main className="seller-main">
                <div className="seller-header">
                    <h1>{title}</h1>
                    <div className="seller-user">
                        <span>{props.auth.user.name}</span>
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