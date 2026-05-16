import React from 'react';

/**
 * «Светофор» macOS: закрыть / закрепить на сайте / на весь экран.
 */
export default function MessagesMacControls({ pinned, fullscreen, onClose, onTogglePin, onToggleFullscreen, size = 'default' }) {
  const sm = size === 'small';
  return (
    <div className="msg-mac-controls" role="toolbar" aria-label="Управление окном чата">
      <button
        type="button"
        className="msg-mac msg-mac--close"
        title="Закрыть окно"
        aria-label="Закрыть"
        onClick={(e) => {
          e.stopPropagation();
          onClose();
        }}
      />
      <button
        type="button"
        className={`msg-mac msg-mac--pin${pinned ? ' msg-mac--active' : ''}`}
        title={pinned ? 'Открепить с сайта' : 'Закрепить — чат останется при переходах'}
        aria-label="Закрепить"
        onClick={(e) => {
          e.stopPropagation();
          onTogglePin();
        }}
      />
      <button
        type="button"
        className={`msg-mac msg-mac--fs${fullscreen ? ' msg-mac--active' : ''}`}
        title={fullscreen ? 'Выйти из полного экрана' : 'На весь экран'}
        aria-label="На весь экран"
        onClick={(e) => {
          e.stopPropagation();
          onToggleFullscreen();
        }}
      />
    </div>
  );
}
