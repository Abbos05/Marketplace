import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import SellerLayout from '@/Layouts/SellerLayout';
import { formatInt, formatRub } from '@/lib/formatMoney';

export default function Dashboard({ stats, recentOrders, popularProducts, salesChart }) {
    const [chartHover, setChartHover] = useState(null);

    const maxSale = Math.max(...salesChart.sales, 1);

    return (
        <SellerLayout title="Главная">
            <Head title="Панель продавца" />

            <div className="stats-grid">
                <div className="stat-card">
                    <div className="stat-info">
                        <h3>{formatInt(stats.total_products)}</h3>
                        <p>Товаров</p>
                    </div>
                </div>

                <div className="stat-card">
                    <div className="stat-info">
                        <h3>{formatInt(stats.total_orders)}</h3>
                        <p>Заказов</p>
                    </div>
                </div>

                <div className="stat-card">
                    <div className="stat-info">
                        <h3>{formatRub(stats.total_sales)}</h3>
                        <p>Чистый доход (выдано)</p>
                    </div>
                </div>

                <div className="stat-card">
                    <div className="stat-info">
                        <h3>{formatInt(stats.pending_orders)}</h3>
                        <p>В обработке</p>
                    </div>
                </div>
            </div>

            <div className="chart-container">
                <h3>Чистый доход по месяцам (выдано)</h3>
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
                                        {formatRub(sale)}
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
                        <span className="stat-label">Среднее за месяц</span>
                        <span className="stat-value">
                            {formatRub(salesChart.sales.reduce((a, b) => a + b, 0) / 12)}
                        </span>
                    </div>
                    <div className="chart-stat">
                        <span className="stat-label">Максимум</span>
                        <span className="stat-value">{formatRub(Math.max(...salesChart.sales))}</span>
                    </div>
                </div>
            </div>

            <div className="dashboard-two-columns">
                <div className="recent-orders">
                    <h3>Последние заказы</h3>
                    {recentOrders.length > 0 ? (
                        <table className="orders-table">
                            <thead>
                                <tr>
                                    <th>№ заказа</th>
                                    <th>Покупатель</th>
                                    <th>Ваша выплата</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentOrders.map((order) => (
                                    <tr key={order.id}>
                                        <td>
                                            <Link href={route('seller.orders.show', order.id)} className="order-link">
                                                #{order.number}
                                            </Link>
                                        </td>
                                        <td>{order.buyer?.name || 'Покупатель'}</td>
                                        <td>{formatRub(order.seller_payout)}</td>
                                        <td>
                                            <span className={`status-badge status-${order.status}`}>
                                                {order.status === 'NEW' && 'Новый заказ'}
                                                {order.status === 'INTRANSIT' && 'В пути'}
                                                {order.status === 'DELIVERED' && 'В пункте выдачи'}
                                                {order.status === 'ISSUED' && 'Выдан'}
                                                {order.status === 'CANCELED' && 'Отменён'}
                                                {order.status === 'REFUSED' && 'Отказ от получения'}
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
                    <h3>Товары по просмотрам</h3>
                    {popularProducts.length > 0 ? (
                        popularProducts.map((product) => (
                            <Link
                                key={product.id}
                                href={route('seller.products.manage', product.id)}
                                className="popular-item popular-item--link"
                            >
                                <img
                                    src={product.image || '/img/products/default.png'}
                                    alt={product.title}
                                />
                                <div>
                                    <p>{product.title}</p>
                                    <small>{formatInt(product.views_count)} просмотров</small>
                                </div>
                            </Link>
                        ))
                    ) : (
                        <p className="empty-data">Нет товаров</p>
                    )}
                </div>
            </div>
        </SellerLayout>
    );
}
