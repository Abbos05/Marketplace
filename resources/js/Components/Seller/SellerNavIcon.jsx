import React from 'react';

function Svg({ children, className }) {
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.75"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden
        >
            {children}
        </svg>
    );
}

const ICONS = {
    dashboard: (cls) => (
        <Svg className={cls}>
            <path d="M3 10.5L12 3l9 7.5V21a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-10.5z" />
            <path d="M9 22V12h6v10" />
        </Svg>
    ),
    products: (cls) => (
        <Svg className={cls}>
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
            <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
            <line x1="12" y1="22.08" x2="12" y2="12" />
        </Svg>
    ),
    create: (cls) => (
        <Svg className={cls}>
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <path d="M12 8v8M8 12h8" />
        </Svg>
    ),
    orders: (cls) => (
        <Svg className={cls}>
            <path d="M6 2h12l2 6H4l2-6z" />
            <path d="M4 8h16v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8z" />
            <path d="M9 12h6" />
        </Svg>
    ),
    statistics: (cls) => (
        <Svg className={cls}>
            <line x1="18" y1="20" x2="18" y2="10" />
            <line x1="12" y1="20" x2="12" y2="4" />
            <line x1="6" y1="20" x2="6" y2="14" />
        </Svg>
    ),
    promocodes: (cls) => (
        <Svg className={cls}>
            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
            <line x1="7" y1="7" x2="7.01" y2="7" />
        </Svg>
    ),
    settings: (cls) => (
        <Svg className={cls}>
            <circle cx="12" cy="12" r="3" />
            <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
        </Svg>
    ),
};

export default function SellerNavIcon({ name, className = 'seller-nav-icon-svg' }) {
    const render = ICONS[name] ?? ICONS.dashboard;
    return <span className="nav-icon">{render(className)}</span>;
}
