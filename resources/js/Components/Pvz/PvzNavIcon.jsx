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
    queue: (cls) => (
        <Svg className={cls}>
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
            <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
            <line x1="12" y1="22.08" x2="12" y2="12" />
        </Svg>
    ),
    search: (cls) => (
        <Svg className={cls}>
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.35-4.35" />
        </Svg>
    ),
    reports: (cls) => (
        <Svg className={cls}>
            <line x1="18" y1="20" x2="18" y2="10" />
            <line x1="12" y1="20" x2="12" y2="4" />
            <line x1="6" y1="20" x2="6" y2="14" />
        </Svg>
    ),
    settings: (cls) => (
        <Svg className={cls}>
            <circle cx="12" cy="12" r="3" />
            <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
        </Svg>
    ),
    site: (cls) => (
        <Svg className={cls}>
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
            <polyline points="15 3 21 3 21 9" />
            <line x1="10" y1="14" x2="21" y2="3" />
        </Svg>
    ),
};

export default function PvzNavIcon({ name, className = 'pvz-nav-icon-svg' }) {
    const render = ICONS[name] ?? ICONS.dashboard;
    return <span className="nav-icon">{render(className)}</span>;
}
