import React, { useState } from 'react';

export default function ProfileHelpPanel({ title, intro, items = [], children }) {
  const [openId, setOpenId] = useState(items[0]?.id ?? null);

  const toggle = (id) => {
    setOpenId((prev) => (prev === id ? null : id));
  };

  return (
    <div className="profile-help-panel">
      <header className="profile-help-header">
        <h2 className="profile__title">{title}</h2>
        {intro && <p className="profile-help-intro">{intro}</p>}
      </header>

      <div className="profile-faq-list" role="list">
        {items.map((item) => {
          const isOpen = openId === item.id;
          return (
            <div
              key={item.id}
              className={`profile-faq-item${isOpen ? ' is-open' : ''}`}
              role="listitem"
            >
              <button
                type="button"
                className="profile-faq-question"
                onClick={() => toggle(item.id)}
                aria-expanded={isOpen}
              >
                <span>{item.question}</span>
                <span className="profile-faq-chevron" aria-hidden />
              </button>
              {isOpen && (
                <div className="profile-faq-answer">
                  <p>{item.answer}</p>
                </div>
              )}
            </div>
          );
        })}
      </div>

      {children && <div className="profile-help-action">{children}</div>}
    </div>
  );
}
