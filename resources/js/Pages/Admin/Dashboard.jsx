import React, { useState, useEffect, useCallback } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import BarcodeScannerModal from '@/Components/BarcodeScannerModal';
import { ORDER_STATUS_MAP, orderStatusOptionsFor } from '@/lib/orderStatusOptions';
import '../../../css/admin/dashboard.css';

const ROLE_LABELS = { admin: 'Админ', moderator: 'Модератор', seller: 'Продавец', pvz: 'Оператор ПВЗ', user: 'Пользователь' };

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
    if (diff < 60)        return `${diff} с назад`;
    if (diff < 3600)      return `${Math.floor(diff / 60)} мин назад`;
    if (diff < 86400)     return `${Math.floor(diff / 3600)} ч назад`;
    return `${Math.floor(diff / 86400)} д назад`;
}

function formatChartTick(d, granularity = 'day') {
    if (d.label) return d.label;
    if (!d.date) return '';
    const date = new Date(`${d.date}T12:00:00`);
    if (Number.isNaN(date.getTime())) return d.date;
    if (granularity === 'year') return String(date.getFullYear());
    if (granularity === 'month') {
        const months = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
        return `${months[date.getMonth()]} ${date.getFullYear()}`;
    }
    return `${date.getDate().toString().padStart(2, '0')}.${(date.getMonth() + 1).toString().padStart(2, '0')}`;
}

