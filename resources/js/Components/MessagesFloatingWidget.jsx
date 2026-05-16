import React, { useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import MessagesMacControls from '@/Components/MessagesMacControls';
import {
  MSG_WIDGET_EV,
  isWidgetFloatFullscreen,
  isWidgetPinned,
  widgetClose,
  widgetToggleFloatFullscreen,
  widgetTogglePin,
} from '@/lib/messagesWidget';
import '../../css/messages-widget.css';

const LS_BOUNDS = 'msg_widget_bounds';
const LS_PATH = 'msg_widget_path';

function readBounds() {
  try {
    const j = JSON.parse(localStorage.getItem(LS_BOUNDS) || 'null');
    if (j && typeof j.left === 'number' && typeof j.top === 'number' && typeof j.width === 'number' && typeof j.height === 'number') {
      return j;
    }
  } catch {
    /* ignore */
  }
  return { left: 48, top: 100, width: 420, height: 520 };
}

function toEmbedSrc(pathWithQuery) {
  const p = pathWithQuery && pathWithQuery.startsWith('/') ? pathWithQuery : '/messages';
  if (p.startsWith('/messages/embed') || p.startsWith('/admin/support/embed')) {
    return p;
  }
  if (p.startsWith('/admin/support')) {
    return `/admin/support/embed${p.slice('/admin/support'.length) || ''}`;
  }
  if (p.startsWith('/messages')) {
    return `/messages/embed${p.slice('/messages'.length)}`;
  }
  return '/messages/embed';
}

export default function MessagesFloatingWidget() {
  const { url } = usePage();
  const rootRef = useRef(null);
  const chromeRef = useRef(null);
  const [pinned, setPinned] = useState(isWidgetPinned);
  const [fullscreen, setFullscreen] = useState(isWidgetFloatFullscreen);
  const [bounds, setBounds] = useState(readBounds);
  const [iframeSrc, setIframeSrc] = useState(() => toEmbedSrc(localStorage.getItem(LS_PATH) || '/messages'));

  const syncFromStorage = useCallback(() => {
    setPinned(isWidgetPinned());
    setFullscreen(isWidgetFloatFullscreen());
    setBounds(readBounds());
    setIframeSrc(toEmbedSrc(localStorage.getItem(LS_PATH) || '/messages'));
  }, []);

  useEffect(() => {
    const onUpdate = () => syncFromStorage();
    window.addEventListener(MSG_WIDGET_EV, onUpdate);
    return () => window.removeEventListener(MSG_WIDGET_EV, onUpdate);
  }, [syncFromStorage]);

  useEffect(() => {
    const onPath = (event) => {
      const path = event?.data?.path;
      if (!path || typeof path !== 'string') return;
      try {
        if (event.origin !== window.location.origin) return;
      } catch {
        return;
      }
      localStorage.setItem(LS_PATH, path);
    };
    window.addEventListener('message', onPath);
    return () => window.removeEventListener('message', onPath);
  }, []);

  const hideBecauseOnMessagesPage =
    (url.startsWith('/messages') && !url.includes('/messages/embed'))
    || (url.startsWith('/admin/support') && !url.includes('/admin/support/embed'));

  useEffect(() => {
    const el = rootRef.current;
    if (!el || fullscreen || hideBecauseOnMessagesPage || !pinned) return undefined;
    const ro = new ResizeObserver(() => {
      const r = el.getBoundingClientRect();
      const next = { left: r.left, top: r.top, width: r.width, height: r.height };
      localStorage.setItem(LS_BOUNDS, JSON.stringify(next));
    });
    ro.observe(el);
    return () => ro.disconnect();
  }, [fullscreen, hideBecauseOnMessagesPage, pinned]);

  const dragging = useRef(null);

  const isPointerOnChrome = useCallback((clientX, clientY) => {
    const chrome = chromeRef.current;
    if (!chrome) return false;
    const r = chrome.getBoundingClientRect();
    return (
      clientX >= r.left
      && clientX <= r.right
      && clientY >= r.top
      && clientY <= r.bottom
    );
  }, []);

  const endDrag = useCallback(() => {
    const d = dragging.current;
    if (!d) return;
    dragging.current = null;
    if (d.pointerId != null && chromeRef.current) {
      try {
        if (chromeRef.current.hasPointerCapture(d.pointerId)) {
          chromeRef.current.releasePointerCapture(d.pointerId);
        }
      } catch {
        /* ignore */
      }
    }
    document.body.style.removeProperty('user-select');
    document.body.style.removeProperty('cursor');
    const el = rootRef.current;
    if (el && !fullscreen) {
      const r = el.getBoundingClientRect();
      const next = { left: r.left, top: r.top, width: r.width, height: r.height };
      setBounds(next);
      localStorage.setItem(LS_BOUNDS, JSON.stringify(next));
    }
  }, [fullscreen]);

  const onChromePointerDown = (e) => {
    if (fullscreen || e.button !== 0) return;
    if (e.target.closest('.msg-mac-controls')) return;
    const el = rootRef.current;
    const chrome = chromeRef.current;
    if (!el || !chrome) return;
    const r = el.getBoundingClientRect();
    dragging.current = {
      startX: e.clientX,
      startY: e.clientY,
      origLeft: r.left,
      origTop: r.top,
      pointerId: e.pointerId,
    };
    try {
      chrome.setPointerCapture(e.pointerId);
    } catch {
      /* ignore */
    }
    document.body.style.userSelect = 'none';
    document.body.style.cursor = 'grabbing';
    e.preventDefault();
  };

  useEffect(() => {
    const move = (e) => {
      const d = dragging.current;
      if (!d || fullscreen) return;
      if (e.buttons === 0) {
        endDrag();
        return;
      }
      const el = rootRef.current;
      if (!el) return;
      const nx = d.origLeft + (e.clientX - d.startX);
      const ny = d.origTop + (e.clientY - d.startY);
      const maxLeft = window.innerWidth - el.offsetWidth - 8;
      const maxTop = window.innerHeight - el.offsetHeight - 8;
      const clampedLeft = Math.max(8, Math.min(nx, maxLeft));
      const clampedTop = Math.max(8, Math.min(ny, maxTop));
      const stuckAtEdge = Math.abs(clampedLeft - nx) > 0.5 || Math.abs(clampedTop - ny) > 0.5;
      if (stuckAtEdge && !isPointerOnChrome(e.clientX, e.clientY)) {
        endDrag();
        return;
      }
      el.style.left = `${clampedLeft}px`;
      el.style.top = `${clampedTop}px`;
      el.style.right = 'auto';
      el.style.bottom = 'auto';
    };
    const up = () => endDrag();
    const onLeaveWindow = (e) => {
      if (!dragging.current) return;
      if (e.relatedTarget === null) endDrag();
    };
    const onBlur = () => endDrag();
    const chrome = chromeRef.current;
    const onLostCapture = () => endDrag();
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
    window.addEventListener('pointercancel', up);
    document.documentElement.addEventListener('mouseleave', onLeaveWindow);
    window.addEventListener('blur', onBlur);
    chrome?.addEventListener('lostpointercapture', onLostCapture);
    return () => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
      window.removeEventListener('pointercancel', up);
      document.documentElement.removeEventListener('mouseleave', onLeaveWindow);
      window.removeEventListener('blur', onBlur);
      chrome?.removeEventListener('lostpointercapture', onLostCapture);
    };
  }, [fullscreen, pinned, endDrag, isPointerOnChrome]);

  if (!pinned || hideBecauseOnMessagesPage) {
    return null;
  }

  const style = fullscreen
    ? undefined
    : {
        left: bounds.left,
        top: bounds.top,
        width: bounds.width,
        height: bounds.height,
        right: 'auto',
        bottom: 'auto',
      };

  return (
    <div ref={rootRef} className={`msg-float${fullscreen ? ' msg-float--fs' : ''}`} style={style}>
      <div ref={chromeRef} className="msg-float-chrome" onPointerDown={onChromePointerDown}>
        <MessagesMacControls
          pinned={pinned}
          fullscreen={fullscreen}
          onClose={widgetClose}
          onTogglePin={widgetTogglePin}
          onToggleFullscreen={widgetToggleFloatFullscreen}
          size="small"
        />
        <div className="msg-float-drag">Сообщения</div>
      </div>
      <div className="msg-float-body">
        <iframe key={iframeSrc} title="Чат" src={iframeSrc} />
      </div>
    </div>
  );
}
