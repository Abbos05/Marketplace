import React, { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { canManageUserAsStaff, roleOptionsFor } from '@/lib/staffAccess';
import '../../../css/admin/dashboard.css';

const ROLE_LABELS = { admin: 'Админ', moderator: 'Модератор', seller: 'Продавец', user: 'Пользователь' };

const ORDER_STATUSES = [
    { value: 'NEW', label: 'Новый заказ' },
    { value: 'INTRANSIT', label: 'В пути' },
    { value: 'DELIVERED', label: 'В пункте выдачи' },
    { value: 'ISSUED', label: 'Выдан' },
    { value: 'CANCELED', label: 'Отменён' },
    { value: 'REFUSED', label: 'Отказ от получения' },
];
const ORDER_STATUS_MAP = Object.fromEntries(ORDER_STATUSES.map(s => [s.value, s.label]));
const UNPAID_ALLOWED_STATUSES = ['NEW', 'INTRANSIT', 'DELIVERED', 'CANCELED', 'REFUSED'];

function orderStatusOptionsFor(order) {
    if (order.payment_status === 'paid') {
        return ORDER_STATUSES;
    }
    const allowed = ORDER_STATUSES.filter((s) => UNPAID_ALLOWED_STATUSES.includes(s.value));
    if (allowed.some((s) => s.value === order.status)) {
        return allowed;
    }
    const current = ORDER_STATUSES.find((s) => s.value === order.status);
    return current ? [{ ...current, disabled: true }, ...allowed] : allowed;
}

const PRODUCT_STATUSES = [
    { value: 'moderation', label: 'На модерации' },
    { value: 'approved',   label: 'Одобрен' },
    { value: 'rejected',   label: 'Отклонён' },
    { value: 'hidden',     label: 'Скрыт' },
    { value: 'archived',   label: 'Архив' },
    { value: 'draft',      label: 'Черновик' },
];
const PRODUCT_STATUS_MAP = Object.fromEntries(PRODUCT_STATUSES.map(s => [s.value, s.label]));

const PAYMENT_STATUS_MAP = { pending: 'Ожидает', paid: 'Оплачен', failed: 'Ошибка', refunded: 'Возврат' };
const LOGIN_METHOD_LABELS = {
    password: 'Пароль',
    phone_otp: 'Код по телефону',
    email_otp: 'Код по email',
    phone_password: 'Пароль по телефону',
    google: 'Google',
    yandex: 'Yandex',
    github: 'GitHub',
};

function formatPhone(p) {
    if (!p) return '';
    const d = p.replace(/\D/g, '');
    if (d.length < 7) return '+' + d;
    return `+7 ${d.slice(1,4)} ${d.slice(4,7)} ${d.slice(7,9)} ${d.slice(9,11)}`;
}

function Accordion({ title, count, defaultOpen = false, children }) {
    const [open, setOpen] = useState(defaultOpen);
    return (
        <div className="adm-accordion">
            <button className="adm-accordion-head" onClick={() => setOpen(o => !o)}>
                <span>{title}</span>
                {count !== undefined && <span className="adm-accordion-count">{count}</span>}
                <span className="adm-accordion-arrow">{open ? '▲' : '▼'}</span>
            </button>
            {open && <div className="adm-accordion-body">{children}</div>}
        </div>
    );
}

function parseUserAgent(ua) {
    if (!ua) return { browser: '—', device: '—' };
    let browser = 'Прочее', device = 'Десктоп';
    if (/iPhone|iPad/.test(ua)) device = 'iPhone/iPad';
    else if (/Android/.test(ua)) device = 'Android';
    else if (/Mac OS X/.test(ua)) device = 'macOS';
    else if (/Windows/.test(ua)) device = 'Windows';
    else if (/Linux/.test(ua)) device = 'Linux';
    if (/Edg\//.test(ua)) browser = 'Edge';
    else if (/OPR\/|Opera/.test(ua)) browser = 'Opera';
    else if (/Chrome\//.test(ua)) browser = 'Chrome';
    else if (/Firefox\//.test(ua)) browser = 'Firefox';
    else if (/Safari\//.test(ua)) browser = 'Safari';
    return { browser, device };
}
function timeAgo(iso) {
    if (!iso) return '—';
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (diff < 60)    return `${diff} с назад`;
    if (diff < 3600)  return `${Math.floor(diff / 60)} мин назад`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} ч назад`;
    return `${Math.floor(diff / 86400)} д назад`;
}

function UserReportBar({ userId }) {
    const today = new Date().toISOString().slice(0, 10);
    const monthAgo = new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10);
    const [from, setFrom] = useState(monthAgo);
    const [to, setTo]     = useState(today);

    const download = () => {
        const p = new URLSearchParams({ from, to }).toString();
        window.location.href = `/admin/reports/user/${userId}?${p}`;
    };

    const quick = (days) => {
        const f = new Date(Date.now() - (days - 1) * 86400000).toISOString().slice(0, 10);
        setFrom(f); setTo(today);
    };

    return (
        <div className="adm-export-bar adm-export-bar-card">
            <div className="adm-export-title">Скачать отчёт пользователя</div>
            <div className="adm-export-row">
                <label className="adm-export-field">
                    <span>С</span>
                    <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="adm-date-input" />
                </label>
                <label className="adm-export-field">
                    <span>По</span>
                    <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="adm-date-input" />
                </label>
                <div className="adm-quick-range">
                    <button type="button" className="adm-filter-pill" onClick={() => quick(7)}>7д</button>
                    <button type="button" className="adm-filter-pill" onClick={() => quick(30)}>30д</button>
                    <button type="button" className="adm-filter-pill" onClick={() => quick(90)}>90д</button>
                    <button type="button" className="adm-filter-pill" onClick={() => { setFrom(''); setTo(''); }}>Всё</button>
                </div>
                <button onClick={download} className="adm-action-btn adm-btn-approve adm-btn-big">
                    📊 Скачать Excel
                </button>
            </div>
        </div>
    );
}

export default function UserDetail({ auth, user, orders, sellerOrders, products, userSessions = [], userLoginHistory = [], currentSessionId }) {
    const { staffAccess } = usePage().props;
    const panelTitle = staffAccess?.panelTitle ?? 'Панель администратора';
    const canManage = canManageUserAsStaff(auth?.user, user);

    const [message, setMessage]       = useState(null);
    const [selectedRole, setSelectedRole]   = useState(user.role);
    const [productComments, setProductComments] = useState({});
    const [pendingProductStatus, setPendingProductStatus] = useState({});
    const [expandedOrder, setExpandedOrder] = useState(null);
    const [sessionQuery, setSessionQuery] = useState('');
    const [loginQuery, setLoginQuery] = useState('');
    const [buyerOrderQuery, setBuyerOrderQuery] = useState('');
    const [buyerOrderFilter, setBuyerOrderFilter] = useState('all');

    const flash = (msg, isError = false) => {
        setMessage({ text: msg, error: isError });
        setTimeout(() => setMessage(null), 4000);
    };

    const isProtected = user.id === 1 || user.id === auth?.user?.id;
    const isDeleted   = !!user.deleted_at;
    const visibleSessions = userSessions.filter((s) => {
        if (!sessionQuery) return true;
        const q = sessionQuery.toLowerCase();
        const parsed = parseUserAgent(s.user_agent);
        return (s.ip_address || '').includes(sessionQuery)
            || (parsed.browser || '').toLowerCase().includes(q)
            || (parsed.device || '').toLowerCase().includes(q)
            || String(s.id || '').toLowerCase().includes(q);
    });
    const visibleLoginHistory = userLoginHistory.filter((event) => {
        if (!loginQuery) return true;
        const q = loginQuery.toLowerCase();
        const parsed = parseUserAgent(event.user_agent);
        const method = LOGIN_METHOD_LABELS[event.login_method] || event.login_method || 'Вход';
        return (event.ip_address || '').includes(loginQuery)
            || method.toLowerCase().includes(q)
            || (parsed.browser || '').toLowerCase().includes(q)
            || (parsed.device || '').toLowerCase().includes(q)
            || String(event.id || '').toLowerCase().includes(q);
    });
    const visibleBuyerOrders = orders.filter((order) => {
        if (buyerOrderFilter === 'new' && order.status !== 'NEW') return false;
        if (buyerOrderFilter === 'active' && ['ISSUED', 'CANCELED', 'REFUSED'].includes(order.status)) return false;
        if (buyerOrderFilter === 'completed' && order.status !== 'ISSUED') return false;
        if (buyerOrderFilter === 'cancelled' && !['CANCELED', 'REFUSED'].includes(order.status)) return false;

        if (!buyerOrderQuery) return true;
        const q = buyerOrderQuery.toLowerCase();
        return String(order.id || '').includes(q)
            || String(order.number || '').toLowerCase().includes(q)
            || String(order.total || '').includes(q)
            || (ORDER_STATUS_MAP[order.status] || order.status || '').toLowerCase().includes(q)
            || (PAYMENT_STATUS_MAP[order.payment_status] || order.payment_status || '').toLowerCase().includes(q)
            || (order.items || []).some((item) => (item.product_name || '').toLowerCase().includes(q));
    });

    const blockUser = () => {
        router.put(`/admin/users/${user.id}/block`, {}, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => flash(user.is_blocked ? 'Пользователь разблокирован' : 'Пользователь заблокирован'),
        });
    };

    const changeRole = (role) => {
        setSelectedRole(role);
        router.patch(`/admin/users/${user.id}/role`, { role }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => flash('Роль изменена'),
        });
    };

    const deleteUser = () => {
        if (user.has_orders) {
            flash('Нельзя удалить пользователя: есть активные заказы', true);
            return;
        }
        if (user.role === 'seller' && (user.has_products || user.has_sales)) {
            flash('Нельзя удалить продавца с товарами или продажами', true);
            return;
        }
        if (!confirm(`Деактивировать аккаунт ${user.name || '#' + user.id}? Восстановление будет возможно.`)) return;
        router.delete(`/admin/users/${user.id}`, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => flash('Аккаунт деактивирован'),
        });
    };

    const restoreUser = () => {
        if (!confirm('Восстановить аккаунт?')) return;
        router.post(`/admin/users/${user.id}/restore`, {}, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => flash('Аккаунт восстановлен'),
        });
    };

    const kickSession = (sessionId) => {
        if (sessionId === currentSessionId) {
            flash('Нельзя завершить текущую сессию', true);
            return;
        }
        if (!confirm('Завершить сессию пользователя?')) return;
        router.delete(`/admin/sessions/${sessionId}`, {
            preserveScroll: true, preserveState: false,
            onSuccess: () => flash('Сессия завершена'),
        });
    };

    const approveSeller = () => {
        router.post(`/admin/sellers/${user.id}/approve`, {}, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => flash('Продавец одобрен'),
        });
    };

    const rejectSeller = () => {
        if (!confirm('Отклонить заявку продавца?')) return;
        router.post(`/admin/sellers/${user.id}/reject`, {}, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => flash('Заявка отклонена'),
        });
    };

    useEffect(() => {
        const comments = {};
        const statuses = {};
        (products || []).forEach((p) => {
            comments[p.id] = p.moderation_comment || '';
            statuses[p.id] = p.status;
        });
        setProductComments((prev) => ({ ...comments, ...prev }));
        setPendingProductStatus((prev) => ({ ...statuses, ...prev }));
    }, [products]);

    const applyProductStatus = (productId, currentStatus) => {
        const newStatus = pendingProductStatus[productId] ?? currentStatus;
        const comment = (productComments[productId] || '').trim();

        if (newStatus === 'rejected' && !comment) {
            flash('При отклонении укажите комментарий для продавца — он увидит его в кабинете.', true);
            return;
        }

        router.post(`/admin/products/${productId}/status`, {
            status: newStatus,
            moderation_comment: comment,
        }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => flash(`Статус товара #${productId} изменён`),
        });
    };

    const updateOrderStatus = (orderId, status) => {
        router.post(`/admin/orders/${orderId}/status`, { status }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => flash(`Статус заказа #${orderId} обновлён`),
        });
    };

    const productStatusColor = (s) => {
        if (s === 'approved') return 'adm-pstatus-approved';
        if (s === 'rejected') return 'adm-pstatus-rejected';
        if (s === 'moderation') return 'adm-pstatus-moderation';
        if (s === 'hidden' || s === 'archived') return 'adm-pstatus-hidden';
        return 'adm-pstatus-default';
    };

    return (
        <MainLayout auth={auth}>
            <Head title={`Пользователь #${user.id}`} />

            {message && (
                <div className={`adm-flash ${message.error ? 'adm-flash-error' : ''}`}
                     onClick={() => setMessage(null)}>
                    {message.text}
                </div>
            )}

            <div className="adm-detail-page">
                <div className="adm-detail-nav">
                    <a href="/admin/dashboard" className="adm-back-link">← {panelTitle}</a>
                </div>

                {/* ── User info card ── */}
                <div className="adm-detail-card">
                    <div className="adm-detail-user-row">
                        <img src={user.avatar || '/img/profiles/profile.png'} className="adm-detail-avatar" alt="avatar" />
                        <div className="adm-detail-user-info">
                            <h1 className="adm-detail-name">
                                {[user.name, user.last_name].filter(Boolean).join(' ') || 'Без имени'}
                            </h1>
                            <div className="adm-detail-meta">
                                {user.email && <span>{user.email}</span>}
                                {user.phone && <span>{formatPhone(user.phone)}</span>}
                                <span>ID: {user.id}</span>
                                {user.created_at && (
                                    <span>Зарег.: {new Date(user.created_at).toLocaleDateString('ru-RU')}</span>
                                )}
                            </div>
                            <div className="adm-detail-badges">
                                <span className={`adm-role-badge role-${user.role}`}>
                                    {ROLE_LABELS[user.role] || user.role}
                                </span>
                                {isDeleted && (
                                    <span className="adm-status-blocked">
                                        Удалён · {new Date(user.deleted_at).toLocaleDateString('ru-RU')}
                                    </span>
                                )}
                                {!isDeleted && user.is_blocked && (
                                    <span className="adm-status-blocked">Заблокирован</span>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Deleted notice */}
                    {isDeleted && (
                        <div className="adm-deleted-notice">
                            <div>
                                <strong>Аккаунт деактивирован</strong>
                                <div className="adm-user-sub">
                                    Данные сохранены. Восстановите аккаунт, чтобы он снова стал активным.
                                </div>
                            </div>
                            <button className="adm-action-btn adm-btn-approve" onClick={restoreUser}>
                                Восстановить
                            </button>
                        </div>
                    )}

                    {canManage && !isDeleted && (
                        <div className="adm-detail-actions">
                            <select
                                className="admin-role-select"
                                value={selectedRole}
                                onChange={(e) => changeRole(e.target.value)}
                            >
                                {roleOptionsFor(auth.user).map((opt) => (
                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                ))}
                            </select>
                            <button
                                className={`adm-action-btn ${user.is_blocked ? 'adm-btn-unblock' : 'adm-btn-block'}`}
                                onClick={blockUser}
                            >
                                {user.is_blocked ? 'Разблокировать' : 'Заблокировать'}
                            </button>
                            <button
                                className="adm-action-btn adm-btn-reject"
                                onClick={deleteUser}
                                disabled={user.has_orders || (user.role === 'seller' && (user.has_products || user.has_sales))}
                                title={user.has_orders ? 'Есть активные заказы' : (user.has_products || user.has_sales) ? 'Есть товары или продажи' : ''}
                            >
                                Деактивировать
                            </button>
                        </div>
                    )}

                    {/* Pending seller approval */}
                    {user.seller_profile && user.role !== 'seller' && (
                        <div className="adm-seller-pending-notice">
                            <strong>Заявка продавца ожидает одобрения</strong>
                            <div className="adm-shop-meta">Магазин: {user.seller_profile.shop_name}</div>
                            {user.seller_profile.inn && <div className="adm-shop-meta">ИНН: {user.seller_profile.inn}</div>}
                            <div className="adm-pending-actions" style={{ marginTop: 10 }}>
                                <button className="adm-action-btn adm-btn-approve" onClick={approveSeller}>Одобрить</button>
                                <button className="adm-action-btn adm-btn-reject" onClick={rejectSeller}>Отклонить</button>
                            </div>
                        </div>
                    )}

                    {/* Approved seller info */}
                    {user.seller_profile && user.role === 'seller' && (
                        <div className="adm-seller-info">
                            <h3>Информация о магазине</h3>
                            <div className="adm-seller-grid">
                                <div><span className="adm-label">Магазин</span>{user.seller_profile.shop_name}</div>
                                {user.seller_profile.inn && <div><span className="adm-label">ИНН</span>{user.seller_profile.inn}</div>}
                                {user.seller_profile.pickup_address && <div><span className="adm-label">Самовывоз</span>{user.seller_profile.pickup_address}</div>}
                                {user.seller_profile.legal_address && <div><span className="adm-label">Юр. адрес</span>{user.seller_profile.legal_address}</div>}
                                <div><span className="adm-label">Рейтинг</span>⭐ {user.seller_profile.rating || 0}</div>
                                <div><span className="adm-label">Продаж</span>{user.seller_profile.total_sales || 0}</div>
                            </div>
                        </div>
                    )}
                </div>

                {/* ── Report download ── */}
                <UserReportBar userId={user.id} />

                {/* ── Sessions history ── */}
                <Accordion title="История сессий" count={visibleSessions.length}>
                    {userSessions.length === 0 ? (
                        <p className="adm-empty">Сессий нет</p>
                    ) : (
                        <>
                            <div className="adm-filter-row adm-detail-filter-row">
                                <input
                                    type="text"
                                    className="admin-search-input adm-flex-search"
                                    placeholder="Поиск по IP, браузеру, устройству..."
                                    value={sessionQuery}
                                    onChange={(e) => setSessionQuery(e.target.value)}
                                />
                            </div>
                            <div className="adm-table-wrap adm-table-wrap--detail-sessions">
                                <table className="adm-table">
                                    <thead>
                                        <tr>
                                            <th>IP-адрес</th>
                                            <th>Устройство</th>
                                            <th>Активность</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visibleSessions.map(s => {
                                            const { browser, device } = parseUserAgent(s.user_agent);
                                            const isCurrent = s.id === currentSessionId;
                                            return (
                                                <tr key={s.id}>
                                                    <td><code className="adm-code">{s.ip_address || '—'}</code></td>
                                                    <td>
                                                        <div className="adm-device-info">
                                                            <span>{browser}</span>
                                                            <span className="adm-user-sub">{device}</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div className="adm-activity-cell">
                                                            <span className={`adm-online-dot ${s.is_online ? 'online' : ''}`} />
                                                            <span>{timeAgo(s.last_activity)}</span>
                                                            {isCurrent && <span className="adm-current-badge"> (текущая)</span>}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        {!isCurrent && (
                                                            <button
                                                                className="adm-action-btn adm-btn-reject"
                                                                onClick={() => kickSession(s.id)}
                                                            >
                                                                Кикнуть
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                                {visibleSessions.length === 0 && (
                                    <div className="adm-empty">Сессии по фильтру не найдены</div>
                                )}
                            </div>
                        </>
                    )}
                </Accordion>

                <Accordion title="История входов" count={visibleLoginHistory.length}>
                    {userLoginHistory.length === 0 ? (
                        <p className="adm-empty">Истории входов нет</p>
                    ) : (
                        <>
                            <div className="adm-filter-row adm-detail-filter-row">
                                <input
                                    type="text"
                                    className="admin-search-input adm-flex-search"
                                    placeholder="Поиск по IP, методу входа, браузеру..."
                                    value={loginQuery}
                                    onChange={(e) => setLoginQuery(e.target.value)}
                                />
                            </div>
                            <div className="adm-table-wrap adm-table-wrap--detail-logins">
                                <table className="adm-table">
                                    <thead>
                                        <tr>
                                            <th>Метод</th>
                                            <th>IP-адрес</th>
                                            <th>Устройство</th>
                                            <th>Когда</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visibleLoginHistory.map(event => {
                                            const { browser, device } = parseUserAgent(event.user_agent);
                                            return (
                                                <tr key={event.id}>
                                                    <td>
                                                        <span className="adm-login-method">
                                                            {LOGIN_METHOD_LABELS[event.login_method] || event.login_method || 'Вход'}
                                                        </span>
                                                    </td>
                                                    <td><code className="adm-code">{event.ip_address || '—'}</code></td>
                                                    <td>
                                                        <div className="adm-device-info">
                                                            <span>{browser}</span>
                                                            <span className="adm-user-sub">{device}</span>
                                                        </div>
                                                    </td>
                                                    <td>{timeAgo(event.created_at)}</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                                {visibleLoginHistory.length === 0 && (
                                    <div className="adm-empty">Входы по фильтру не найдены</div>
                                )}
                            </div>
                        </>
                    )}
                </Accordion>

                {/* ── Buyer orders ── */}
                <Accordion title="Заказы покупателя" count={visibleBuyerOrders.length} defaultOpen={orders.length > 0}>
                    {orders.length === 0 ? (
                        <p className="adm-empty">Заказов нет</p>
                    ) : (
                        <>
                            <div className="adm-filter-row adm-detail-filter-row">
                                <button className={`adm-filter-pill ${buyerOrderFilter === 'all' ? 'active' : ''}`} onClick={() => setBuyerOrderFilter('all')}>Все ({orders.length})</button>
                                <button className={`adm-filter-pill ${buyerOrderFilter === 'new' ? 'active' : ''}`} onClick={() => setBuyerOrderFilter('new')}>Новые ({orders.filter(o => o.status === 'NEW').length})</button>
                                <button className={`adm-filter-pill ${buyerOrderFilter === 'active' ? 'active' : ''}`} onClick={() => setBuyerOrderFilter('active')}>Активные ({orders.filter(o => !['ISSUED', 'CANCELED', 'REFUSED'].includes(o.status)).length})</button>
                                <button className={`adm-filter-pill ${buyerOrderFilter === 'completed' ? 'active' : ''}`} onClick={() => setBuyerOrderFilter('completed')}>Выданы ({orders.filter(o => o.status === 'ISSUED').length})</button>
                                <button className={`adm-filter-pill ${buyerOrderFilter === 'cancelled' ? 'active' : ''}`} onClick={() => setBuyerOrderFilter('cancelled')}>Отмены ({orders.filter(o => ['CANCELED', 'REFUSED'].includes(o.status)).length})</button>
                                <input
                                    type="text"
                                    className="admin-search-input adm-flex-search"
                                    placeholder="Поиск по номеру, сумме, статусу, товару..."
                                    value={buyerOrderQuery}
                                    onChange={(e) => setBuyerOrderQuery(e.target.value)}
                                />
                            </div>
                            <div className="adm-orders-list adm-orders-list--scroll">
                            {visibleBuyerOrders.map(o => (
                                <div key={o.id} className="adm-order-row">
                                    {/* Order header — click to expand */}
                                    <div
                                        className="adm-order-header"
                                        onClick={() => setExpandedOrder(expandedOrder === o.id ? null : o.id)}
                                    >
                                        <div className="adm-order-header-left">
                                            <strong>#{o.number || o.id}</strong>
                                            <span className={`adm-order-status status-${o.status}`}>
                                                {ORDER_STATUS_MAP[o.status] || o.status}
                                            </span>
                                            {o.payment_status && (
                                                <span className="adm-pay-status">
                                                    💳 {PAYMENT_STATUS_MAP[o.payment_status] || o.payment_status}
                                                </span>
                                            )}
                                        </div>
                                        <div className="adm-order-header-right">
                                            <span className="adm-order-total">{Number(o.total).toLocaleString('ru-RU')} ₽</span>
                                            <span className="adm-order-date">{new Date(o.created_at).toLocaleDateString('ru-RU')}</span>
                                            <span className="adm-accordion-arrow">{expandedOrder === o.id ? '▲' : '▼'}</span>
                                        </div>
                                    </div>

                                    {expandedOrder === o.id && (
                                        <div className="adm-order-details">
                                            {/* Items */}
                                            <div className="adm-order-items-full">
                                                {o.items.map(item => (
                                                    <div key={item.id} className="adm-order-item-full">
                                                        {item.product_image && (
                                                            <img src={item.product_image} className="adm-product-thumb" alt="" />
                                                        )}
                                                        <div className="adm-order-item-info">
                                                            <span className="adm-order-item-name">{item.product_name}</span>
                                                            <span className="adm-order-item-meta">
                                                                {item.quantity} шт. × {Number(item.price_at_purchase).toLocaleString('ru-RU')} ₽
                                                                = {(item.quantity * item.price_at_purchase).toLocaleString('ru-RU')} ₽
                                                            </span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>

                                            {/* Delivery & payment info */}
                                            <div className="adm-order-meta-grid">
                                                {o.delivery_address && (
                                                    <div><span className="adm-label">Адрес доставки</span>{o.delivery_address}</div>
                                                )}
                                                {o.delivery_method && (
                                                    <div><span className="adm-label">Способ доставки</span>{o.delivery_method}</div>
                                                )}
                                                {o.discount > 0 && (
                                                    <div><span className="adm-label">Скидка</span>{Number(o.discount).toLocaleString('ru-RU')} ₽</div>
                                                )}
                                                {o.comment && (
                                                    <div className="adm-order-comment"><span className="adm-label">Комментарий</span>{o.comment}</div>
                                                )}
                                            </div>

                                            {/* Admin status change */}
                                            <div className="adm-order-status-change">
                                                <span className="adm-label">Изменить статус заказа:</span>
                                                <div className="adm-order-status-row">
                                                    <select
                                                        className="adm-status-select"
                                                        defaultValue={o.status}
                                                        onChange={(e) => updateOrderStatus(o.id, e.target.value)}
                                                    >
                                                        {orderStatusOptionsFor(o).map(s => (
                                                            <option key={s.value} value={s.value} disabled={s.disabled}>{s.label}</option>
                                                        ))}
                                                    </select>
                                                    <span className="adm-status-hint">
                                                        Текущий: {ORDER_STATUS_MAP[o.status]}
                                                        {o.payment_status !== 'paid' && ' · выдача после оплаты'}
                                                    </span>
                                                    <a
                                                        href={`/admin/reports/order/${o.id}`}
                                                        className="adm-action-btn adm-btn-view"
                                                        title="Скачать чек этого заказа"
                                                    >
                                                        🧾 Чек
                                                    </a>
                                                    {o.payment_status === 'paid' && (
                                                        <a href={`/order/${o.id}/document/payment`} className="adm-action-btn adm-btn-view" title="Документ об оплате">💳 Оплата</a>
                                                    )}
                                                    {o.payment_status === 'refunded' && (
                                                        <a href={`/order/${o.id}/document/refund`} className="adm-action-btn adm-btn-view" title="Документ о возврате">↩ Возврат</a>
                                                    )}
                                                    {o.status === 'CANCELED' && (
                                                        <a href={`/order/${o.id}/document/cancel`} className="adm-action-btn adm-btn-view" title="Документ об отмене">✕ Отмена</a>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Order status timeline */}
                                            <div className="adm-order-timeline">
                                                {['NEW','INTRANSIT','DELIVERED','ISSUED'].map((s, i, arr) => {
                                                    const statusOrder = arr.indexOf(o.status);
                                                    const thisOrder   = i;
                                                    const isDone    = thisOrder <= statusOrder && !['CANCELED','REFUSED'].includes(o.status);
                                                    const isCurrent = s === o.status;
                                                    return (
                                                        <div key={s} className={`adm-timeline-step ${isDone ? 'done' : ''} ${isCurrent ? 'current' : ''}`}>
                                                            <div className="adm-timeline-dot" />
                                                            <div className="adm-timeline-label">{ORDER_STATUS_MAP[s]}</div>
                                                        </div>
                                                    );
                                                })}
                                                {['CANCELED','REFUSED'].includes(o.status) && (
                                                    <div className={`adm-timeline-step ${o.status} current`}>
                                                        <div className="adm-timeline-dot" />
                                                        <div className="adm-timeline-label">{ORDER_STATUS_MAP[o.status]}</div>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                                {visibleBuyerOrders.length === 0 && (
                                    <div className="adm-empty">Заказы по фильтру не найдены</div>
                                )}
                            </div>
                        </>
                    )}
                </Accordion>

                {/* ── Seller orders ── */}
                {sellerOrders.length > 0 && (
                    <Accordion title="Продажи (как продавец)" count={sellerOrders.length}>
                        <div className="adm-table-wrap">
                            <table className="adm-table">
                                <thead>
                                    <tr><th>Заказ</th><th>Товар</th><th>Кол-во</th><th>Цена</th><th>Статус</th><th>Дата</th></tr>
                                </thead>
                                <tbody>
                                    {sellerOrders.map(item => (
                                        <tr key={item.id}>
                                            <td>#{item.order_number}</td>
                                            <td>
                                                <div className="adm-cell-user">
                                                    {item.product_image && (
                                                        <img src={item.product_image} className="adm-product-thumb" alt="" />
                                                    )}
                                                    <span>{item.product_name}</span>
                                                </div>
                                            </td>
                                            <td>{item.quantity}</td>
                                            <td>{Number(item.price_at_purchase).toLocaleString('ru-RU')} ₽</td>
                                            <td>
                                                <span className={`adm-order-status status-${item.order_status}`}>
                                                    {ORDER_STATUS_MAP[item.order_status] || item.order_status}
                                                </span>
                                            </td>
                                            <td>{new Date(item.created_at).toLocaleDateString('ru-RU')}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Accordion>
                )}

                {/* ── Products ── */}
                {products.length > 0 && (
                    <Accordion title="Товары продавца" count={products.length}>
                        <div className="adm-products-admin-list">
                            {products.map(p => (
                                <div key={p.id} className="adm-product-admin-card">
                                    <img
                                        src={p.image || '/img/products/default.png'}
                                        className="adm-product-admin-img"
                                        alt={p.name}
                                    />
                                    <div className="adm-product-admin-info">
                                        <div className="adm-product-name">{p.name}</div>
                                        <div className="adm-product-price">{Number(p.min_price).toLocaleString('ru-RU')} ₽</div>
                                        <div className="adm-product-meta">{p.variants_count} вариантов</div>
                                        {p.moderation_comment && (
                                            <div className="adm-product-comment">Комментарий: {p.moderation_comment}</div>
                                        )}
                                    </div>
                                    <div className="adm-product-admin-actions">
                                        <span className={`adm-product-status-badge ${productStatusColor(p.status)}`}>
                                            {PRODUCT_STATUS_MAP[p.status] || p.status}
                                        </span>
                                        <select
                                                className="adm-status-select"
                                                value={pendingProductStatus[p.id] ?? p.status}
                                                onChange={(e) => setPendingProductStatus((prev) => ({
                                                    ...prev,
                                                    [p.id]: e.target.value,
                                                }))}
                                            >
                                                {PRODUCT_STATUSES.map(s => (
                                                    <option key={s.value} value={s.value}>{s.label}</option>
                                                ))}
                                            </select>
                                            <input
                                                type="text"
                                                className="adm-comment-input"
                                                placeholder={ (pendingProductStatus[p.id] ?? p.status) === 'rejected' ? 'Комментарий для продавца *' : 'Комментарий для продавца' }
                                                value={productComments[p.id] ?? ''}
                                                onChange={(e) => setProductComments((prev) => ({
                                                    ...prev,
                                                    [p.id]: e.target.value,
                                                }))}
                                            />
                                            <button
                                                type="button"
                                                className="adm-action-btn adm-btn-save" onClick={() => applyProductStatus(p.id, p.status)}
                                            >
                                                OK
                                            </button>
                                        <a
                                            href={`/product/${p.id}`}
                                            className="adm-action-btn adm-btn-view"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Открыть
                                        </a>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Accordion>
                )}
            </div>
        </MainLayout>
    );
}
