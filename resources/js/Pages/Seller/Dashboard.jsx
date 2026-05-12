import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import SellerLayout from '@/Layouts/SellerLayout';

export default function Dashboard({ stats, recentOrders, popularProducts, salesChart, sellerProfile }) {
    const [chartHover, setChartHover] = useState(null);

    // Находим максимальное значение для масштабирования
    const maxSale = Math.max(...salesChart.sales, 1);

    return (
        <SellerLayout title="Главная">
            <Head title="Панель продавца" />

            {/* Статистика */}
            <div className="stats-grid">
                <div className="stat-card">
                    <div className="stat-icon">📦</div>
                    <div className="stat-info">
                        <h3>{stats.total_products}</h3>
                        <p>Товаров</p>
                    </div>
                </div>

                <div className="stat-card">
                    <div className="stat-icon">🛒</div>
                    <div className="stat-info">
                        <h3>{stats.total_orders}</h3>
                        <p>Заказов</p>
                    </div>
                </div>

                <div className="stat-card">
                    <div className="stat-icon">💰</div>
                    <div className="stat-info">
                        <h3>{stats.total_sales.toLocaleString()} ₽</h3>
                        <p>Продажи</p>
                    </div>
                </div>

                <div className="stat-card">
                    <div className="stat-icon">⏳</div>
                    <div className="stat-info">
                        <h3>{stats.pending_orders}</h3>
                        <p>В обработке</p>
                    </div>
                </div>
            </div>

            {/* График продаж */}
            <div className="chart-container">
                <h3>Продажи по месяцам</h3>
                <div className="chart-wrapper">
                    <div className="chart-bars">
                        {salesChart.sales.map((sale, index) => {
                            const height = (sale / maxSale) * 200;
                            const month = salesChart.months[index];
                            const isHovered = chartHover === index;
                       
                            return (
                                <div
                                    key={index}
                                    className="chart-bar-item"
                                    onMouseEnter={() => setChartHover(index)}
                                    onMouseLeave={() => setChartHover(null)}
                                >
                                    <div className="chart-bar-tooltip" style={{ opacity: isHovered ? 1 : 0 }}>
                                        {sale.toLocaleString()} ₽
                                    </div>
                                    <div
                                        className="chart-bar"
                                        style={{ height: `${height}px` }}
                                    >
                                        <div className="chart-bar-fill"></div>
                                    </div>
                                    <div className="chart-bar-label">{month}</div>
                                </div>
                            );
                        })}
                    </div>
                </div>
                <div className="chart-stats">
                    <div className="chart-stat">
                        <span className="stat-label">Средние продажи</span>
                        <span className="stat-value">
                            {(salesChart.sales.reduce((a, b) => a + b, 0) / 12).toLocaleString()} ₽
                        </span>
                    </div>
                    <div className="chart-stat">
                        <span className="stat-label">Максимум</span>
                        <span className="stat-value">{Math.max(...salesChart.sales).toLocaleString()} ₽</span>
                    </div>
                </div>
            </div>

            {/* Последние заказы и популярные товары */}
            <div className="dashboard-two-columns">
                <div className="recent-orders">
                    <h3>Последние заказы</h3>
                    {recentOrders.length > 0 ? (
                        <table className="orders-table">
                            <thead>
                                <tr>
                                    <th>№ заказа</th>
                                    <th>Покупатель</th>
                                    <th>Сумма</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentOrders.map(order => (
                                    <tr key={order.id}>
                                        <td>
                                            <Link href={`/orders/${order.id}`} className="order-link">
                                                #{order.number}
                                            </Link>
                                        </td>
                                        <td>{order.buyer?.name || 'Покупатель'}</td>
                                        <td>{order.total.toLocaleString()} ₽</td>
                                        <td>
                                            <span className={`status-badge status-${order.status}`}>
                                                {order.status === 'new' && 'Новый'}
                                                {order.status === 'paid' && 'Оплачен'}
                                                {order.status === 'processing' && 'В обработке'}
                                                {order.status === 'ready_for_pickup' && 'Готов к выдаче'}
                                                {order.status === 'in_transit' && 'В пути'}
                                                {order.status === 'issued' && 'Получен'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <p className="empty-data">Нет заказов</p>
                    )}
                </div>

                <div className="popular-products">
                    <h3>Популярные товары</h3>
                    {popularProducts.length > 0 ? (
                        popularProducts.map(product => (
                            <div key={product.id} className="popular-item">
                                <img
                                    src={product.image || '/img/products/default.png'}
                                    alt={product.title}
                                />
                                <div>
                                    <p>{product.title}</p>
                                    <small>{product.views_count || 0} просмотров</small>
                                </div>
                            </div>
                        ))
                    ) : (
                        <p className="empty-data">Нет товаров</p>
                    )}
                </div>
            </div>
        </SellerLayout>
    );
}