import React, { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import FlashBanner from '@/Components/FlashBanner';
import PvzNavIcon from '@/Components/Pvz/PvzNavIcon';
import '../../css/pvz/layout.css';

function navActive(url, href) {
    if (href === '/pvz') {
        return url === '/pvz' || url === '/pvz/';
    }
    return url === href || url.startsWith(href + '/');
}

export default function PvzLayout({ children, title, pickupPoint = {} }) {
    const { url } = usePage();
    const [sidebarOpen, setSidebarOpen] = useState(() => {
        const saved = localStorage.getItem('pvzSidebarOpen');
        return saved !== null ? saved === 'true' : true;
    });

    useEffect(() => {
        localStorage.setItem('pvzSidebarOpen', sidebarOpen);
    }, [sidebarOpen]);

    const menuItems = [
        { label: 'Главная', href: '/pvz', icon: 'dashboard' },
        { label: 'К выдаче', href: '/pvz/queue', icon: 'queue' },
        { label: 'Поиск / скан', href: '/pvz/orders', icon: 'search' },
        { label: 'Отчёты', href: '/pvz/reports', icon: 'reports' },
        { label: 'Настройки', href: '/pvz/settings', icon: 'settings' },
    ];

    return (
        <div className="pvz-layout">
            <div className="pvz-mobile-unavailable">
                <h2>Панель ПВЗ доступна с компьютера</h2>
                <p>Для выдачи заказов и отчётов нужен широкий экран.</p>
                <Link href="/" className="pvz-mobile-unavailable__link">На главную</Link>
            </div>

            <aside className={`pvz-sidebar ${sidebarOpen ? 'open' : 'closed'}`}>
                <div className="pvz-sidebar-header">
                    {sidebarOpen && (
                        <div className="pvz-sidebar-brand">
                            <h2>{pickupPoint.title || 'Пункт выдачи'}</h2>
                            {pickupPoint.address && (
                                <p className="pvz-sidebar-address">{pickupPoint.address}</p>
                            )}
                            {pickupPoint.closure_status === 'pending' && (
                                <span className="pvz-badge pvz-badge--warn">Закрытие на рассмотрении</span>
                            )}
                        </div>
                    )}
                    <button
                        type="button"
                        className="pvz-sidebar-toggle"
                        onClick={(e) => {
                            e.stopPropagation();
                            setSidebarOpen(!sidebarOpen);
                        }}
                        aria-label={sidebarOpen ? 'Свернуть меню' : 'Развернуть меню'}
                    >
                        ☰
                    </button>
                </div>

                <nav className="pvz-sidebar-nav">
                    {menuItems.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`pvz-nav-item ${navActive(url, item.href) ? 'active' : ''}`}
                        >
                            <PvzNavIcon name={item.icon} />
                            {sidebarOpen && <span className="nav-label">{item.label}</span>}
                        </Link>
                    ))}
                    <Link href="/" className="pvz-nav-item pvz-nav-back">
                        <PvzNavIcon name="site" />
                        {sidebarOpen && <span className="nav-label">На сайт</span>}
                    </Link>
                </nav>
            </aside>

            <main className="pvz-main">
                <FlashBanner classNamePrefix="pvz-flash" />
                {title && (
                    <header className="pvz-header">
                        <h1>{title}</h1>
                    </header>
                )}
                <div className="pvz-content-inner">
                    {title && <Head title={title} />}
                    {children}
                </div>
            </main>
        </div>
    );
}
