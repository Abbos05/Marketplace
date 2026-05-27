import React, { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { addSearchHistory, getSearchHistory } from '@/lib/searchHistory';
import { mergeCatalogSearchParams } from '@/lib/catalogFilters';

const DEBOUNCE_MS = 220;
const MIN_QUERY_LENGTH = 2;

function highlightMatch(text, query) {
  if (!text || !query) return text;
  const lowerText = text.toLowerCase();
  const lowerQuery = query.toLowerCase();
  const index = lowerText.indexOf(lowerQuery);
  if (index < 0) return text;

  return (
    <>
      {text.slice(0, index)}
      <mark className="header-search-suggest__mark">{text.slice(index, index + query.length)}</mark>
      {text.slice(index + query.length)}
    </>
  );
}

function SuggestionSearchIcon() {
  return (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="2" />
      <path d="M16 16l5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

function HistoryIcon() {
  return (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path
        d="M12 7v5l3 2"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <circle cx="12" cy="12" r="8" stroke="currentColor" strokeWidth="2" />
    </svg>
  );
}

function historyToItems(queries) {
  return queries.map((text) => ({ type: 'history', text, label: text }));
}

export default function HeaderSearchWithSuggestions({
  className = '',
  searchQuery,
  setSearchQuery,
  onSearch,
  onNavigate,
  filters,
}) {
  const listboxId = useId();
  const rootRef = useRef(null);
  const inputRef = useRef(null);
  const [suggestions, setSuggestions] = useState([]);
  const [historyItems, setHistoryItems] = useState([]);
  const [isOpen, setIsOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const abortRef = useRef(null);
  const allowPanelRef = useRef(false);
  const inputFocusedRef = useRef(false);

  const trimmed = searchQuery.trim();
  const showHistory = trimmed.length < MIN_QUERY_LENGTH;

  const panelItems = useMemo(
    () => (showHistory ? historyItems : suggestions),
    [showHistory, historyItems, suggestions],
  );

  const refreshHistory = useCallback(() => {
    const items = historyToItems(getSearchHistory());
    setHistoryItems(items);
    return items;
  }, []);

  const openHistoryPanel = useCallback(() => {
    const items = refreshHistory();
    if (items.length > 0) {
      setIsOpen(true);
      setActiveIndex(-1);
    }
  }, [refreshHistory]);

  const closePanel = useCallback(() => {
    allowPanelRef.current = false;
    setIsOpen(false);
    setSuggestions([]);
    setActiveIndex(-1);
    setLoading(false);
    if (abortRef.current) {
      abortRef.current.abort();
      abortRef.current = null;
    }
  }, []);

  const fetchSuggestions = useCallback(async (q) => {
    if (!allowPanelRef.current || !inputFocusedRef.current) {
      return;
    }

    if (q.length < MIN_QUERY_LENGTH) {
      setSuggestions([]);
      setLoading(false);
      if (q.length === 0) {
        openHistoryPanel();
      } else {
        setIsOpen(false);
      }
      return;
    }

    if (abortRef.current) {
      abortRef.current.abort();
    }

    const controller = new AbortController();
    abortRef.current = controller;
    setLoading(true);

    try {
      const response = await fetch(
        `/api/catalog/search-suggestions?q=${encodeURIComponent(q)}`,
        {
          headers: { Accept: 'application/json' },
          signal: controller.signal,
        },
      );
      if (!response.ok) {
        throw new Error('suggestions failed');
      }
      const data = await response.json();
      if (!allowPanelRef.current || !inputFocusedRef.current) {
        return;
      }

      const items = Array.isArray(data.suggestions) ? data.suggestions : [];
      setSuggestions(items);
      setIsOpen(items.length > 0);
      setActiveIndex(-1);
    } catch (error) {
      if (error?.name !== 'AbortError') {
        if (allowPanelRef.current && inputFocusedRef.current) {
          setSuggestions([]);
          setIsOpen(false);
        }
      }
    } finally {
      if (!controller.signal.aborted) {
        setLoading(false);
      }
    }
  }, [openHistoryPanel]);

  useEffect(() => {
    if (!allowPanelRef.current || !inputFocusedRef.current) {
      return undefined;
    }

    if (trimmed.length === 0) {
      openHistoryPanel();
      return undefined;
    }

    if (trimmed.length < MIN_QUERY_LENGTH) {
      setSuggestions([]);
      setIsOpen(false);
      return undefined;
    }

    const timer = window.setTimeout(() => {
      fetchSuggestions(trimmed);
    }, DEBOUNCE_MS);

    return () => window.clearTimeout(timer);
  }, [trimmed, fetchSuggestions, openHistoryPanel]);

  useEffect(() => {
    const onPointerDown = (event) => {
      if (rootRef.current && !rootRef.current.contains(event.target)) {
        closePanel();
        inputRef.current?.blur();
      }
    };
    document.addEventListener('mousedown', onPointerDown);
    return () => document.removeEventListener('mousedown', onPointerDown);
  }, [closePanel]);

  const navigateWithSearch = useCallback(
    (text) => {
      const value = String(text ?? '').trim();
      if (!value) return;

      addSearchHistory(value);
      closePanel();
      setSearchQuery(value);
      onNavigate?.();
      inputRef.current?.blur();

      if (/^\d+$/.test(value) && value.startsWith('000') && value.length >= 4) {
        router.visit(`/article/${value}`);
        return;
      }

      const params = mergeCatalogSearchParams(value, filters || {});

      const currentPath = window.location.pathname;
      let targetPath = '/';
      if (currentPath.startsWith('/admins')) targetPath = '/admins';
      else if (currentPath.startsWith('/category')) targetPath = currentPath;
      else if (currentPath.startsWith('/sellerProfile')) targetPath = currentPath;

      router.get(targetPath, params, {
        preserveScroll: false,
        preserveState: false,
      });
    },
    [closePanel, filters, onNavigate, setSearchQuery],
  );

  const applySuggestion = useCallback(
    (item) => {
      if (!item) return;
      navigateWithSearch(item.text ?? item.label);
    },
    [navigateWithSearch],
  );

  const handleSubmitSearch = useCallback(() => {
    const value = trimmed;
    if (value) {
      addSearchHistory(value);
    }
    closePanel();
    inputRef.current?.blur();
    onSearch();
  }, [closePanel, onSearch, trimmed]);

  const onInputKeyDown = (event) => {
    if (!isOpen || panelItems.length === 0) {
      if (event.key === 'Enter') {
        handleSubmitSearch();
      }
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setActiveIndex((prev) => (prev + 1) % panelItems.length);
      return;
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();
      setActiveIndex((prev) => (prev <= 0 ? panelItems.length - 1 : prev - 1));
      return;
    }

    if (event.key === 'Escape') {
      closePanel();
      return;
    }

    if (event.key === 'Enter') {
      event.preventDefault();
      if (activeIndex >= 0 && panelItems[activeIndex]) {
        applySuggestion(panelItems[activeIndex]);
      } else {
        handleSubmitSearch();
      }
    }
  };

  const renderPanelRow = (item, index) => {
    const isActive = index === activeIndex;
    const mainText = item.label ?? item.text ?? '';
    const isHistory = item.type === 'history';

    return (
      <li
        key={`${item.type}-${mainText}-${index}`}
        role="option"
        aria-selected={isActive}
        className={`header-search-suggest__item${isActive ? ' header-search-suggest__item--active' : ''}`}
      >
        <button
          type="button"
          className="header-search-suggest__btn"
          onMouseDown={(e) => e.preventDefault()}
          onClick={() => applySuggestion(item)}
        >
          <span
            className={`header-search-suggest__icon${
              isHistory ? ' header-search-suggest__icon--history' : ' header-search-suggest__icon--search'
            }`}
          >
            {isHistory ? <HistoryIcon /> : <SuggestionSearchIcon />}
          </span>
          <span className="header-search-suggest__title">
            {isHistory ? mainText : highlightMatch(mainText, trimmed)}
          </span>
        </button>
      </li>
    );
  };

  const showPanel =
    isOpen && allowPanelRef.current && (panelItems.length > 0 || (loading && !showHistory));

  return (
    <div
      ref={rootRef}
      className={`header-search header-search--suggest ${className}`.trim()}
    >
      <input
        ref={inputRef}
        type="text"
        placeholder="Поиск по названию или артикулу..."
        maxLength="45"
        value={searchQuery}
        onChange={(e) => {
          allowPanelRef.current = true;
          setSearchQuery(e.target.value);
        }}
        onFocus={() => {
          inputFocusedRef.current = true;
          allowPanelRef.current = true;
          if (trimmed.length >= MIN_QUERY_LENGTH) {
            fetchSuggestions(trimmed);
          } else if (trimmed.length === 0) {
            openHistoryPanel();
          }
        }}
        onBlur={() => {
          inputFocusedRef.current = false;
          window.setTimeout(() => {
            if (!inputFocusedRef.current) {
              closePanel();
            }
          }, 120);
        }}
        onKeyDown={onInputKeyDown}
        role="combobox"
        aria-expanded={showPanel}
        aria-controls={listboxId}
        aria-autocomplete="list"
        aria-activedescendant={
          activeIndex >= 0 ? `${listboxId}-option-${activeIndex}` : undefined
        }
        autoComplete="off"
        spellCheck={false}
      />



      <button
        type="button"
        onClick={handleSubmitSearch}
        className="search-btn search-btn--submit"
        aria-label="Найти"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 27" fill="none">
          <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="2.3" />
          <path d="M16.2 16.2L21 21" stroke="currentColor" strokeWidth="2.3" strokeLinecap="round" />
        </svg>
      </button>

      {showPanel ? (
        <div className="header-search-suggest">
          {showHistory && historyItems.length > 0 ? (
            <p className="header-search-suggest__heading">История поиска</p>
          ) : null}
          {loading && !showHistory && panelItems.length === 0 ? (
            <p className="header-search-suggest__status">Ищем подсказки…</p>
          ) : null}
          {panelItems.length > 0 ? (
            <ul id={listboxId} className="header-search-suggest__list" role="listbox">
              {panelItems.map((item, index) => renderPanelRow(item, index))}
            </ul>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
