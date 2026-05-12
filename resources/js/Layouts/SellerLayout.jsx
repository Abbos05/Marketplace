// resources/js/Layouts/SellerLayout.jsx
import React, { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import '../../css/seller/dashboard.css';
export default function SellerLayout({ children, title }) {
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const { url } = usePage();
    
    const menuItems = [
        { icon: '📊', label: 'Главная', href: '/seller/dashboard' },
        { icon: '📦', label: 'Товары', href: '/seller/products' },
        { icon: '➕', label: 'Добавить товар', href: '/seller/products/create' },
        { icon: '🛒', label: 'Заказы', href: '/seller/orders' },
        { icon: '📈', label: 'Статистика', href: '/seller/statistics' },
        { icon: '⚙️', label: 'Настройки', href: '/seller/settings' },
    ];
    
    return (
        <div className="seller-layout">
            {/* Левый сайдбар */}
            <aside className={`seller-sidebar ${sidebarOpen ? 'open' : 'closed'}`}>
                <div className="sidebar-header">
                    <h2>Мой магазин</h2>
                    <button onClick={() => setSidebarOpen(!sidebarOpen)}>☰</button>
                </div>
                
                <nav className="sidebar-nav">
                    {menuItems.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`nav-item ${url === item.href ? 'active' : ''}`}
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
                        <span>{usePage().props.auth.user.name}</span>
                        <Link href="/profile" className="back-to-site">На сайт</Link>
                    </div>
                </div>
                
                <div className="seller-content">
                    {children}
                </div>
            </main>
        </div>
    );
}