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

function iconPhone(cls) {
    return (
        <Svg className={cls}>
            <rect x="5" y="2" width="14" height="20" rx="2" />
            <line x1="12" y1="18" x2="12.01" y2="18" />
        </Svg>
    );
}

function iconLaptop(cls) {
    return (
        <Svg className={cls}>
            <rect x="3" y="4" width="18" height="12" rx="2" />
            <path d="M2 20h20" />
        </Svg>
    );
}

function iconTv(cls) {
    return (
        <Svg className={cls}>
            <rect x="2" y="5" width="20" height="14" rx="2" />
            <line x1="8" y1="21" x2="16" y2="21" />
            <line x1="12" y1="19" x2="12" y2="21" />
        </Svg>
    );
}

function iconBox(cls) {
    return (
        <Svg className={cls}>
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
            <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
            <line x1="12" y1="22.08" x2="12" y2="12" />
        </Svg>
    );
}

const ICONS = {
    default: iconBox,
    electronics: iconPhone,
    smartphones: iconPhone,
    laptops: iconLaptop,
    fridges: (cls) => (
        <Svg className={cls}>
            <rect x="6" y="2" width="12" height="20" rx="1" />
            <line x1="6" y1="10" x2="18" y2="10" />
        </Svg>
    ),
    tvs: iconTv,
    'electronics-s1': iconPhone,
    'electronics-s2': iconLaptop,
    'electronics-s3': iconTv,
    'electronics-s4': (cls) => (
        <Svg className={cls}>
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4" />
        </Svg>
    ),
    clothing: (cls) => (
        <Svg className={cls}>
            <path d="M6 3l6 3 6-3v4l-6 3-6-3V3z" />
            <path d="M6 7v14h12V7" />
        </Svg>
    ),
    clothes: (cls) => ICONS.clothing(cls),
    'mens-clothes': (cls) => ICONS.clothing(cls),
    'womens-clothes': (cls) => (
        <Svg className={cls}>
            <path d="M8 3h8l2 4H6l2-4z" />
            <path d="M7 7v14h10V7" />
        </Svg>
    ),
    'clothing-s1': (cls) => (
        <Svg className={cls}>
            <path d="M8 4l4-2 4 2v3l-4 2-4-2V4z" />
            <path d="M6 7v13h12V7" />
        </Svg>
    ),
    'clothing-s2': (cls) => ICONS.clothing(cls),
    'clothing-s3': (cls) => (
        <Svg className={cls}>
            <ellipse cx="12" cy="18" rx="5" ry="2" />
            <path d="M7 6h10v8a5 5 0 0 1-10 0V6z" />
        </Svg>
    ),
    'clothing-s4': (cls) => (
        <Svg className={cls}>
            <circle cx="12" cy="8" r="3" />
            <path d="M6 20v-4a6 6 0 0 1 12 0v4" />
        </Svg>
    ),
    home: (cls) => (
        <Svg className={cls}>
            <path d="M3 10.5L12 3l9 7.5V21a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-10.5z" />
            <path d="M9 22V12h6v10" />
        </Svg>
    ),
    'home-s1': (cls) => (
        <Svg className={cls}>
            <rect x="4" y="8" width="16" height="12" rx="1" />
            <path d="M4 12h16M12 8V4" />
        </Svg>
    ),
    'home-s2': (cls) => (
        <Svg className={cls}>
            <path d="M4 6h16v12H4z" />
            <path d="M4 10h16" />
        </Svg>
    ),
    'home-s3': (cls) => (
        <Svg className={cls}>
            <circle cx="12" cy="8" r="3" />
            <path d="M6 20h12l-2-8H8l-2 8z" />
        </Svg>
    ),
    'home-s4': (cls) => (
        <Svg className={cls}>
            <rect x="5" y="4" width="14" height="16" rx="1" />
            <path d="M9 4V2h6v2" />
        </Svg>
    ),
    sports: (cls) => (
        <Svg className={cls}>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 3a15 15 0 0 1 0 18M3 12h18" />
        </Svg>
    ),
    'sports-s1': (cls) => (
        <Svg className={cls}>
            <path d="M6 20V10l6-6 6 6v10" />
            <line x1="9" y1="14" x2="15" y2="14" />
        </Svg>
    ),
    'sports-s2': (cls) => (
        <Svg className={cls}>
            <path d="M4 20L12 4l8 16" />
        </Svg>
    ),
    'sports-s3': (cls) => ICONS.sports(cls),
    'sports-s4': (cls) => (
        <Svg className={cls}>
            <rect x="3" y="8" width="18" height="10" rx="2" />
            <line x1="12" y1="8" x2="12" y2="4" />
        </Svg>
    ),
    auto: (cls) => (
        <Svg className={cls}>
            <path d="M5 17h14l-1.5-5H6.5L5 17z" />
            <circle cx="7.5" cy="17.5" r="1.5" />
            <circle cx="16.5" cy="17.5" r="1.5" />
            <path d="M5 12h14V9l-2-3H7L5 9v3z" />
        </Svg>
    ),
    'auto-s1': (cls) => (
        <Svg className={cls}>
            <circle cx="12" cy="12" r="8" />
            <circle cx="12" cy="12" r="3" />
        </Svg>
    ),
    'auto-s2': (cls) => (
        <Svg className={cls}>
            <path d="M8 4v16M16 4v16M8 12h8" />
        </Svg>
    ),
    'auto-s3': (cls) => (
        <Svg className={cls}>
            <rect x="6" y="6" width="12" height="12" rx="1" />
            <line x1="10" y1="10" x2="14" y2="10" />
        </Svg>
    ),
    'auto-s4': (cls) => ICONS.auto(cls),
    books: (cls) => (
        <Svg className={cls}>
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
        </Svg>
    ),
    'books-s1': (cls) => ICONS.books(cls),
    'books-s2': (cls) => (
        <Svg className={cls}>
            <path d="M4 6h16v12H4z" />
            <line x1="8" y1="10" x2="16" y2="10" />
        </Svg>
    ),
    'books-s3': (cls) => (
        <Svg className={cls}>
            <rect x="5" y="3" width="14" height="18" rx="1" />
            <line x1="9" y1="8" x2="15" y2="8" />
        </Svg>
    ),
    'books-s4': (cls) => (
        <Svg className={cls}>
            <circle cx="12" cy="12" r="8" />
            <path d="M9 12h6M12 9v6" />
        </Svg>
    ),
    beauty: (cls) => (
        <Svg className={cls}>
            <path d="M12 2a4 4 0 0 1 4 6v2H8V8a4 4 0 0 1 4-6z" />
            <path d="M8 10h8v12H8z" />
        </Svg>
    ),
    'beauty-s1': (cls) => ICONS.beauty(cls),
    'beauty-s2': (cls) => (
        <Svg className={cls}>
            <path d="M9 4h6l1 8H8l1-8z" />
            <path d="M10 12v8M14 12v8" />
        </Svg>
    ),
    'beauty-s3': (cls) => (
        <Svg className={cls}>
            <path d="M8 4c0 4 2 6 4 6s4-2 4-6" />
            <path d="M6 20h12l-2-10H8l-2 10z" />
        </Svg>
    ),
    'beauty-s4': (cls) => (
        <Svg className={cls}>
            <rect x="5" y="6" width="14" height="12" rx="2" />
            <line x1="9" y1="10" x2="15" y2="10" />
        </Svg>
    ),
    kids: (cls) => (
        <Svg className={cls}>
            <circle cx="12" cy="7" r="4" />
            <path d="M5 21v-2a7 7 0 0 1 14 0v2" />
        </Svg>
    ),
    'kids-s1': (cls) => (
        <Svg className={cls}>
            <rect x="4" y="8" width="16" height="10" rx="2" />
            <circle cx="9" cy="13" r="1" />
            <circle cx="15" cy="13" r="1" />
        </Svg>
    ),
    'kids-s2': (cls) => ICONS.clothing(cls),
    'kids-s3': (cls) => (
        <Svg className={cls}>
            <path d="M8 4h8v4H8z" />
            <path d="M6 8h12v12H6z" />
        </Svg>
    ),
    'kids-s4': (cls) => (
        <Svg className={cls}>
            <path d="M4 8h16v10H4z" />
            <line x1="8" y1="12" x2="16" y2="12" />
        </Svg>
    ),
    pets: (cls) => (
        <Svg className={cls}>
            <circle cx="8" cy="9" r="2" />
            <circle cx="16" cy="9" r="2" />
            <path d="M12 16c-3 0-5 2-5 5h10c0-3-2-5-5-5z" />
        </Svg>
    ),
    'pets-s1': (cls) => (
        <Svg className={cls}>
            <path d="M6 10h12v8H6z" />
            <path d="M8 10V7h8v3" />
        </Svg>
    ),
    'pets-s2': (cls) => ICONS.pets(cls),
    'pets-s3': (cls) => (
        <Svg className={cls}>
            <path d="M8 6h8l2 12H6l2-12z" />
        </Svg>
    ),
    'pets-s4': (cls) => (
        <Svg className={cls}>
            <rect x="6" y="8" width="12" height="10" rx="2" />
            <path d="M9 8V6h6v2" />
        </Svg>
    ),
    furniture: (cls) => (
        <Svg className={cls}>
            <rect x="4" y="10" width="16" height="10" rx="1" />
            <path d="M4 14h16M8 10V6h8v4" />
        </Svg>
    ),
    'furniture-s1': (cls) => (
        <Svg className={cls}>
            <rect x="4" y="6" width="16" height="14" rx="1" />
            <line x1="4" y1="12" x2="20" y2="12" />
        </Svg>
    ),
    'furniture-s2': (cls) => ICONS.furniture(cls),
    'furniture-s3': (cls) => (
        <Svg className={cls}>
            <rect x="3" y="8" width="18" height="4" />
            <path d="M6 12v8M18 12v8" />
        </Svg>
    ),
    'furniture-s4': (cls) => (
        <Svg className={cls}>
            <rect x="5" y="4" width="14" height="16" rx="1" />
            <path d="M5 10h14" />
        </Svg>
    ),
};

function resolveIconKey(slug, parentSlug) {
    if (!slug) return 'default';
    if (ICONS[slug]) return slug;

    if (parentSlug && ICONS[parentSlug]) {
        const suffix = slug.match(/-s(\d+)$/)?.[1];
        const childKey = suffix ? `${parentSlug}-s${suffix}` : null;
        if (childKey && ICONS[childKey]) return childKey;
        return parentSlug;
    }

    const rootMatch = slug.match(/^([a-z]+)-s\d+$/);
    if (rootMatch) {
        const childKey = slug;
        if (ICONS[childKey]) return childKey;
        if (ICONS[rootMatch[1]]) return rootMatch[1];
    }

    if (slug === 'clothes') return 'clothing';

    return 'default';
}

export default function CategoryIcon({ slug, parentSlug, className = 'category-icon-svg' }) {
    const key = resolveIconKey(slug, parentSlug);
    const render = ICONS[key] ?? ICONS.default;

    return <span className="category-icon">{render(className)}</span>;
}
