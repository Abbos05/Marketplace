import React from 'react';

/** Минимальная оболочка для чата в iframe (без шапки сайта). */
export default function EmbedLayout({ children }) {
  return <div className="msg-embed-layout">{children}</div>;
}
