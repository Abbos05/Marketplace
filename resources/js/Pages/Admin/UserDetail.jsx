import React, { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { canManageUserAsStaff, roleOptionsFor, roleOptionsForTarget } from '@/lib/staffAccess';
import { storefrontVisibility, productAdminHref } from '@/lib/productStorefront';
import { ORDER_STATUS_MAP, orderStatusOptionsFor } from '@/lib/orderStatusOptions';
import '../../../css/admin/dashboard.css';

const ROLE_LABELS = { admin: 'Админ', moderator: 'Модератор', seller: 'Продавец', pvz: 'Оператор ПВЗ', user: 'Пользователь' };

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
    phone_otp: 'Код по телефону (SMS)',
    phone_otp_notification: 'Код в уведомлениях',
    phone_otp_2fa: 'Телефон + пароль',
    phone_password_reset: 'Сброс пароля',
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

function Accordion({ title, count, defaultOpen = false, open: controlledOpen, onOpenChange, children }) {
    const [internalOpen, setInternalOpen] = useState(defaultOpen);
    const isControlled = controlledOpen !== undefined;
    const open = isControlled ? controlledOpen : internalOpen;

    const toggleOpen = () => {
        const next = !open;
        if (isControlled) {
            onOpenChange?.(next);
        } else {
            setInternalOpen(next);
        }
    };

    return (
        <div className="adm-accordion">
            <button type="button" className="adm-accordion-head" onClick={toggleOpen}>
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
                    Скачать отчет
                </button>
            </div>
        </div>
    );
}

const PVZ_CLOSURE_LABELS = { none: 'Работает', pending: 'Запрос на закрытие', closed: 'Закрыт окончательно' };

export default function UserDetail({
    auth,
    user,
    orders,
    sellerOrders = [],
    sellerOrdersMeta = {},
    products = [],
    productsMeta = {},
    pvzOverview = null,
    userSessions = [],
    userLoginHistory = [],
    currentSessionId,
    audit_events: auditEventsProp = [],
    seller_history: sellerHistoryProp = null,
}) {
    const sellerHistory = sellerHistoryProp ?? user.seller_history ?? null;
    const auditEvents = auditEventsProp.length ? auditEventsProp : (user.audit_events ?? []);
    const { staffAccess } = usePage().props;
    const closedSellerProfile = user.closed_seller_profile ?? null;
    const sellerRestorePending = user.seller_restore_pending ?? null;
    const pendingShopChanges = user.pending_shop_changes ?? null;
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
    const [expandedPvzOrder, setExpandedPvzOrder] = useState(null);
    const [sellerSalesSort, setSellerSalesSort] = useState(sellerOrdersMeta.sort ?? 'date_desc');
    const [sellerSalesStatus, setSellerSalesStatus] = useState(sellerOrdersMeta.status ?? 'all');
    const [sellerProductsSort, setSellerProductsSort] = useState(productsMeta.sort ?? 'date_desc');
    const [sellerProductsStatus, setSellerProductsStatus] = useState(productsMeta.status ?? 'all');
    const [sellerProductsSearch, setSellerProductsSearch] = useState(productsMeta.search ?? '');
    const [sellerSalesOpen, setSellerSalesOpen] = useState(false);

    useEffect(() => {
        setSellerProductsSearch(productsMeta.search ?? '');
    }, [productsMeta.search]);
    const [sellerProductsOpen, setSellerProductsOpen] = useState(false);

    const showSellerSalesSection = user.has_sales
        || user.role === 'seller'
        || (sellerOrdersMeta.total ?? 0) > 0
        || sellerOrders.length > 0;
    const showSellerProductsSection = user.has_products
        || user.role === 'seller'
        || (sellerHistory?.products_total ?? 0) > 0
        || (productsMeta.total ?? 0) > 0
        || products.length > 0;

    const reloadSellerSection = (overrides = {}) => {
        router.get(route('admin.users.detail', user.id), {
            seller_sales_sort: overrides.salesSort ?? sellerSalesSort,
            seller_sales_status: overrides.salesStatus ?? sellerSalesStatus,
            seller_sales_page: overrides.salesPage ?? 1,
            seller_products_sort: overrides.productsSort ?? sellerProductsSort,
            seller_products_status: overrides.productsStatus ?? sellerProductsStatus,
            seller_products_search: overrides.productsSearch !== undefined ? overrides.productsSearch : sellerProductsSearch,
            seller_products_page: overrides.productsPage ?? 1,
        }, {
            preserveScroll: true,
            preserveState: true,
            only: ['sellerOrders', 'sellerOrdersMeta', 'products', 'productsMeta'],
        });
    };

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

    const messageUser = () => {
        router.post(route('messages.open'), {
            type: 'seller_shop',
            seller_id: user.id,
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

    const approveShopChanges = () => {
        router.post(`/admin/sellers/${user.id}/approve-shop-changes`, {}, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => flash('Изменения магазина одобрены'),
        });
    };

    const rejectShopChanges = () => {
        if (!confirm('Отклонить изменения названия/описания?')) return;
        router.post(`/admin/sellers/${user.id}/reject-shop-changes`, {}, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => flash('Изменения отклонены'),
        });
    };

    const approvePickupStaff = (staffId) => {
        router.post(`/admin/pickup-staff/${staffId}/approve`, {}, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => flash('Оператор ПВЗ одобрен'),
        });
    };

    const rejectPickupStaff = (staffId) => {
        const reason = prompt('Причина отклонения (необязательно):') ?? '';
        router.post(`/admin/pickup-staff/${staffId}/reject`, { reject_reason: reason }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => flash('Заявка ПВЗ отклонена'),
        });
    };

    const pvzOrgLabel = (t) => (t === 'ooo' ? 'ООО' : t === 'ip' ? 'ИП' : t === 'self' ? 'Самозанятый' : t);

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

        const product = (products || []).find((pr) => pr.id === productId);
        if (product && !product.seller_can_publish && newStatus === 'approved') {
            flash('Нельзя вывести на витрину: компания продавца закрыта.', true);
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
                                {pendingShopChanges && !isDeleted && (
                                    <span className="adm-user-flag adm-user-flag--shop">Изменения магазина</span>
                                )}
                                {user.pvz_application?.status === 'pending' && !isDeleted && (
                                    <span className="adm-user-flag adm-user-flag--pvz">Заявка ПВЗ</span>
                                )}
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
                                {roleOptionsForTarget(auth.user, user).map((opt) => (
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
                                className="adm-action-btn adm-btn-view"
                                onClick={messageUser}
                            >
                                Написать
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

                    {pendingShopChanges && (
                        <div className="adm-seller-pending-notice adm-seller-pending-notice--shop-change adm-moderation-highlight">
                            <strong>Изменение названия или описания магазина</strong>
                            <p className="adm-moderation-hint">Сейчас на сайте отображаются старые данные. После одобрения применятся новые.</p>
                            {pendingShopChanges.changes_name && (
                                <div className="adm-shop-meta">
                                    <span className="adm-label">Название</span>
                                    «{pendingShopChanges.current_shop_name}» → «{pendingShopChanges.proposed_shop_name}»
                                </div>
                            )}
                            {pendingShopChanges.changes_description && (
                                <div className="adm-shop-meta">
                                    <span className="adm-label">Описание</span>
                                    {pendingShopChanges.current_description
                                        ? `«${pendingShopChanges.current_description}»`
                                        : '(пусто)'}
                                    {' → '}
                                    {pendingShopChanges.proposed_description
                                        ? `«${pendingShopChanges.proposed_description}»`
                                        : '(пусто)'}
                                </div>
                            )}
                            {pendingShopChanges.requested_at && (
                                <div className="adm-shop-meta">
                                    Отправлено: {new Date(pendingShopChanges.requested_at).toLocaleString('ru-RU')}
                                </div>
                            )}
                            <div className="adm-pending-actions" style={{ marginTop: 12 }}>
                                <button type="button" className="adm-action-btn adm-btn-approve" onClick={approveShopChanges}>
                                    Одобрить изменения
                                </button>
                                <button type="button" className="adm-action-btn adm-btn-reject" onClick={rejectShopChanges}>
                                    Отклонить
                                </button>
                            </div>
                        </div>
                    )}

                    {sellerRestorePending && (
                        <div className="adm-seller-pending-notice adm-seller-pending-notice--restore adm-moderation-highlight">
                            <strong>Заявка на восстановление компании продавца</strong>
                            <div className="adm-shop-meta">Магазин: {sellerRestorePending.shop_name}</div>
                            {sellerRestorePending.inn && <div className="adm-shop-meta">ИНН: {sellerRestorePending.inn}</div>}
                            {sellerRestorePending.requested_at && (
                                <div className="adm-shop-meta">
                                    Отправлена: {new Date(sellerRestorePending.requested_at).toLocaleString('ru-RU')}
                                </div>
                            )}
                            <p className="adm-moderation-hint">
                                После одобрения пользователь снова получит роль продавца. Товары останутся сняты с витрины.
                            </p>
                            <div className="adm-pending-actions" style={{ marginTop: 12 }}>
                                <button type="button" className="adm-action-btn adm-btn-approve" onClick={approveSeller}>Одобрить восстановление</button>
                                <button type="button" className="adm-action-btn adm-btn-reject" onClick={rejectSeller}>Отклонить</button>
                            </div>
                        </div>
                    )}

                    {user.seller_profile && user.role !== 'seller' && !sellerRestorePending && (
                        <div className="adm-seller-pending-notice adm-moderation-highlight">
                            <strong>Заявка продавца ожидает одобрения</strong>
                            <div className="adm-shop-meta">Магазин: {user.seller_profile.shop_name}</div>
                            {user.seller_profile.inn && <div className="adm-shop-meta">ИНН: {user.seller_profile.inn}</div>}
                            <div className="adm-pending-actions" style={{ marginTop: 12 }}>
                                <button type="button" className="adm-action-btn adm-btn-approve" onClick={approveSeller}>Одобрить</button>
                                <button type="button" className="adm-action-btn adm-btn-reject" onClick={rejectSeller}>Отклонить</button>
                            </div>
                        </div>
                    )}

                    {user.pvz_application && user.pvz_application.status === 'pending' && (
                        <div className="adm-seller-pending-notice adm-seller-pending-notice--pvz adm-moderation-highlight">
                            <strong>Заявка на пункт выдачи ожидает одобрения</strong>
                            <p className="adm-moderation-hint">Проверьте данные организации и пункта, затем одобрите или отклоните заявку.</p>
                            <div className="adm-shop-meta">
                                {user.pvz_application.type === 'open' ? 'Открытие нового ПВЗ' : 'Присоединение к пункту'}
                            </div>
                            {user.pvz_application.legal_name && (
                                <div className="adm-shop-meta">Организация: {user.pvz_application.legal_name}</div>
                            )}
                            {user.pvz_application.inn && (
                                <div className="adm-shop-meta">
                                    ИНН: {user.pvz_application.inn}
                                    {user.pvz_application.org_type && ` · ${pvzOrgLabel(user.pvz_application.org_type)}`}
                                </div>
                            )}
                            {user.pvz_application.contact_name && (
                                <div className="adm-shop-meta">
                                    Контакт: {user.pvz_application.contact_name}, {user.pvz_application.contact_phone}
                                </div>
                            )}
                            {user.pvz_application.proposed_title && (
                                <>
                                    <div className="adm-shop-meta">Пункт: {user.pvz_application.proposed_title}</div>
                                    <div className="adm-shop-meta">{user.pvz_application.proposed_address}</div>
                                    {user.pvz_application.proposed_region_name && (
                                        <div className="adm-shop-meta">Регион: {user.pvz_application.proposed_region_name}</div>
                                    )}
                                </>
                            )}
                            {user.pvz_application.pickup_point && (
                                <div className="adm-shop-meta">
                                    Существующий пункт: {user.pvz_application.pickup_point.title}, {user.pvz_application.pickup_point.address}
                                </div>
                            )}
                            {user.pvz_application.premises_info && (
                                <div className="adm-shop-meta">Помещение: {user.pvz_application.premises_info}</div>
                            )}
                            {user.pvz_application.application_comment && (
                                <div className="adm-shop-desc">{user.pvz_application.application_comment}</div>
                            )}
                            <div className="adm-pending-actions" style={{ marginTop: 12 }}>
                                <button
                                    type="button"
                                    className="adm-action-btn adm-btn-approve"
                                    onClick={() => approvePickupStaff(user.pvz_application.id)}
                                >
                                    Одобрить ПВЗ
                                </button>
                                <button
                                    type="button"
                                    className="adm-action-btn adm-btn-reject"
                                    onClick={() => rejectPickupStaff(user.pvz_application.id)}
                                >
                                    Отклонить
                                </button>
                            </div>
                        </div>
                    )}

                    {closedSellerProfile && (
                        <div className="adm-seller-closed-notice">
                            <strong>Компания продавца закрыта</strong>
                            <div className="adm-shop-meta">
                                Магазин: {closedSellerProfile.shop_name}
                                {closedSellerProfile.inn ? ` · ИНН ${closedSellerProfile.inn}` : ''}
                            </div>
                            {closedSellerProfile.closed_at && (
                                <div className="adm-shop-meta">
                                    Закрыта: {new Date(closedSellerProfile.closed_at).toLocaleString('ru-RU')}
                                </div>
                            )}
                        </div>
                    )}

                    {sellerHistory && (sellerHistory.products_total > 0 || sellerHistory.sales_units > 0) && (
                        <div className="adm-seller-stats-row">
                            <span>Товаров: {sellerHistory.products_total}</span>
                            <span>На витрине: {sellerHistory.products_on_catalog}</span>
                            <span>Снято с витрины: {sellerHistory.products_off_catalog}</span>
                            <span>Продано ед.: {sellerHistory.sales_units}</span>
                            <a
                                href={route('admin.reports.user', user.id)}
                                className="adm-action-btn adm-btn-view"
                                style={{ marginLeft: 'auto' }}
                            >
                                Скачать отчёт CSV
                            </a>
                        </div>
                    )}

                    {auditEvents.length > 0 && (
                        <div className="adm-audit-timeline">
                            <h3 className="adm-audit-title">Журнал изменений</h3>
                            <ul className="adm-audit-list">
                                {auditEvents.map((ev) => (
                                    <li key={ev.id} className="adm-audit-item">
                                        <div className="adm-audit-item__head">
                                            <strong>{ev.label}</strong>
                                            <span>{new Date(ev.created_at).toLocaleString('ru-RU')}</span>
                                        </div>
                                        {ev.actor && (
                                            <div className="adm-audit-item__actor">
                                                Кто: {ev.actor.name || ev.actor.email} ({ev.actor.role})
                                            </div>
                                        )}
                                        {ev.event_type === 'seller_company_closed' && (
                                            <div className="adm-audit-item__meta">
                                                Магазин «{ev.meta?.shop_name}» · скрыто товаров: {ev.meta?.products_hidden ?? '—'}
                                                {ev.meta?.previous_role && ` · была роль: ${ev.meta.previous_role}`}
                                            </div>
                                        )}
                                        {ev.event_type === 'role_changed' && (
                                            <div className="adm-audit-item__meta">
                                                {ev.meta?.from} → {ev.meta?.to}
                                            </div>
                                        )}
                                        {ev.event_type === 'seller_company_restore_requested' && (
                                            <div className="adm-audit-item__meta">
                                                Запрос на восстановление «{ev.meta?.shop_name}»
                                            </div>
                                        )}
                                        {ev.event_type === 'seller_company_restored' && (
                                            <div className="adm-audit-item__meta">
                                                Восстановлен магазин «{ev.meta?.shop_name}» (одобрено администратором)
                                            </div>
                                        )}
                                        {ev.event_type === 'seller_company_restore_rejected' && (
                                            <div className="adm-audit-item__meta">
                                                Отклонено восстановление «{ev.meta?.shop_name}»
                                            </div>
                                        )}
                                        {ev.event_type === 'seller_shop_changes_requested' && (
                                            <div className="adm-audit-item__meta">
                                                {ev.meta?.proposed_shop_name && (
                                                    <span>Название: «{ev.meta?.current_shop_name}» → «{ev.meta?.proposed_shop_name}»</span>
                                                )}
                                                {ev.meta?.proposed_description !== undefined && ev.meta?.proposed_shop_name && ' · '}
                                                {ev.meta?.proposed_description !== undefined && ev.meta?.proposed_description !== null && (
                                                    <span>Описание изменено</span>
                                                )}
                                            </div>
                                        )}
                                        {ev.event_type === 'seller_shop_changes_approved' && (
                                            <div className="adm-audit-item__meta">
                                                {ev.meta?.from_shop_name !== ev.meta?.to_shop_name && (
                                                    <span>Название: «{ev.meta?.from_shop_name}» → «{ev.meta?.to_shop_name}»</span>
                                                )}
                                            </div>
                                        )}
                                        {ev.event_type === 'seller_shop_changes_rejected' && (
                                            <div className="adm-audit-item__meta">
                                                Отклонены изменения магазина
                                                {ev.meta?.rejected_shop_name && ` (название: «${ev.meta.rejected_shop_name}»)`}
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* Approved PVZ operator */}
                    {(user.role === 'pvz' || user.pvz_application?.status === 'approved') && (
                        <div className="adm-seller-info">
                            <h3>Пункт выдачи (оператор)</h3>
                            <div className="adm-seller-grid">
                                {user.pvz_point && (
                                    <>
                                        <div><span className="adm-label">Пункт</span>{user.pvz_point.title}</div>
                                        <div><span className="adm-label">Адрес</span>{user.pvz_point.address}</div>
                                    </>
                                )}
                                {user.pvz_application?.legal_name && (
                                    <div><span className="adm-label">Организация</span>{user.pvz_application.legal_name}</div>
                                )}
                                {user.pvz_application?.inn && (
                                    <div><span className="adm-label">ИНН</span>{user.pvz_application.inn}</div>
                                )}
                                {user.pvz_application?.contact_name && (
                                    <div>
                                        <span className="adm-label">Контакт</span>
                                        {user.pvz_application.contact_name}, {user.pvz_application.contact_phone}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Rejected PVZ application */}
                    {user.pvz_application && user.pvz_application.status === 'rejected' && user.role !== 'pvz' && (
                        <div className="adm-seller-pending-notice" style={{ opacity: 0.85 }}>
                            <strong>Заявка на ПВЗ отклонена</strong>
                            {user.pvz_application.reject_reason && (
                                <div className="adm-shop-meta">Причина: {user.pvz_application.reject_reason}</div>
                            )}
                            {user.pvz_application.reviewed_at && (
                                <div className="adm-shop-meta">
                                    Дата: {new Date(user.pvz_application.reviewed_at).toLocaleString('ru-RU')}
                                </div>
                            )}
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

                {/* ── PVZ operator work (not buyer orders) ── */}
                {pvzOverview && (
                    <Accordion title="Работа оператора ПВЗ" count={pvzOverview.orders_at_point?.length ?? 0} defaultOpen>
                        {pvzOverview.point && (
                            <div className="adm-seller-info" style={{ marginBottom: 16 }}>
                                <h3>Пункт выдачи</h3>
                                <div className="adm-seller-grid">
                                    <div><span className="adm-label">Название</span>{pvzOverview.point.title}</div>
                                    <div><span className="adm-label">Адрес</span>{pvzOverview.point.address}</div>
                                    <div><span className="adm-label">Активен</span>{pvzOverview.point.is_active ? 'да' : 'нет'}</div>
                                    <div><span className="adm-label">Статус</span>{PVZ_CLOSURE_LABELS[pvzOverview.point.closure_status] || pvzOverview.point.closure_status}</div>
                                    {pvzOverview.point.closure_reason && (
                                        <div><span className="adm-label">Причина закрытия (запрос)</span>{pvzOverview.point.closure_reason}</div>
                                    )}
                                    {pvzOverview.point.closure_requested_at && (
                                        <div><span className="adm-label">Запрос отправлен</span>{new Date(pvzOverview.point.closure_requested_at).toLocaleString('ru-RU')}</div>
                                    )}
                                    {pvzOverview.point.closure_admin_reject_reason && (
                                        <div><span className="adm-label">Отклонение закрытия админом</span>{pvzOverview.point.closure_admin_reject_reason}</div>
                                    )}
                                    {pvzOverview.point.closure_admin_rejected_at && (
                                        <div><span className="adm-label">Дата отклонения</span>{new Date(pvzOverview.point.closure_admin_rejected_at).toLocaleString('ru-RU')}</div>
                                    )}
                                </div>
                            </div>
                        )}

                        {pvzOverview.counts && (
                            <div className="adm-seller-grid" style={{ marginBottom: 16 }}>
                                <div><span className="adm-label">В пути</span>{pvzOverview.counts.in_transit}</div>
                                <div><span className="adm-label">К выдаче</span>{pvzOverview.counts.delivered}</div>
                                <div><span className="adm-label">Выдано всего</span>{pvzOverview.counts.issued_total}</div>
                                <div><span className="adm-label">Отказов</span>{pvzOverview.counts.refused_total}</div>
                                <div><span className="adm-label">Выдал оператор</span>{pvzOverview.counts.issued_by_operator}</div>
                                <div><span className="adm-label">Отказов оператором</span>{pvzOverview.counts.refused_by_operator ?? 0}</div>
                                <div><span className="adm-label">Вознаграждение</span>{Number(pvzOverview.counts.earnings_total).toLocaleString('ru-RU')} ₽</div>
                            </div>
                        )}

                        {pvzOverview.stats && (
                            <p className="adm-shop-meta" style={{ marginBottom: 12 }}>
                                Текущий месяц ({pvzOverview.stats.period}): выдано {pvzOverview.stats.issued_count}, отказов {pvzOverview.stats.refused_count}, к выплате {Number(pvzOverview.stats.earnings).toLocaleString('ru-RU')} ₽
                            </p>
                        )}

                        {pvzOverview.period_summaries?.length > 0 && (
                            <div className="adm-table-wrap" style={{ marginBottom: 16 }}>
                                <table className="adm-table">
                                    <thead>
                                        <tr><th>Период</th><th>Выдано</th><th>Отказов</th><th>К выплате</th></tr>
                                    </thead>
                                    <tbody>
                                        {pvzOverview.period_summaries.map((row) => (
                                            <tr key={row.period}>
                                                <td>{row.period}</td>
                                                <td>{row.issued_count}</td>
                                                <td>{row.refused_count}</td>
                                                <td>{Number(row.earnings).toLocaleString('ru-RU')} ₽</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {pvzOverview.staff_history?.length > 0 && (
                            <div style={{ marginBottom: 16 }}>
                                <h4 style={{ margin: '0 0 8px' }}>История заявок на ПВЗ</h4>
                                {pvzOverview.staff_history.map((s) => (
                                    <div key={s.id} className="adm-shop-meta" style={{ marginBottom: 8, padding: 10, background: '#f8f9fa', borderRadius: 8 }}>
                                        <strong>{s.status}</strong> · {s.type === 'open' ? 'Открытие' : 'Присоединение'} · {s.created_at ? new Date(s.created_at).toLocaleString('ru-RU') : ''}
                                        {s.proposed_title && <div>Пункт: {s.proposed_title}, {s.proposed_address}</div>}
                                        {s.reject_reason && <div>Отклонение заявки: {s.reject_reason}</div>}
                                    </div>
                                ))}
                            </div>
                        )}

                        <h4 style={{ margin: '0 0 8px' }}>Заказы на пункте выдачи</h4>
                        {pvzOverview.orders_at_point?.length === 0 ? (
                            <p className="adm-empty">Заказов на пункте пока нет</p>
                        ) : (
                            <div className="adm-orders-list adm-orders-list--scroll">
                                {pvzOverview.orders_at_point.map((o) => (
                                    <div key={o.id} className="adm-order-row">
                                        <div
                                            className="adm-order-header"
                                            onClick={() => setExpandedPvzOrder(expandedPvzOrder === o.id ? null : o.id)}
                                        >
                                            <div className="adm-order-header-left">
                                                <strong>#{o.number || o.id}</strong>
                                                <span className={`adm-order-status status-${o.status}`}>{ORDER_STATUS_MAP[o.status] || o.status}</span>
                                                {o.handled_by_operator && <span className="adm-pay-status">✓ оператор</span>}
                                            </div>
                                            <div className="adm-order-header-right">
                                                <span>{o.buyer_name}</span>
                                                <span className="adm-order-total">{Number(o.total).toLocaleString('ru-RU')} ₽</span>
                                                <span className="adm-accordion-arrow">{expandedPvzOrder === o.id ? '▲' : '▼'}</span>
                                            </div>
                                        </div>
                                        {expandedPvzOrder === o.id && (
                                            <div className="adm-order-details">
                                                <div className="adm-order-items-full">
                                                    {o.items.map((item) => (
                                                        <div key={item.id} className="adm-order-item-full">
                                                            {item.product_image && <img src={item.product_image} className="adm-product-thumb" alt="" />}
                                                            <div className="adm-order-item-info">
                                                                <span className="adm-order-item-name">{item.product_name}</span>
                                                                <span className="adm-order-item-meta">
                                                                    {item.quantity} шт. × {Number(item.price_at_purchase).toLocaleString('ru-RU')} ₽
                                                                </span>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </Accordion>
                )}

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
                                                                Завершить
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
                                                        {orderStatusOptionsFor(o, auth.user?.role).map(s => (
                                                            <option key={s.value} value={s.value} disabled={s.disabled}>{s.label}</option>
                                                        ))}
                                                    </select>
                                                    <span className="adm-status-hint">
                                                        Текущий: {ORDER_STATUS_MAP[o.status]}
                                                        {o.payment_status !== 'paid' && ' · выдача после оплаты'}
                                                        {auth.user?.role === 'moderator' && ' · «Выдан»/«Отказ» — только админ или ПВЗ'}
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
                {showSellerSalesSection && (
                    <Accordion
                        title="Продажи (как продавец)"
                        count={sellerOrdersMeta.total ?? sellerOrders.length}
                        open={sellerSalesOpen}
                        onOpenChange={setSellerSalesOpen}
                    >
                        <div className="adm-filter-row" style={{ marginBottom: 12 }}>
                            <select
                                className="adm-status-select"
                                value={sellerSalesSort}
                                onChange={(e) => {
                                    setSellerSalesSort(e.target.value);
                                    reloadSellerSection({ salesSort: e.target.value, salesPage: 1 });
                                }}
                            >
                                <option value="date_desc">Сначала новые</option>
                                <option value="date_asc">Сначала старые</option>
                            </select>
                            <select
                                className="adm-status-select"
                                value={sellerSalesStatus}
                                onChange={(e) => {
                                    setSellerSalesStatus(e.target.value);
                                    reloadSellerSection({ salesStatus: e.target.value, salesPage: 1 });
                                }}
                            >
                                <option value="all">Все статусы</option>
                                {Object.entries(ORDER_STATUS_MAP).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                        </div>
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
                                                    {item.product_id ? (
                                                        <a href={productAdminHref(item.product_id)} className="adm-product-title-link" target="_blank" rel="noreferrer">
                                                            {item.product_name}
                                                        </a>
                                                    ) : (
                                                        <span>{item.product_name}</span>
                                                    )}
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
                            {sellerOrders.length === 0 && (
                                <div className="adm-empty">Продажи по фильтру не найдены</div>
                            )}
                        </div>
                        {sellerOrdersMeta.has_more && (
                            <div style={{ marginTop: 12, textAlign: 'center' }}>
                                <button
                                    type="button"
                                    className="adm-action-btn adm-btn-view"
                                    onClick={() => reloadSellerSection({ salesPage: (sellerOrdersMeta.page ?? 1) + 1 })}
                                >
                                    Показать ещё ({sellerOrders.length} из {sellerOrdersMeta.total})
                                </button>
                            </div>
                        )}
                    </Accordion>
                )}

                {/* ── Products ── */}
                {showSellerProductsSection && (
                    <Accordion
                        title="Товары продавца"
                        count={productsMeta.total ?? products.length ?? sellerHistory?.products_total ?? 0}
                        open={sellerProductsOpen}
                        onOpenChange={setSellerProductsOpen}
                    >
                        <div className="adm-filter-row" style={{ marginBottom: 12 }}>
                            <select
                                className="adm-status-select"
                                value={sellerProductsSort}
                                onChange={(e) => {
                                    setSellerProductsSort(e.target.value);
                                    reloadSellerSection({ productsSort: e.target.value, productsPage: 1 });
                                }}
                            >
                                <option value="date_desc">Дата ↓</option>
                                <option value="date_asc">Дата ↑</option>
                                <option value="name_asc">Название А–Я</option>
                                <option value="name_desc">Название Я–А</option>
                            </select>
                            <select
                                className="adm-status-select"
                                value={sellerProductsStatus}
                                onChange={(e) => {
                                    setSellerProductsStatus(e.target.value);
                                    reloadSellerSection({ productsStatus: e.target.value, productsPage: 1 });
                                }}
                            >
                                <option value="all">Все статусы</option>
                                {PRODUCT_STATUSES.map((s) => (
                                    <option key={s.value} value={s.value}>{s.label}</option>
                                ))}
                            </select>
                            <input
                                type="search"
                                className="admin-search-input"
                                style={{ flex: '1 1 200px', minWidth: 160 }}
                                placeholder="Поиск по названию или ID…"
                                value={sellerProductsSearch}
                                onChange={(e) => setSellerProductsSearch(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        reloadSellerSection({ productsSearch: sellerProductsSearch.trim(), productsPage: 1 });
                                    }
                                }}
                            />
                            <button
                                type="button"
                                className="adm-action-btn adm-btn-view"
                                onClick={() => reloadSellerSection({ productsSearch: sellerProductsSearch.trim(), productsPage: 1 })}
                            >
                                Найти
                            </button>
                            {sellerProductsSearch.trim() !== '' && (
                                <button
                                    type="button"
                                    className="adm-action-btn"
                                    onClick={() => {
                                        setSellerProductsSearch('');
                                        reloadSellerSection({ productsSearch: '', productsPage: 1 });
                                    }}
                                >
                                    Сбросить
                                </button>
                            )}
                        </div>
                        <div className="adm-products-admin-list">
                            {products.map(p => {
                                const storefront = storefrontVisibility(p);
                                return (
                                <div key={p.id} className="adm-product-admin-card">
                                    <img
                                        src={p.image || '/img/products/default.png'}
                                        className="adm-product-admin-img"
                                        alt={p.name}
                                        onError={(e) => {
                                            e.currentTarget.onerror = null;
                                            e.currentTarget.src = '/img/products/default.png';
                                        }}
                                    />
                                    <div className="adm-product-admin-info">
                                        <div className="adm-product-name">
                                            <a href={productAdminHref(p.id)} className="adm-product-title-link" target="_blank" rel="noreferrer">
                                                {p.name}
                                            </a>
                                        </div>
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
                                        <span className={`adm-storefront-badge ${storefront.className}`}>
                                            {storefront.label}
                                        </span>
                                        {!p.seller_can_publish && (
                                            <p className="adm-product-seller-inactive-hint">
                                                Компания закрыта — «Одобрен» не вернёт товар на витрину.
                                            </p>
                                        )}
                                        <select
                                                className="adm-status-select"
                                                value={pendingProductStatus[p.id] ?? p.status}
                                                onChange={(e) => setPendingProductStatus((prev) => ({
                                                    ...prev,
                                                    [p.id]: e.target.value,
                                                }))}
                                            >
                                                {PRODUCT_STATUSES.map(s => (
                                                    <option
                                                        key={s.value}
                                                        value={s.value}
                                                        disabled={!p.seller_can_publish && s.value === 'approved'}
                                                    >
                                                        {s.label}
                                                    </option>
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
                            );
                            })}
                            {products.length === 0 && (
                                <div className="adm-empty">Товары по фильтру не найдены</div>
                            )}
                        </div>
                        {productsMeta.has_more && (
                            <div style={{ marginTop: 12, textAlign: 'center' }}>
                                <button
                                    type="button"
                                    className="adm-action-btn adm-btn-view"
                                    onClick={() => reloadSellerSection({ productsPage: (productsMeta.page ?? 1) + 1 })}
                                >
                                    Показать ещё ({products.length} из {productsMeta.total})
                                </button>
                            </div>
                        )}
                    </Accordion>
                )}
            </div>
        </MainLayout>
    );
}