function RevenueChart({ data = [], chartId = 'adm-revenue-chart-svg', rangeLabel = '', periodLabel = '', granularity = 'day' }) {
    const width  = 720;
    const height = 220;
    const padL   = 50;
    const padR   = 10;
    const padT   = 16;
    const padB   = 32;
    const innerW = width - padL - padR;
    const innerH = height - padT - padB;

    if (!data.length) return <div className="adm-empty">Нет данных по выручке</div>;

    const maxRevenue = Math.max(1, ...data.map(d => Number(d.revenue) || 0));
    const barW = innerW / data.length;
    const tickStep = Math.max(1, Math.floor(data.length / 7));

    const yTicks = 4;
    const yLabels = Array.from({ length: yTicks + 1 }, (_, i) => Math.round((maxRevenue / yTicks) * i));

    const total = data.reduce((acc, d) => acc + Number(d.revenue || 0), 0);
    const orders = data.reduce((acc, d) => acc + Number(d.count || 0), 0);
    const revenueCaption = rangeLabel
        ? `Выручка · ${rangeLabel}${periodLabel ? ` (${periodLabel})` : ''}`
        : 'Выручка за период';

    return (
        <div className="adm-chart-wrap">
            <div className="adm-chart-summary">
                <div className="adm-chart-summary-block">
                    <div className="adm-chart-summary-value">{total.toLocaleString('ru-RU')} ₽</div>
                    <div className="adm-chart-summary-label">{revenueCaption}</div>
                </div>
                <div className="adm-chart-summary-block">
                    <div className="adm-chart-summary-value">{orders.toLocaleString('ru-RU')}</div>
                    <div className="adm-chart-summary-label">Заказов</div>
                </div>
                <div className="adm-chart-summary-block">
                    <div className="adm-chart-summary-value">
                        {orders > 0 ? Math.round(total / orders).toLocaleString('ru-RU') : 0} ₽
                    </div>
                    <div className="adm-chart-summary-label">Средний чек</div>
                </div>
            </div>
            <svg id={chartId} className="adm-chart-svg" viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="xMidYMid meet">
                {yLabels.map((v, i) => {
                    const y = padT + innerH - (innerH * i) / yTicks;
                    return (
                        <g key={i}>
                            <line x1={padL} x2={width - padR} y1={y} y2={y} className="adm-chart-grid" />
                            <text x={padL - 6} y={y + 4} textAnchor="end" className="adm-chart-axis-label">
                                {v >= 1000 ? `${Math.round(v / 1000)}k` : v}
                            </text>
                        </g>
                    );
                })}

                {data.map((d, i) => {
                    const v = Number(d.revenue) || 0;
                    const h = (v / maxRevenue) * innerH;
                    const x = padL + i * barW + 1.5;
                    const y = padT + innerH - h;
                    const label = formatChartTick(d, granularity);
                    return (
                        <g key={`${d.date}-${i}`}>
                            <rect
                                x={x}
                                y={y}
                                width={Math.max(2, barW - 3)}
                                height={h}
                                className="adm-chart-bar"
                                rx={2}
                            >
                                <title>{`${label}: ${v.toLocaleString('ru-RU')} ₽ · ${d.count} зак.`}</title>
                            </rect>
                            {i % tickStep === 0 && (
                                <text
                                    x={x + barW / 2}
                                    y={padT + innerH + 16}
                                    textAnchor="middle"
                                    className="adm-chart-axis-label"
                                >{label}</text>
                            )}
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}

const CHART_PRESETS = [
    { key: '7d', label: '7 дн.' },
    { key: '30d', label: '30 дн.' },
    { key: 'month', label: 'Месяц' },
    { key: 'year', label: 'Год' },
    { key: 'all', label: 'Всё время' },
];

function OverviewRevenueSection({ initialChart = [] }) {
    const today = new Date().toISOString().slice(0, 10);
    const monthAgo = new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10);
    const [from, setFrom] = useState(monthAgo);
    const [to, setTo] = useState(today);
    const [status, setStatus] = useState('paid');
    const [period, setPeriod] = useState('30d');
    const [chartData, setChartData] = useState(initialChart);
    const [chartMeta, setChartMeta] = useState({ range_label: '30 дней', period_label: 'по дням', granularity: 'day' });
    const [chartLoading, setChartLoading] = useState(false);

    const exportQuery = () => {
        const params = new URLSearchParams({ from, to, status });
        if (period) params.set('period', period);
        return params.toString();
    };

    const loadChart = useCallback(async () => {
        setChartLoading(true);
        try {
            const params = new URLSearchParams();
            if (period) {
                params.set('period', period);
            } else {
                params.set('from', from);
                params.set('to', to);
            }
            const res = await fetch(`/admin/reports/revenue/chart?${params}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await res.json();
            setChartData(json.data ?? []);
            setChartMeta({
                range_label: json.range_label ?? '',
                period_label: json.period_label ?? '',
                granularity: json.granularity ?? 'day',
            });
            if (json.from) setFrom(json.from);
            if (json.to) setTo(json.to);
        } catch {
            setChartData([]);
        } finally {
            setChartLoading(false);
        }
    }, [from, to, period]);

    useEffect(() => {
        loadChart();
    }, [loadChart]);

    const applyPreset = (key) => setPeriod(key);
    const onFromChange = (value) => { setPeriod(''); setFrom(value); };
    const onToChange = (value) => { setPeriod(''); setTo(value); };

    return (
        <>
            <div className="adm-export-bar">
                <label className="adm-export-field">
                    <span>С</span>
                    <input type="date" value={from} onChange={(e) => onFromChange(e.target.value)} className="adm-date-input" />
                </label>
                <label className="adm-export-field">
                    <span>По</span>
                    <input type="date" value={to} onChange={(e) => onToChange(e.target.value)} className="adm-date-input" />
                </label>
                <select value={status} onChange={(e) => setStatus(e.target.value)} className="adm-status-select">
                    <option value="paid">Успешные заказы</option>
                    <option value="all">Все заказы</option>
                    <option value="CANCELED">Только отменённые</option>
                    <option value="REFUSED">Только отказы от получения</option>
                </select>
                <button
                    type="button"
                    onClick={() => { window.location.href = `/admin/reports/revenue?${exportQuery()}&format=xlsx`; }}
                    className="adm-action-btn adm-btn-approve adm-btn-big"
                >
                    Excel с графиком
                </button>
                <button type="button" onClick={() => { window.location.href = `/admin/reports/revenue?${exportQuery()}`; }} className="adm-action-btn adm-btn-view adm-btn-big">
                    CSV
                </button>
                <button type="button" onClick={() => { window.location.href = `/admin/reports/revenue/pdf?${exportQuery()}`; }} className="adm-action-btn adm-btn-big">
                    PDF
                </button>
            </div>

            <div className={`adm-chart-card${chartLoading ? ' adm-chart-card--loading' : ''}`}>
                <div className="adm-chart-head">
                    <div className="adm-chart-title">
                        График выручки{chartMeta.range_label ? ` · ${chartMeta.range_label}` : ''}
                    </div>
                    <div className="adm-chart-period">
                        {CHART_PRESETS.map((p) => (
                            <button
                                key={p.key}
                                type="button"
                                className={`adm-filter-pill${period === p.key ? ' active' : ''}`}
                                onClick={() => applyPreset(p.key)}
                            >
                                {p.label}
                            </button>
                        ))}
                    </div>
                </div>
                <RevenueChart
                    data={chartData}
                    rangeLabel={chartMeta.range_label}
                    periodLabel={chartMeta.period_label}
                    granularity={chartMeta.granularity}
                />
            </div>
        </>
    );
}

export default function AdminDashboard({
    auth, stats, pendingSellers = [], users = [], usersMeta = {},
    orderSearch = '', orderResults = [],
    sessions = [], sessionsMeta = {}, loginHistory = [], loginHistoryMeta = {}, currentSessionId,
    revenueChart = [],
}) {
    const { staffAccess } = usePage().props;
    const panelTitle = staffAccess?.panelTitle ?? 'Панель администратора';
    const sidebarTitle = staffAccess?.isModerator ? 'Модерация' : 'Администрирование';

    const [message, setMessage] = useState(null);

    const [activeSection, setActiveSectionState] = useState(
        () => sessionStorage.getItem('admin_section') || 'overview'
    );
    const setActiveSection = (s) => {
        setActiveSectionState(s);
        sessionStorage.setItem('admin_section', s);
    };

    const [orderQuery, setOrderQuery] = useState(orderSearch);
    const [userQuery, setUserQuery] = useState('');
    const [pendingQuery, setPendingQuery] = useState('');
    const [sessionQuery, setSessionQuery] = useState('');
    const [sessionFilter, setSessionFilter] = useState('all'); // 'all' | 'online'
    const [userFilter, setUserFilter] = useState(usersMeta.filter ?? 'all');
    const [userSort, setUserSort] = useState(usersMeta.sort ?? 'created_at');
    const [userDir, setUserDir] = useState(usersMeta.dir ?? 'desc');

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const sectionParam = params.get('section');
        const filterParam = params.get('filter');

        if (sectionParam) {
            const section = sectionParam === 'pending-pvz' || sectionParam === 'pending-shop' ? 'users' : sectionParam;
            setActiveSection(section);
            sessionStorage.setItem('admin_section', section);
        } else {
            const saved = sessionStorage.getItem('admin_section');
            if (saved === 'pending-shop' || saved === 'pending-pvz') {
                setActiveSection('users');
                sessionStorage.setItem('admin_section', 'users');
            }
        }

        if (filterParam) {
            setUserFilter(filterParam);
        } else if (sectionParam === 'pending-pvz' || sessionStorage.getItem('admin_section') === 'pending-pvz') {
            setUserFilter('pvz_pending');
        } else if (sectionParam === 'pending-shop' || sessionStorage.getItem('admin_section') === 'pending-shop') {
            setUserFilter('shop_changes');
        }
    }, []);
    const [expandedOrder, setExpandedOrder] = useState(null);
    const [scannerOpen, setScannerOpen] = useState(false);

    const flash = (msg) => { setMessage(msg); setTimeout(() => setMessage(null), 4000); };

    const approveSeller = (userId) => {
        router.post(`/admin/sellers/${userId}/approve`, {}, {
            preserveScroll: true, preserveState: false,
            onSuccess: () => flash('Продавец одобрен'),
        });
    };
    const rejectSeller = (userId) => {
        if (!confirm('Отклонить заявку продавца?')) return;
        router.post(`/admin/sellers/${userId}/reject`, {}, {
            preserveScroll: true, preserveState: false,
            onSuccess: () => flash('Заявка отклонена'),
        });
    };
    const blockUser = (userId, isBlocked) => {
        router.put(`/admin/users/${userId}/block`, {}, {
            preserveScroll: true, preserveState: false,
            onSuccess: () => flash(isBlocked ? 'Разблокировано' : 'Заблокировано'),
        });
    };
    const kickSession = (sessionId) => {
        if (sessionId === currentSessionId) {
            flash('Нельзя завершить текущую сессию');
            return;
        }
        if (!confirm('Завершить сессию пользователя?')) return;
        router.delete(`/admin/sessions/${sessionId}`, {
            preserveScroll: true, preserveState: false,
            onSuccess: () => flash('Сессия завершена'),
        });
    };
    const submitOrderSearch = (e) => {
        e?.preventDefault();
        router.get('/admin/dashboard', { order_search: orderQuery }, {
            preserveScroll: true,
            preserveState: true,
            only: ['orderSearch', 'orderResults'],
        });
    };

    const runOrderSearch = useCallback((query) => {
        const trimmed = String(query ?? '').trim();
        setOrderQuery(trimmed);
        router.get('/admin/dashboard', { order_search: trimmed }, {
            preserveScroll: true,
            preserveState: true,
            only: ['orderSearch', 'orderResults'],
        });
    }, []);

    const handleBarcodeScan = useCallback((decoded) => {
        setScannerOpen(false);
        runOrderSearch(decoded);
    }, [runOrderSearch]);
    const updateOrderStatus = (orderId, status) => {
        router.post(`/admin/orders/${orderId}/status`, { status }, {
            preserveScroll: true, preserveState: false,
            onSuccess: () => flash('Статус заказа обновлён'),
        });
    };

    const onlineSessions = sessions.filter(s => s.is_online);
    const visibleSessions = sessions.filter(s => {
        if (sessionFilter === 'online' && !s.is_online) return false;
        if (!sessionQuery) return true;
        const q = sessionQuery.toLowerCase();
        return (s.user_name || '').toLowerCase().includes(q)
            || (s.user_email || '').toLowerCase().includes(q)
            || (s.ip_address || '').includes(sessionQuery)
            || String(s.user_id || '').includes(sessionQuery);
    });
    const visibleLoginHistory = loginHistory.filter(event => {
        if (!sessionQuery) return true;
        const q = sessionQuery.toLowerCase();
        return (event.user_name || '').toLowerCase().includes(q)
            || (event.user_last_name || '').toLowerCase().includes(q)
            || (event.user_email || '').toLowerCase().includes(q)
            || (event.ip_address || '').includes(sessionQuery)
            || (event.login_method || '').toLowerCase().includes(q)
            || String(event.user_id || '').includes(sessionQuery);
    });

    const visiblePending = pendingSellers.filter(u => {
        if (!pendingQuery) return true;
        const q = pendingQuery.toLowerCase();
        return (u.name || '').toLowerCase().includes(q)
            || (u.last_name || '').toLowerCase().includes(q)
            || (u.email || '').toLowerCase().includes(q)
            || (u.phone || '').includes(pendingQuery)
            || (u.seller_profile?.shop_name || '').toLowerCase().includes(q)
            || (u.seller_profile?.inn || '').includes(pendingQuery);
    });

    const shopChangesCount = stats.pending_shop_changes ?? 0;
    const pvzPendingCount = stats.pvz_pending_applications ?? 0;

    const reloadUsers = (overrides = {}) => {
        router.get('/admin/dashboard', {
            users_page: overrides.page ?? 1,
            users_filter: overrides.filter ?? userFilter,
            users_search: overrides.search ?? userQuery.trim(),
            users_sort: overrides.sort ?? userSort,
            users_dir: overrides.dir ?? userDir,
            section: 'users',
        }, {
            preserveScroll: true,
            preserveState: true,
            only: ['users', 'usersMeta', 'stats'],
        });
    };

    const applyUserFilter = (filter) => {
        setUserFilter(filter);
        reloadUsers({ page: 1, filter });
    };

    const applyUserSort = (sort, dir) => {
        setUserSort(sort);
        setUserDir(dir);
        reloadUsers({ page: 1, sort, dir });
    };

    const loadMoreUsers = () => {
        reloadUsers({ page: (usersMeta.page ?? 1) + 1 });
    };

    const submitUserSearch = (e) => {
        e?.preventDefault();
        reloadUsers({ page: 1, search: userQuery.trim() });
    };

    const exportAllUsers = (format = 'xlsx') => {
        const params = new URLSearchParams({
            users_filter: userFilter,
            users_search: userQuery.trim(),
            users_sort: userSort,
            users_dir: userDir,
            format,
        });
        window.location.href = `/admin/reports/users?${params}`;
    };

    const loadMoreSessions = () => {
        router.get('/admin/dashboard', {
            sessions_page: (sessionsMeta.page ?? 1) + 1,
            section: 'sessions',
        }, { preserveScroll: true, only: ['sessions', 'sessionsMeta'] });
    };

    const loadMoreLoginHistory = () => {
        router.get('/admin/dashboard', {
            login_page: (loginHistoryMeta.page ?? 1) + 1,
            section: 'sessions',
        }, { preserveScroll: true, only: ['loginHistory', 'loginHistoryMeta'] });
    };

    const visibleUsers = users;

    const sections = [
        { key: 'overview', label: 'Обзор' },
        { key: 'sessions', label: `Сессии${onlineSessions.length > 0 ? ` · ${onlineSessions.length} онлайн` : ''}` },
        { key: 'pending',  label: `Заявки продавцов${stats.pending_approvals > 0 ? ` (${stats.pending_approvals})` : ''}` },
        { key: 'users',    label: 'Пользователи' },
        { key: 'orders',   label: 'Поиск заказов' },
        { key: 'extra',    label: 'Дополнительно' },
    ];

    const extraLinks = [
        {
            title: 'Все товары',
            description: 'Каталог товаров, модерация, статусы и управление витриной.',
            href: '/admin/products',
        },
        {
            title: 'Пункты выдачи',
            description: 'Список ПВЗ, операторы, закрытие пунктов и назначение сотрудников.',
            href: '/admin/pickup-points',
        },
        {
            title: 'Слайдер главной',
            description: 'Баннеры и промо-слайды на главной странице.',
            href: '/admin/home-slides',
        },
        {
            title: 'Отзывы',
            description: 'Модерация отзывов покупателей по товарам.',
            href: '/admin/reviews',
        },
        {
            title: 'Поддержка (чаты)',
            description: 'Обращения пользователей и переписки поддержки.',
            href: '/admin/support',
        },
    ];

    // Live update of "X min ago" labels every 30s
    const [, force] = useState(0);
    useEffect(() => {
        const t = setInterval(() => force(x => x + 1), 30000);
        return () => clearInterval(t);
    }, []);

    return (
        <MainLayout auth={auth}>
            <Head title={panelTitle} />

            {message && (
                <div className="adm-flash" onClick={() => setMessage(null)}>{message}</div>
            )}

            {!staffAccess?.isPrimaryAdmin && (
                <div className="adm-mobile-unavailable">
                    <h2>{panelTitle} доступна с компьютера</h2>
                    <p>Для управления пользователями, заказами и отчётами нужен широкий экран. Откройте панель на ноутбуке или ПК.</p>
                </div>
            )}

            <div className={`adm-layout${staffAccess?.isPrimaryAdmin ? ' adm-layout--primary-access' : ''}`}>
                {/* Sidebar */}
                <aside className="adm-sidebar">
                    <div className="adm-sidebar-title">{sidebarTitle}</div>
                    <nav className="adm-nav">
                        {sections.map(s => (
                            <button
                                key={s.key}
                                className={`adm-nav-item ${activeSection === s.key ? 'active' : ''}`}
                                onClick={() => setActiveSection(s.key)}
                            >
                                {s.label}
                            </button>
                        ))}
                       
                        <a href="/profile" className="adm-nav-item adm-nav-back">← Профиль</a>
                    </nav>
                </aside>

                <main className="adm-main">

                    {/* ── OVERVIEW ── */}
                    {activeSection === 'overview' && (
                        <div>
                            <div className="adm-overview-head">
                                <h1 className="adm-title">{panelTitle}</h1>
                            </div>

                            <OverviewRevenueSection initialChart={revenueChart} />

                            <div className="adm-stats-row">
                                <div className="adm-stat-card adm-stat-success" onClick={() => setActiveSection('sessions')} style={{ cursor: 'pointer' }}>
                                    <div className="adm-stat-value">{stats.online_count || 0}</div>
                                    <div className="adm-stat-label">Онлайн сейчас</div>
                                </div>
                                <div className="adm-stat-card">
                                    <div className="adm-stat-value">{stats.total_users}</div>
                                    <div className="adm-stat-label">Пользователей</div>
                                </div>
                                <div className="adm-stat-card">
                                    <div className="adm-stat-value">{stats.total_sellers}</div>
                                    <div className="adm-stat-label">Продавцов</div>
                                </div>
                                <div className="adm-stat-card adm-stat-danger">
                                    <div className="adm-stat-value">{stats.blocked_users}</div>
                                    <div className="adm-stat-label">Заблокировано</div>
                                </div>
                                <div className="adm-stat-card">
                                    <div className="adm-stat-value">{stats.total_orders}</div>
                                    <div className="adm-stat-label">Заказов всего</div>
                                </div>
                                <div className="adm-stat-card">
                                    <div className="adm-stat-value">{stats.orders_today}</div>
                                    <div className="adm-stat-label">Заказов сегодня</div>
                                </div>
                                <div className="adm-stat-card adm-stat-money">
                                    <div className="adm-stat-value">
                                        {Number(stats.revenue_total).toLocaleString('ru-RU')} ₽
                                    </div>
                                    <div className="adm-stat-label">Оборот заказов</div>
                                </div>
                                <div className="adm-stat-card adm-stat-money">
                                    <div className="adm-stat-value">
                                        {Number(stats.platform_commission_total || 0).toLocaleString('ru-RU')} ₽
                                    </div>
                                    <div className="adm-stat-label">Комиссия платформы</div>
                                </div>
                                {stats.pending_approvals > 0 && (
                                    <div className="adm-stat-card adm-stat-warn" onClick={() => setActiveSection('pending')} style={{ cursor: 'pointer' }}>
                                        <div className="adm-stat-value">{stats.pending_approvals}</div>
                                        <div className="adm-stat-label">На одобрении</div>
                                    </div>
                                )}
                            </div>

                            <div className="adm-shortcuts">
                                <button className="adm-shortcut-btn" onClick={() => setActiveSection('sessions')}>
                                    Активные сессии ({onlineSessions.length})
                                </button>
                                <button className="adm-shortcut-btn" onClick={() => setActiveSection('pending')}>
                                    Заявки ({stats.pending_approvals})
                                </button>
                                <button className="adm-shortcut-btn" onClick={() => setActiveSection('users')}>
                                    Пользователи
                                </button>
                                <button className="adm-shortcut-btn" onClick={() => setActiveSection('orders')}>
                                    Поиск заказов
                                </button>
                                <button className="adm-shortcut-btn" onClick={() => setActiveSection('extra')}>
                                    Дополнительно
                                </button>
                            </div>
                        </div>
                    )}

                    {/* ── EXTRA LINKS ── */}
                    {activeSection === 'extra' && (
                        <div>
                            <h1 className="adm-title">Дополнительно</h1>
                            <div className="adm-extra-grid">
                                {extraLinks.map((link) => (
                                    <button
                                        key={link.href}
                                        type="button"
                                        className="adm-extra-card"
                                        onClick={() => { window.location.href = link.href; }}
                                    >
                                        <span className="adm-extra-card-title">{link.title}</span>
                                        <span className="adm-extra-card-text">{link.description}</span>
                                        <span className="adm-extra-card-action">Открыть →</span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* ── SESSIONS ── */}
                    {activeSection === 'sessions' && (
                        <div>
                            <h1 className="adm-title">Сессии · {onlineSessions.length} онлайн</h1>

                            <div className="adm-filter-row">
                                <button
                                    className={`adm-filter-pill ${sessionFilter === 'all' ? 'active' : ''}`}
                                    onClick={() => setSessionFilter('all')}
                                >Все ({sessions.length})</button>
                                <button
                                    className={`adm-filter-pill ${sessionFilter === 'online' ? 'active' : ''}`}
                                    onClick={() => setSessionFilter('online')}
                                >Онлайн ({onlineSessions.length})</button>
                                <input
                                    type="text"
                                    className="admin-search-input adm-flex-search"
                                    placeholder="Поиск по имени, email, IP, ID..."
                                    value={sessionQuery}
                                    onChange={(e) => setSessionQuery(e.target.value)}
                                />
                            </div>

                            <div className="adm-table-wrap adm-table-wrap--sessions">
                                <table className="adm-table">
                                    <thead>
                                        <tr>
                                            <th>Пользователь</th>
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
                                                    <td>
                                                        {s.user_id ? (
                                                            <div className="adm-cell-user">
                                                                <img src={s.user_avatar || '/img/profiles/profile.png'} className="adm-avatar-sm" alt="" />
                                                                <div>
                                                                    <div className="adm-user-name">
                                                                        {[s.user_name, s.user_last_name].filter(Boolean).join(' ') || 'Без имени'}
                                                                        {isCurrent && <span className="adm-current-badge"> (вы)</span>}
                                                                    </div>
                                                                    <div className="adm-user-sub">{s.user_email || `ID: ${s.user_id}`}</div>
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <span className="adm-anon-user">Гость</span>
                                                        )}
                                                    </td>
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
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div className="adm-cell-actions">
                                                            {s.user_id && (
                                                                <a href={`/admin/users/${s.user_id}/detail`} className="adm-action-btn adm-btn-view">
                                                                    Профиль
                                                                </a>
                                                            )}
                                                            {!isCurrent && (
                                                                <button
                                                                    className="adm-action-btn adm-btn-reject"
                                                                    onClick={() => kickSession(s.id)}
                                                                >
                                                                    Завершить
                                                                </button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                                {visibleSessions.length === 0 && (
                                    <div className="adm-empty">Сессии не найдены</div>
                                )}
                            </div>
                            {sessionsMeta.has_more && (
                                <div style={{ marginTop: 12, textAlign: 'center' }}>
                                    <button type="button" className="adm-action-btn adm-btn-view" onClick={loadMoreSessions}>
                                        Показать ещё сессии
                                    </button>
                                </div>
                            )}

                            <div className="adm-section-subhead">
                                <h2>История входов</h2>
                                <span>{visibleLoginHistory.length} записей</span>
                            </div>
                            <div className="adm-table-wrap adm-table-wrap--login-history">
                                <table className="adm-table">
                                    <thead>
                                        <tr>
                                            <th>Пользователь</th>
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
                                                        {event.user_id ? (
                                                            <div className="adm-cell-user">
                                                                <img src={event.user_avatar || '/img/profiles/profile.png'} className="adm-avatar-sm" alt="" />
                                                                <div>
                                                                    <div className="adm-user-name">
                                                                        {[event.user_name, event.user_last_name].filter(Boolean).join(' ') || 'Без имени'}
                                                                    </div>
                                                                    <div className="adm-user-sub">{event.user_email || `ID: ${event.user_id}`}</div>
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <span className="adm-anon-user">Удалённый пользователь</span>
                                                        )}
                                                    </td>
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
                                    <div className="adm-empty">История входов не найдена</div>
                                )}
                            </div>
                            {loginHistoryMeta.has_more && (
                                <div style={{ marginTop: 12, textAlign: 'center' }}>
                                    <button type="button" className="adm-action-btn adm-btn-view" onClick={loadMoreLoginHistory}>
                                        Показать ещё историю входов
                                    </button>
                                </div>
                            )}
                        </div>
                    )}

                    {/* ── PENDING SELLERS ── */}
                    {activeSection === 'pending' && (
                        <div>
                            <h1 className="adm-title">Заявки продавцов ({pendingSellers.length})</h1>

                            <div className="adm-search-bar adm-mb">
                                <input
                                    type="text"
                                    className="admin-search-input"
                                    placeholder="Поиск по магазину, ИНН, имени, email..."
                                    value={pendingQuery}
                                    onChange={(e) => setPendingQuery(e.target.value)}
                                />
                                <span className="admin-users-count">{visiblePending.length} из {pendingSellers.length}</span>
                            </div>

                            {visiblePending.length === 0 ? (
                                <div className="adm-empty">Заявок нет</div>
                            ) : (
                                <div className="adm-pending-list">
                                    {visiblePending.map(u => (
                                        <div key={u.id} className={`adm-pending-card ${u.application_type === 'restore' ? 'adm-pending-card--restore' : ''}`}>
                                            <div className="adm-pending-user">
                                                <img src={u.avatar || '/img/profiles/profile.png'} className="adm-avatar" alt="" />
                                                <div>
                                                    <div className="adm-user-name">
                                                        {[u.name, u.last_name].filter(Boolean).join(' ') || 'Без имени'}
                                                    </div>
                                                    <div className="adm-user-sub">{u.email}</div>
                                                    <div className="adm-user-sub">{formatPhone(u.phone)}</div>
                                                    <div className="adm-user-sub">
                                                        Зарегистрирован: {new Date(u.created_at).toLocaleDateString('ru-RU')}
                                                    </div>
                                                </div>
                                            </div>
                                            {u.seller_profile && (
                                                <div className="adm-pending-shop">
                                                    <div className="adm-shop-name">
                                                        {u.seller_profile.shop_name}
                                                        {u.application_type === 'restore' && (
                                                            <span className="adm-badge adm-badge-restore">Восстановление компании</span>
                                                        )}
                                                    </div>
                                                    {u.application_type === 'restore' && u.restore_requested_at && (
                                                        <div className="adm-shop-meta">
                                                            Запрошено: {new Date(u.restore_requested_at).toLocaleString('ru-RU')}
                                                        </div>
                                                    )}
                                                    {u.seller_profile.inn && <div className="adm-shop-meta">ИНН: {u.seller_profile.inn}</div>}
                                                    {u.seller_profile.pickup_address && (
                                                        <div className="adm-shop-meta">Адрес: {u.seller_profile.pickup_address}</div>
                                                    )}
                                                    {u.seller_profile.legal_address && (
                                                        <div className="adm-shop-meta">Юр. адрес: {u.seller_profile.legal_address}</div>
                                                    )}
                                                    {u.seller_profile.description && (
                                                        <div className="adm-shop-desc">{u.seller_profile.description}</div>
                                                    )}
                                                </div>
                                            )}
                                            <div className="adm-pending-actions">
                                                <a href={`/admin/users/${u.id}/detail`} className="adm-action-btn adm-btn-view">Подробнее</a>
                                                <button className="adm-action-btn adm-btn-approve" onClick={() => approveSeller(u.id)}>
                                                    {u.application_type === 'restore' ? 'Одобрить восстановление' : 'Одобрить'}
                                                </button>
                                                <button className="adm-action-btn adm-btn-reject" onClick={() => rejectSeller(u.id)}>Отклонить</button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* ── USERS ── */}
                    {activeSection === 'users' && (
                        <div>
                            <h1 className="adm-title">Пользователи ({usersMeta.total ?? users.length})</h1>

                            <div className="adm-filter-row" style={{ marginBottom: 10 }}>
                                <select
                                    className="adm-status-select"
                                    value={`${userSort}:${userDir}`}
                                    onChange={(e) => {
                                        const [sort, dir] = e.target.value.split(':');
                                        applyUserSort(sort, dir);
                                    }}
                                >
                                    <option value="created_at:desc">Дата регистрации ↓</option>
                                    <option value="created_at:asc">Дата регистрации ↑</option>
                                    <option value="name:asc">Имя А–Я</option>
                                    <option value="name:desc">Имя Я–А</option>
                                    <option value="role:asc">Роль: админ → пользователь</option>
                                    <option value="role:desc">Роль: пользователь → админ</option>
                                </select>
                                <button type="button" className="adm-action-btn adm-btn-approve" onClick={() => exportAllUsers('xlsx')}>
                                    Excel
                                </button>
                                <button type="button" className="adm-action-btn adm-btn-view" onClick={() => exportAllUsers('csv')}>
                                    CSV
                                </button>
                            </div>

                            <div className="adm-filter-row">
                                {[
                                    { k: 'all', l: 'Все' },
                                    { k: 'active', l: 'Активные' },
                                    { k: 'shop_changes', l: `Изменения магазина (${shopChangesCount})` },
                                    { k: 'pvz_pending', l: `Заявки ПВЗ (${pvzPendingCount})` },
                                    { k: 'blocked', l: 'Заблокированные' },
                                    { k: 'deleted', l: 'Удалённые' },
                                ].map(t => (
                                    <button
                                        key={t.k}
                                        type="button"
                                        className={`adm-filter-pill ${userFilter === t.k ? 'active' : ''}`}
                                        onClick={() => applyUserFilter(t.k)}
                                    >{t.l}</button>
                                ))}
                                <form onSubmit={submitUserSearch} style={{ display: 'flex', gap: 8, flex: 1 }}>
                                    <input
                                        type="text"
                                        className="admin-search-input adm-flex-search"
                                        placeholder="Поиск по имени, email, телефону, ID..."
                                        value={userQuery}
                                        onChange={(e) => setUserQuery(e.target.value)}
                                    />
                                    <button type="submit" className="adm-action-btn adm-btn-view">Найти</button>
                                </form>
                            </div>

                            <div className="adm-table-wrap">
                                <table className="adm-table">
                                    <thead>
                                        <tr>
                                            <th>Пользователь</th>
                                            <th>Контакт</th>
                                            <th>Роль</th>
                                            <th>Статус</th>
                                            <th>Дата</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visibleUsers.map(u => (
                                            <tr key={u.id} className={`${u.is_blocked ? 'adm-row-blocked' : ''} ${(u.shop_changes_pending || u.pvz_application_pending) ? 'adm-row-highlight' : ''}`}>
                                                <td>
                                                    <div className="adm-cell-user">
                                                        <img src={u.avatar || '/img/profiles/profile.png'} className="adm-avatar-sm" alt="" />
                                                        <span>{[u.name, u.last_name].filter(Boolean).join(' ') || 'Без имени'}</span>
                                                        {u.shop_changes_pending && (
                                                            <span className="adm-user-flag adm-user-flag--shop">Изменения магазина</span>
                                                        )}
                                                        {u.pvz_application_pending && (
                                                            <span className="adm-user-flag adm-user-flag--pvz">Заявка ПВЗ</span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div className="adm-cell-contact">
                                                        {u.email && <span>{u.email}</span>}
                                                        {u.phone && <span>{formatPhone(u.phone)}</span>}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span className={`adm-role-badge role-${u.role}`}>
                                                        {ROLE_LABELS[u.role] || u.role}
                                                    </span>
                                                </td>
                                                <td>
                                                    {u.deleted_at
                                                        ? <span className="adm-status-blocked">Удалён</span>
                                                        : u.is_blocked
                                                            ? <span className="adm-status-blocked">Заблокирован</span>
                                                            : <span className="adm-status-active">Активен</span>
                                                    }
                                                </td>
                                                <td>{new Date(u.created_at).toLocaleDateString('ru-RU')}</td>
                                                <td>
                                                    <div className="adm-cell-actions">
                                                        <a href={`/admin/users/${u.id}/detail`} className="adm-action-btn adm-btn-view">
                                                            Детали
                                                        </a>
                                                        {u.id !== auth?.user?.id && u.id !== 1 && !u.deleted_at && (
                                                            <button
                                                                className={`adm-action-btn ${u.is_blocked ? 'adm-btn-unblock' : 'adm-btn-block'}`}
                                                                onClick={() => blockUser(u.id, u.is_blocked)}
                                                            >
                                                                {u.is_blocked ? 'Разбл.' : 'Блок.'}
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {visibleUsers.length === 0 && (
                                    <div className="adm-empty">Пользователи не найдены</div>
                                )}
                            </div>
                            {usersMeta.has_more && (
                                <div style={{ marginTop: 16, textAlign: 'center' }}>
                                    <button type="button" className="adm-action-btn adm-btn-view" onClick={loadMoreUsers}>
                                        Показать ещё ({visibleUsers.length} из {usersMeta.total})
                                    </button>
                                </div>
                            )}
                        </div>
                    )}

                    {/* ── ORDER SEARCH ── */}
                    {activeSection === 'orders' && (
                        <div>
                            <h1 className="adm-title">Поиск заказов</h1>

                            <form onSubmit={submitOrderSearch} className="adm-search-bar adm-mb">
                                <input
                                    type="text"
                                    className="admin-search-input"
                                    placeholder="Точный номер 123..., код выдачи (10 символов), суточный код 1234 5678, ID или email"
                                    value={orderQuery}
                                    onChange={(e) => setOrderQuery(e.target.value)}
                                />
                                <button
                                    type="button"
                                    className="adm-action-btn adm-btn-scan"
                                    title="Сканировать QR или штрихкод"
                                    onClick={() => setScannerOpen(true)}
                                >
                                    📷 Сканировать
                                </button>
                                <button type="submit" className="adm-action-btn adm-btn-view">Найти</button>
                                {orderSearch && (
                                    <button
                                        type="button"
                                        className="adm-action-btn"
                                        onClick={() => { setOrderQuery(''); router.get('/admin/dashboard', {}, { preserveState: true, preserveScroll: true, only: ['orderSearch', 'orderResults'] }); }}
                                    >Очистить</button>
                                )}
                            </form>

                            {!orderSearch && orderResults.length === 0 && (
                                <div className="adm-empty">
                                    Введите значение целиком — поиск только по точному совпадению (не по части кода).<br />
                                    Номер заказа <code className="adm-code">234...</code>, код выдачи (10 символов),
                                    суточный код <code className="adm-code">1234 5678</code>, ID покупателя или email.
                                    Можно отсканировать QR / штрихкод.
                                </div>
                            )}

                            {orderSearch && orderResults.length === 0 && (
                                <div className="adm-empty">Заказы по запросу «{orderSearch}» не найдены</div>
                            )}

                            {orderResults.length > 0 && (
                                <div className="adm-orders-list" style={{ padding: 0 }}>
                                    <div className="adm-result-count">Найдено: {orderResults.length}</div>
                                    {orderResults.map(o => (
                                        <div key={o.id} className="adm-order-row">
                                            <div
                                                className="adm-order-header"
                                                onClick={() => setExpandedOrder(expandedOrder === o.id ? null : o.id)}
                                            >
                                                <div className="adm-order-header-left">
                                                    <strong>#{o.number}</strong>
                                                    {o.order_code && <code className="adm-code">{o.order_code}</code>}
                                                    <span className={`adm-order-status status-${o.status}`}>
                                                        {ORDER_STATUS_MAP[o.status] || o.status}
                                                    </span>
                                                    {o.payment_status && (
                                                        <span className="adm-pay-status">💳 {PAYMENT_STATUS_MAP[o.payment_status]}</span>
                                                    )}
                                                    {o.buyer && (
                                                        <a
                                                            href={`/admin/users/${o.buyer.id}/detail`}
                                                            onClick={(e) => e.stopPropagation()}
                                                            className="adm-buyer-chip"
                                                        >
                                                            👤 {o.buyer.name || o.buyer.email || `ID:${o.buyer.id}`}
                                                        </a>
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
                                                    {/* Buyer card */}
                                                    {o.buyer ? (
                                                        <div className="adm-order-buyer-card">
                                                            <img
                                                                src={o.buyer.avatar || '/img/profiles/profile.png'}
                                                                className="adm-avatar"
                                                                alt="avatar"
                                                            />
                                                            <div className="adm-order-buyer-info">
                                                                <div className="adm-order-buyer-head">
                                                                    <span className="adm-order-buyer-name">
                                                                        {[o.buyer.name, o.buyer.last_name].filter(Boolean).join(' ') || 'Без имени'}
                                                                    </span>
                                                                    {o.buyer.role && (
                                                                        <span className={`adm-role-badge role-${o.buyer.role}`}>
                                                                            {ROLE_LABELS[o.buyer.role] || o.buyer.role}
                                                                        </span>
                                                                    )}
                                                                    {o.buyer.is_blocked && (
                                                                        <span className="adm-status-blocked">Заблокирован</span>
                                                                    )}
                                                                    {o.buyer.deleted_at && (
                                                                        <span className="adm-status-blocked">Удалён</span>
                                                                    )}
                                                                </div>
                                                                <div className="adm-order-buyer-contacts">
                                                                    <span>ID: {o.buyer.id}</span>
                                                                    {o.buyer.email && <span>{o.buyer.email}</span>}
                                                                    {o.buyer.phone && <span>{formatPhone(o.buyer.phone)}</span>}
                                                                </div>
                                                            </div>
                                                            <a
                                                                href={`/admin/users/${o.buyer.id}/detail`}
                                                                className="adm-action-btn adm-btn-view adm-btn-big"
                                                            >
                                                                Открыть профиль →
                                                            </a>
                                                        </div>
                                                    ) : (
                                                        <div className="adm-order-buyer-card adm-order-buyer-missing">
                                                            <span>⚠ Покупатель не найден (возможно, аккаунт полностью удалён)</span>
                                                        </div>
                                                    )}

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

                                                    <div className="adm-order-meta-grid">
                                                        {o.delivery_address && <div><span className="adm-label">Адрес доставки</span>{o.delivery_address}</div>}
                                                        {o.delivery_method && <div><span className="adm-label">Способ доставки</span>{o.delivery_method}</div>}
                                                        {o.discount > 0 && <div><span className="adm-label">Скидка</span>{Number(o.discount).toLocaleString('ru-RU')} ₽</div>}
                                                        {o.comment && <div className="adm-order-comment"><span className="adm-label">Комментарий</span>{o.comment}</div>}
                                                    </div>

                                                    <div className="adm-order-status-change">
                                                        <span className="adm-label">Изменить статус:</span>
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
                                                                title="Скачать чек заказа"
                                                            >
                                                                🧾 Чек
                                                            </a>
                                                            <a
                                                                href={route('admin.reports.commission', o.id)}
                                                                className="adm-action-btn adm-btn-view"
                                                                title="Отчёт по комиссии и выплатам"
                                                            >
                                                                % Комиссия
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

                                                    <div className="adm-order-timeline">
                                                        {['NEW','INTRANSIT','DELIVERED','ISSUED'].map((s, i, arr) => {
                                                            const statusOrder = arr.indexOf(o.status);
                                                            const isDone    = i <= statusOrder && !['CANCELED','REFUSED'].includes(o.status);
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
                                </div>
                            )}
                        </div>
                    )}

                </main>
            </div>

            <BarcodeScannerModal
                open={scannerOpen}
                onClose={() => setScannerOpen(false)}
                onScan={handleBarcodeScan}
                title="Сканирование кода заказа"
            />
        </MainLayout>
    );
}
