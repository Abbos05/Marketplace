import React, { useState } from 'react';

export default function FilterSection({ title, children, defaultOpen = true }) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <div className={`filter-section ${open ? 'filter-section--open' : 'filter-section--collapsed'}`}>
            <button
                type="button"
                className="filter-section__header"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
            >
                <span className="filter-section__title">{title}</span>
                <span className="filter-section__chevron" aria-hidden />
            </button>
            {open && <div className="filter-section__body">{children}</div>}
        </div>
    );
}
