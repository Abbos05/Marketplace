export const MSG_WIDGET_EV = 'msg-widget:update';

export function emitMessagesWidget() {
  window.dispatchEvent(new CustomEvent(MSG_WIDGET_EV));
}

/** Закрыть плавающее окно: снять закрепление и полноэкран. */
export function widgetClose() {
  localStorage.setItem('msg_widget_pinned', '0');
  localStorage.setItem('msg_widget_fullscreen', '0');
  emitMessagesWidget();
}

/** Жёлтая кнопка: закрепить / открепить чат на сайте (окно на других страницах). */
export function widgetTogglePin() {
  const next = localStorage.getItem('msg_widget_pinned') === '1' ? '0' : '1';
  localStorage.setItem('msg_widget_pinned', next);
  if (next === '1') {
    const raw = `${window.location.pathname}${window.location.search}`;
    localStorage.setItem('msg_widget_path', raw);
    if (raw.startsWith('/admin/support') && !raw.includes('/embed')) {
      localStorage.setItem('msg_widget_path', `/admin/support/embed${raw.slice('/admin/support'.length)}`);
    } else if (raw.startsWith('/messages') && !raw.includes('/embed')) {
      localStorage.setItem('msg_widget_path', `/messages/embed${raw.slice('/messages'.length)}`);
    }
  }
  emitMessagesWidget();
}

/** Зелёная кнопка: полноэкран у плавающего окна (iframe). */
export function widgetToggleFloatFullscreen() {
  const next = localStorage.getItem('msg_widget_fullscreen') === '1' ? '0' : '1';
  localStorage.setItem('msg_widget_fullscreen', next);
  emitMessagesWidget();
}

export function isWidgetPinned() {
  return localStorage.getItem('msg_widget_pinned') === '1';
}

export function isWidgetFloatFullscreen() {
  return localStorage.getItem('msg_widget_fullscreen') === '1';
}

const LS_PAGE_FS = 'msg_page_chat_fs';

export function getPageChatFullscreen() {
  return localStorage.getItem(LS_PAGE_FS) === '1';
}

export function setPageChatFullscreen(on) {
  localStorage.setItem(LS_PAGE_FS, on ? '1' : '0');
}

export function togglePageChatFullscreen() {
  const next = localStorage.getItem(LS_PAGE_FS) === '1' ? '0' : '1';
  localStorage.setItem(LS_PAGE_FS, next);
}

/**
 * Закрепить плавающий чат в точке отпускания (перетаскивание с иконки в шапке).
 */
/** Сохранить путь открытого чата в iframe (без перезагрузки окна). */
export function syncWidgetPath(pathWithQuery) {
  if (!pathWithQuery || typeof pathWithQuery !== 'string') return;
  localStorage.setItem('msg_widget_path', pathWithQuery);
}

export const MSG_WIDGET_PATH_EV = 'msg-widget:path';

export function pinWidgetFromHeaderDrag(clientX, clientY) {
  const w = 800;
  const h = 400;
  let left = Math.round(clientX - w / 2);
  let top = Math.round(clientY - 36);
  left = Math.max(8, Math.min(left, window.innerWidth - w - 8));
  top = Math.max(8, Math.min(top, window.innerHeight - h - 8));
  localStorage.setItem('msg_widget_path', '/messages/embed');
  localStorage.setItem('msg_widget_bounds', JSON.stringify({ left, top, width: w, height: h }));
  localStorage.setItem('msg_widget_pinned', '1');
  localStorage.setItem('msg_widget_fullscreen', '0');
  emitMessagesWidget();
}
