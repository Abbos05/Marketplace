/**
 * Ссылка «Открыть» только для реальных страниц (заказ, товар, ПВЗ…).
 * Ссылки на ленту уведомлений /messages?notifications=1 не показываем.
 */
export function resolveNotificationActionUrl(url) {
  if (!url || typeof url !== 'string') return null;

  const trimmed = url.trim();
  if (!trimmed) return null;

  try {
    const parsed = new URL(trimmed, typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
    let path = parsed.pathname.replace(/\/$/, '') || '/';

    const isMessagesHub = /^\/messages(\/embed)?$/.test(path);
    const isSupportHub = /^\/admin\/support(\/embed)?$/.test(path);

    if (isMessagesHub || isSupportHub) {
      if (parsed.searchParams.has('conversation')) {
        return trimmed;
      }
      if (parsed.searchParams.has('notifications') || parsed.search === '') {
        return null;
      }
    }

    return trimmed;
  } catch {
    return null;
  }
}
