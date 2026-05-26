const STORAGE_KEY = 'alvora_catalog_search_history';
const MAX_ITEMS = 5;

export function getSearchHistory() {
  if (typeof window === 'undefined') {
    return [];
  }

  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) {
      return [];
    }
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed
      .map((item) => String(item ?? '').trim())
      .filter((item) => item !== '')
      .slice(0, MAX_ITEMS);
  } catch {
    return [];
  }
}

export function addSearchHistory(query) {
  const value = String(query ?? '').trim();
  if (!value || typeof window === 'undefined') {
    return;
  }

  const next = [value, ...getSearchHistory().filter((item) => item !== value)].slice(0, MAX_ITEMS);

  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
  } catch {
    // ignore quota / private mode
  }
}

export const SEARCH_HISTORY_LIMIT = MAX_ITEMS;
