import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import EmbedLayout from '@/Layouts/EmbedLayout';
import MessagesMacControls from '@/Components/MessagesMacControls';
import {
  MSG_WIDGET_EV,
  getPageChatFullscreen,
  isWidgetPinned,
  setPageChatFullscreen,
  togglePageChatFullscreen,
  widgetClose,
  widgetTogglePin,
  syncWidgetPath,
} from '@/lib/messagesWidget';
import { format, isToday, isYesterday } from 'date-fns';
import { ru } from 'date-fns/locale';
import '../../../css/messages.css';
import '../../../css/messages-widget.css';

function labelForDay(iso) {
  const d = new Date(iso);
  if (isToday(d)) return 'Сегодня';
  if (isYesterday(d)) return 'Вчера';
  return format(d, 'd MMMM yyyy', { locale: ru });
}

function timeInBubble(iso) {
  return format(new Date(iso), 'HH:mm', { locale: ru });
}

/** Окно редактирования / удаления своего сообщения (1 мин 30 с), как на сервере. */
const MESSAGE_MUTATE_MS = 900 * 1000;

function messageCanMutate(createdIso) {
  if (!createdIso) return false;
  return Date.now() - new Date(createdIso).getTime() < MESSAGE_MUTATE_MS;
}

function MessageBubbleContent({ message: m }) {
  const hasText = !!(m.body && String(m.body).trim());
  return (
    <>
      {m.attachment_url && m.is_attachment_image && (
        <a href={m.attachment_url} target="_blank" rel="noopener noreferrer" className="msg-attachment-img-wrap">
          <img src={m.attachment_url} alt="" className="msg-attachment-img" loading="lazy" />
        </a>
      )}
      {m.attachment_url && !m.is_attachment_image && (
        <a href={m.attachment_url} target="_blank" rel="noopener noreferrer" className="msg-attachment-file">
          <span className="msg-attachment-file-icon" aria-hidden>
            {(m.attachment_mime || '').includes('pdf') ? '📄' : '📎'}
          </span>
          <span className="msg-attachment-file-name">{m.attachment_name || 'Скачать файл'}</span>
        </a>
      )}
      {hasText && <div className="msg-bubble-text">{m.body}</div>}
    </>
  );
}

function MessageBubbleMeta({ message: m }) {
  return (
    <div className="msg-bubble-meta">
      <span className="msg-bubble-time">{timeInBubble(m.created_at)}</span>
      {m.is_edited && <span className="msg-bubble-edited">изменено</span>}
    </div>
  );
}

function goProfile(kind, userId) {
  if (kind === 'self') {
    router.visit(route('profile'));
    return;
  }
  if (kind === 'seller_store' && userId) {
    router.visit(route('seller.index', userId));
    return;
  }
  if (kind === 'member' && userId) {
    router.visit(route('admin.users.detail', userId));
  }
}

function profileAriaLabel(kind) {
  if (kind === 'self') return 'Мой профиль';
  if (kind === 'seller_store') return 'Страница продавца';
  if (kind === 'member') return 'Профиль пользователя';
  return '';
}

function ProfileAvatarWrap({ kind, userId, className, style, children, profileDisabled = false }) {
  const clickable = !profileDisabled && (kind === 'self' || kind === 'seller_store' || kind === 'member');
  if (!clickable) {
    return (
      <div className={className} style={style}>
        {children}
      </div>
    );
  }
  return (
    <button
      type="button"
      className={`${className} msg-avatar-btn`.trim()}
      style={style}
      onClick={() => goProfile(kind, userId)}
      aria-label={profileAriaLabel(kind)}
    >
      {children}
    </button>
  );
}

function MessageSenderLabel({ message, profileDisabled = false }) {
  const role = message.sender_role || 'user';
  const isStaff = Boolean(message.sender_is_staff);
  const displayName = message.sender_display_name || message.sender_name?.trim() || 'Пользователь';
  const realName = (message.sender_name || '').trim();
  const showSubtitle = isStaff && realName && realName !== displayName;
  const profileKind = message.sender_profile_kind;
  const profileUserId = message.sender_profile_user_id;
  const profileClickable =
    !profileDisabled
    && (profileKind === 'member' || profileKind === 'seller_store' || profileKind === 'self');

  const roleClass = isStaff ? `msg-sender-label--${role}` : 'msg-sender-label--user';

  const inner = (
    <>
     
      <span className="msg-sender-label-text">{displayName}</span>
      {showSubtitle && <span className="msg-sender-label-sub">{realName}</span>}
    </>
  );

  if (profileClickable && profileUserId) {
    return (
      <button
        type="button"
        className={`msg-sender-label msg-sender-label--btn ${roleClass}`}
        onClick={() => goProfile(profileKind, profileUserId)}
      >
        {inner}
      </button>
    );
  }

  return <div className={`msg-sender-label ${roleClass}`}>{inner}</div>;
}

export default function Index() {
  const page = usePage();
  const {
    auth,
    threads: initialThreads = [],
    activeConversation,
    messages: initialMessages = [],
    isAdminSupport = false,
    supportInboxUnreadCount = 0,
    notificationsFeed: initialNotifFeed = [],
    notificationsUnreadCount: initialNotifUnread = 0,
    showNotifications = false,
    embed = false,
    staffForTransfer = [],
    staffMergedInbox = false,
    supportFilter: initialSupportFilter = 'all',
  } = page.props;

  const isStaff = ['admin', 'moderator'].includes(auth?.user?.role);
  const showSupportFilters = isAdminSupport || (staffMergedInbox && isStaff);

  const [threads, setThreads] = useState(initialThreads);
  const [messages, setMessages] = useState(initialMessages);
  const [supportInboxUnread, setSupportInboxUnread] = useState(() => Number(supportInboxUnreadCount) || 0);
  const [notifItems, setNotifItems] = useState(initialNotifFeed || []);
  const [notifUnread, setNotifUnread] = useState(() => Number(initialNotifUnread) || 0);
  const [mobileChat, setMobileChat] = useState(false);
  const scrollRef = useRef(null);
  const notifScrollRef = useRef(null);
  const pageRef = useRef(null);
  const compactBackRef = useRef(false);
  const fileInputRef = useRef(null);

  useEffect(() => {
    setThreads(initialThreads || []);
  }, [initialThreads]);

  useEffect(() => {
    setMessages(initialMessages || []);
  }, [initialMessages]);

  useEffect(() => {
    setSupportInboxUnread(Number(supportInboxUnreadCount) || 0);
  }, [supportInboxUnreadCount]);

  useEffect(() => {
    setNotifItems(initialNotifFeed || []);
  }, [initialNotifFeed]);

  useEffect(() => {
    setNotifUnread(Number(initialNotifUnread) || 0);
  }, [initialNotifUnread]);

  const [activePatch, setActivePatch] = useState(null);
  const [sendBusy, setSendBusy] = useState(false);
  const [sendError, setSendError] = useState('');
  const [editingId, setEditingId] = useState(null);
  const [editDraft, setEditDraft] = useState('');
  const [editBusy, setEditBusy] = useState(false);
  const [editError, setEditError] = useState('');
  const [mutTick, setMutTick] = useState(0);
  const [pendingFile, setPendingFile] = useState(null);
  const [transferStaffId, setTransferStaffId] = useState('');
  const supportFilter = initialSupportFilter || 'all';

  const SUPPORT_FILTERS = [
    { id: 'all', label: 'Все' },
    { id: 'new', label: 'Новые' },
    { id: 'mine', label: 'У меня' },
    { id: 'transferred', label: 'Переданные' },
  ];

  const pendingPreviewUrl = useMemo(() => {
    if (!pendingFile || !pendingFile.type?.startsWith?.('image/')) return null;
    return URL.createObjectURL(pendingFile);
  }, [pendingFile]);

  useEffect(() => {
    return () => {
      if (pendingPreviewUrl) URL.revokeObjectURL(pendingPreviewUrl);
    };
  }, [pendingPreviewUrl]);

  useEffect(() => {
    setActivePatch(null);
  }, [activeConversation?.id]);

  const active = activePatch ?? activeConversation;
  const activeId = active?.id ?? null;
  const staffSupportActive =
    isStaff && active?.type === 'support' && active?.support_queue_status != null;

  useEffect(() => {
    setPendingFile(null);
  }, [activeId]);

  const form = useForm({ message: '' });

  const showNotifView = Boolean(showNotifications);

  const [widgetPinnedUi, setWidgetPinnedUi] = useState(() => isWidgetPinned());
  const [pageChatFs, setPageChatFs] = useState(() => getPageChatFullscreen());

  useEffect(() => {
    const sync = () => {
      setWidgetPinnedUi(isWidgetPinned());
    };
    window.addEventListener(MSG_WIDGET_EV, sync);
    return () => window.removeEventListener(MSG_WIDGET_EV, sync);
  }, []);

  useEffect(() => {
    if (!activeId || showNotifView) return undefined;
    const id = setInterval(() => setMutTick((x) => x + 1), 4000);
    return () => clearInterval(id);
  }, [activeId, showNotifView]);

  const applyMutationJson = useCallback((j) => {
    if (Array.isArray(j.threads)) setThreads(j.threads);
    if (Array.isArray(j.messages)) setMessages(j.messages);
    if (j.activeConversation) setActivePatch(j.activeConversation);
    if (Array.isArray(j.notificationsFeed)) setNotifItems(j.notificationsFeed);
    if (typeof j.notificationsUnreadCount === 'number') {
      setNotifUnread(Number(j.notificationsUnreadCount) || 0);
    }
    if (typeof j.hubUnreadCount === 'number') {
      window.dispatchEvent(new CustomEvent('inertia:messages-hub-unread', { detail: j.hubUnreadCount }));
    }
    if (typeof j.supportInboxUnreadCount === 'number') {
      setSupportInboxUnread(Number(j.supportInboxUnreadCount) || 0);
    }
  }, []);

  useEffect(() => {
    setEditingId(null);
    setEditDraft('');
    setEditError('');
  }, [activeConversation?.id]);


  const scrollToBottom = useCallback(() => {
    const el = showNotifView ? notifScrollRef.current : scrollRef.current;
    if (!el) return;
    requestAnimationFrame(() => {
      el.scrollTop = el.scrollHeight;
    });
  }, [showNotifView]);

  useEffect(() => {
    scrollToBottom();
  }, [messages, activeId, scrollToBottom, notifItems, showNotifView]);

  const totalUnread = useMemo(
    () =>
      (threads || []).reduce((acc, t) => acc + (Number(t.unread_count) || 0), 0) + (Number(notifUnread) || 0),
    [threads, notifUnread],
  );

  useEffect(() => {
    const timer = setInterval(async () => {
      try {
        const params = new URLSearchParams();
        if (activeId) params.set('conversation', String(activeId));
        const pollAsAdmin =
          isAdminSupport ||
          (active?.type === 'support' && isStaff && staffSupportActive);
        if (isAdminSupport) {
          params.set('admin_queue_only', '1');
          if (pollAsAdmin) params.set('admin', '1');
          params.set('filter', supportFilter);
        } else if (isStaff) {
          params.set('merged', '1');
          if (pollAsAdmin) params.set('admin', '1');
          params.set('filter', supportFilter);
        } else {
          params.set('admin', pollAsAdmin ? '1' : '0');
        }
        const res = await fetch(`${route('messages.poll')}?${params.toString()}`, {
          credentials: 'same-origin',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) return;
        const j = await res.json();
        if (Array.isArray(j.threads)) {
          setThreads(j.threads);
        }
        if (Array.isArray(j.notificationsFeed)) setNotifItems(j.notificationsFeed);
        if (typeof j.notificationsUnreadCount === 'number') {
          setNotifUnread(Number(j.notificationsUnreadCount) || 0);
        }
        if (typeof j.hubUnreadCount === 'number') {
          window.dispatchEvent(new CustomEvent('inertia:messages-hub-unread', { detail: j.hubUnreadCount }));
        }
        if (typeof j.supportInboxUnreadCount === 'number') {
          setSupportInboxUnread(Number(j.supportInboxUnreadCount) || 0);
        }
        if (activeId) {
          if (j.activeConversation && Number(j.activeConversation.id) === Number(activeId)) {
            if (Array.isArray(j.messages)) setMessages(j.messages);
            setActivePatch(j.activeConversation);
          } else if (j.activeConversation === null) {
            setMessages([]);
            setActivePatch(null);
          }
        }
      } catch {
        /* ignore */
      }
    }, 5000);
    return () => clearInterval(timer);
  }, [activeId, isAdminSupport, showNotifView, active?.type, auth?.user?.role, supportFilter, staffMergedInbox, isStaff, staffSupportActive]);

  const messagesRouteName = embed ? 'messages.embed' : 'messages.index';

  const openThread = (id) => {
    compactBackRef.current = false;
    setMobileChat(true);
    const params = { conversation: id };
    if (showSupportFilters) params.filter = supportFilter;
    router.get(route(messagesRouteName), params, { preserveState: false });
  };

  const changeSupportFilter = (filterId) => {
    const params = { filter: filterId };
    if (activeId) params.conversation = activeId;
    router.get(route(messagesRouteName), params, { preserveState: true, preserveScroll: true });
  };

  const openSupport = () => {
    router.post(route('messages.open'), { type: 'support', embed: embed ? 1 : 0 });
  };

  const hideChat = () => {
    if (!activeId) return;
    const confirmText = isAdminSupport
      ? 'Скрыть это обращение для всех модераторов? Клиент переписку сохранит.'
      : 'Скрыть этот чат в вашем списке? Собеседник переписку сохранит.';
    if (!window.confirm(confirmText)) return;
    router.post(route('messages.hide', activeId), { embed: embed ? 1 : 0 });
  };

  const openNotificationsPanel = () => {
    compactBackRef.current = false;
    setMobileChat(true);
    router.get(route(messagesRouteName), { notifications: 1 }, { preserveState: false });
  };

  const markNotifReadOne = async (id) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const fd = new FormData();
    fd.append('_token', token);
    try {
      const res = await fetch(route('notifications.read', id), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
      });
      const j = await res.json().catch(() => ({}));
      if (!res.ok) return;
      if (Array.isArray(j.notificationsFeed)) setNotifItems(j.notificationsFeed);
      if (typeof j.notificationsUnreadCount === 'number') {
        setNotifUnread(Number(j.notificationsUnreadCount) || 0);
      }
      if (typeof j.hubUnreadCount === 'number') {
        window.dispatchEvent(new CustomEvent('inertia:messages-hub-unread', { detail: j.hubUnreadCount }));
      }
    } catch {
      /* ignore */
    }
  };

  const markAllNotifs = async () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const fd = new FormData();
    fd.append('_token', token);
    try {
      const res = await fetch(route('notifications.read-all'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
      });
      const j = await res.json().catch(() => ({}));
      if (!res.ok) return;
      if (Array.isArray(j.notificationsFeed)) setNotifItems(j.notificationsFeed);
      setNotifUnread(0);
      if (typeof j.hubUnreadCount === 'number') {
        window.dispatchEvent(new CustomEvent('inertia:messages-hub-unread', { detail: j.hubUnreadCount }));
      }
    } catch {
      /* ignore */
    }
  };

  const submitMessage = async (e) => {
    e.preventDefault();
    if (!activeId || sendBusy) return;
    if (!form.data.message.trim() && !pendingFile) return;
    setSendError('');
    setSendBusy(true);
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const fd = new FormData();
    fd.append('message', form.data.message.trim());
    fd.append('_token', token);
    if (pendingFile) fd.append('attachment', pendingFile);
    try {
      const res = await fetch(route('messages.messages.store', activeId), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: fd,
      });
      const j = await res.json().catch(() => ({}));
      if (!res.ok) {
        const errText =
          j?.errors?.message?.[0] ||
          j?.errors?.attachment?.[0] ||
          (typeof j?.message === 'string' ? j.message : null) ||
          'Не удалось отправить';
        setSendError(errText);
        setSendBusy(false);
        return;
      }
      applyMutationJson(j);
      form.reset('message');
      setPendingFile(null);
      setTimeout(scrollToBottom, 50);
    } catch {
      setSendError('Нет связи с сервером');
    }
    setSendBusy(false);
  };

  const onPickAttachment = (e) => {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (!f) return;
    setSendError('');
    setPendingFile(f);
  };

  const cancelEdit = () => {
    setEditingId(null);
    setEditDraft('');
    setEditError('');
  };

  const startEdit = (m) => {
    if (!messageCanMutate(m.created_at)) return;
    setEditingId(m.id);
    setEditDraft(m.body || '');
    setEditError('');
  };

  const saveEdit = async () => {
    if (!activeId || !editingId || editBusy) return;
    const text = editDraft.trim();
    const cur = (messages || []).find((x) => Number(x.id) === Number(editingId));
    if (!text && !(cur && cur.attachment_url)) {
      setEditError('Введите текст');
      return;
    }
    setEditBusy(true);
    setEditError('');
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
      const res = await fetch(route('messages.messages.update', { conversation: activeId, message: editingId }), {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify({ message: text }),
      });
      const j = await res.json().catch(() => ({}));
      if (!res.ok) {
        setEditError(typeof j.message === 'string' ? j.message : 'Не удалось сохранить');
        setEditBusy(false);
        return;
      }
      applyMutationJson(j);
      cancelEdit();
      setTimeout(scrollToBottom, 50);
    } catch {
      setEditError('Нет связи с сервером');
    }
    setEditBusy(false);
  };

  const handleDeleteMessage = async (mid) => {
    const found = (messages || []).find((x) => Number(x.id) === Number(mid));
    if (!activeId || !found || !messageCanMutate(found.created_at)) return;
    if (!window.confirm('Удалить это сообщение?')) return;
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
      const res = await fetch(route('messages.messages.destroy', { conversation: activeId, message: mid }), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
      });
      const j = await res.json().catch(() => ({}));
      if (!res.ok) {
        const err = typeof j.message === 'string' ? j.message : 'Не удалось удалить';
        window.alert(err);
        return;
      }
      applyMutationJson(j);
      if (Number(editingId) === Number(mid)) cancelEdit();
      setTimeout(scrollToBottom, 50);
    } catch {
      window.alert('Нет связи с сервером');
    }
  };

  const grouped = useMemo(() => {
    void mutTick;
    const rows = [];
    let lastLabel = null;
    (messages || []).forEach((m) => {
      const lab = labelForDay(m.created_at);
      if (lab !== lastLabel) {
        rows.push({ kind: 'date', key: `d-${m.id}-${lab}`, label: lab });
        lastLabel = lab;
      }
      rows.push({ kind: 'msg', key: `m-${m.id}`, m });
    });
    return rows;
  }, [messages, mutTick]);

  const groupedNotifs = useMemo(() => {
    const rows = [];
    let lastLabel = null;
    (notifItems || []).forEach((n) => {
      if (!n.created_at) return;
      const lab = labelForDay(n.created_at);
      if (lab !== lastLabel) {
        rows.push({ kind: 'date', key: `nd-${n.id}-${lab}`, label: lab });
        lastLabel = lab;
      }
      rows.push({ kind: 'n', key: `nn-${n.id}`, n });
    });
    return rows;
  }, [notifItems]);

  const showChatPane = mobileChat && (activeId || showNotifView);

  const layoutClass = [
    'msg-page',
    showChatPane ? 'msg-page--chat' : '',
    embed ? 'msg-page--embed' : '',
    pageChatFs && !embed ? 'msg-page--fullscreen' : '',
  ]
    .filter(Boolean)
    .join(' ');

  const goBackToList = useCallback(() => {
    compactBackRef.current = true;
    setMobileChat(false);
  }, []);

  useEffect(() => {
    compactBackRef.current = false;
  }, [activeId, showNotifView]);

  useEffect(() => {
    if (!embed) return undefined;
    document.documentElement.classList.add('msg-embed-doc');
    document.body.classList.add('msg-embed-doc');
    return () => {
      document.documentElement.classList.remove('msg-embed-doc');
      document.body.classList.remove('msg-embed-doc');
    };
  }, [embed]);

  useEffect(() => {
    const el = pageRef.current;
    if (!el) return undefined;

    const syncCompactNav = () => {
      const compact = el.getBoundingClientRect().width < 640;
      if (!compact) {
        compactBackRef.current = false;
        return;
      }
      if ((activeId || showNotifView) && !compactBackRef.current) {
        setMobileChat(true);
      }
    };

    syncCompactNav();
    const ro = new ResizeObserver(syncCompactNav);
    ro.observe(el);
    return () => ro.disconnect();
  }, [activeId, showNotifView]);

  const Shell = embed ? EmbedLayout : MainLayout;
  const shellProps = embed ? {} : { auth };

  const onMacClose = () => {
    widgetClose();
    setPageChatFullscreen(false);
    setPageChatFs(false);
  };
  const onMacPin = () => widgetTogglePin();
  const onMacGreen = () => {
    togglePageChatFullscreen();
    setPageChatFs(getPageChatFullscreen());
  };

  const headKind = active?.counterpart_profile_kind ?? 'none';
  const headUserId = active?.counterpart_user_id ?? null;
  const titleClickable =
    !embed && (headKind === 'self' || headKind === 'seller_store' || headKind === 'member');
  const showSenderNames = isAdminSupport || active?.type === 'support';
  const supportUnassigned = staffSupportActive && active?.support_queue_status === 'unassigned';
  const supportAssignedToMe = staffSupportActive && active?.support_queue_status === 'mine';
  const supportTransferred = staffSupportActive && active?.support_queue_status === 'transferred';
  const canReplySupport = !staffSupportActive || active?.support_can_reply === true;

  const takeSupportChat = () => {
    if (!activeId) return;
    const msg = supportTransferred
      ? 'Забрать обращение обратно? Текущий оператор потеряет возможность отвечать.'
      : null;
    if (msg && !window.confirm(msg)) return;
    router.post(route('admin.support.assign', activeId), { filter: supportFilter, embed: embed ? 1 : 0 });
  };

  const transferSupportChat = () => {
    if (!activeId || !transferStaffId) return;
    if (!window.confirm('Передать обращение другому сотруднику? История останется у вас в разделе «Переданные».')) return;
    router.post(route('admin.support.transfer', activeId), {
      staff_id: Number(transferStaffId),
      filter: supportFilter,
      embed: embed ? 1 : 0,
    });
  };

  useEffect(() => {
    setTransferStaffId('');
  }, [activeId]);

  useEffect(() => {
    if (!embed) return;
    const path = page.url || `${window.location.pathname}${window.location.search}`;
    syncWidgetPath(path);
    try {
      window.parent?.postMessage({ type: 'msg-widget-path', path }, window.location.origin);
    } catch {
      /* ignore */
    }
  }, [embed, page.url, activeId, supportFilter]);

  return (
    <Shell {...shellProps}>
      <Head title={isAdminSupport ? 'Поддержка — чаты' : 'Мои сообщения'} />
      <div className={layoutClass} ref={pageRef}>
        {['admin', 'moderator'].includes(auth?.user?.role) && !isAdminSupport && Number(supportInboxUnread) > 0 && (
          <div className="msg-admin-inbox-hint">
            <Link href={route(embed ? 'messages.embed' : 'admin.support')}>
              Очередь поддержки: {Number(supportInboxUnread) > 99 ? '99+' : supportInboxUnread} непрочитанных — открыть
            </Link>
          </div>
        )}
        <div className="msg-shell">
          <aside className="msg-sidebar">
            <div className="msg-sidebar-head">
              <div className="msg-sidebar-head-main">
                <h1 className="msg-sidebar-title">{isAdminSupport ? 'Поддержка' : 'Мои сообщения'}</h1>
                {totalUnread > 0 && <span className="msg-badge">{totalUnread > 99 ? '99+' : totalUnread}</span>}
              </div>
              {!isAdminSupport && !embed && (
                <div className="msg-sidebar-mac">
                  <MessagesMacControls
                    pinned={widgetPinnedUi}
                    fullscreen={pageChatFs}
                    onClose={onMacClose}
                    onTogglePin={onMacPin}
                    onToggleFullscreen={onMacGreen}
                  />
                </div>
              )}
            </div>
            <div className="msg-sidebar-actions">
              {!isAdminSupport && !['admin', 'moderator'].includes(auth?.user?.role) && (
                <button type="button" className="msg-btn-ghost" onClick={openSupport}>
                  Написать в поддержку
                </button>
              )}
            </div>
            {showSupportFilters && (
              <div className="msg-support-filters" role="tablist" aria-label="Фильтр очереди">
                {SUPPORT_FILTERS.map((f) => (
                  <button
                    key={f.id}
                    type="button"
                    role="tab"
                    aria-selected={supportFilter === f.id}
                    className={`msg-support-filter${supportFilter === f.id ? ' msg-support-filter--active' : ''}`}
                    onClick={() => changeSupportFilter(f.id)}
                  >
                    {f.label}
                  </button>
                ))}
              </div>
            )}
            <div className="msg-thread-list">
              <button
                type="button"
                className={`msg-thread msg-thread--notifs ${showNotifView ? 'msg-thread--active' : ''}`}
                onClick={openNotificationsPanel}
              >
                <div className="msg-thread-avatar msg-thread-avatar--bell" aria-hidden>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path
                      d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6V11c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"
                      fill="currentColor"
                    />
                  </svg>
                </div>
                <div className="msg-thread-body">
                  <div className="msg-thread-title">
                    Уведомления
                    {notifUnread > 0 && <span className="msg-thread-unread">{notifUnread > 9 ? '9+' : notifUnread}</span>}
                  </div>
                  <div className="msg-thread-preview">Сервисные сообщения</div>
                </div>
              </button>
              {(threads || []).length === 0 && (
                <div className="msg-main-empty" style={{ border: 'none', minHeight: 120 }}>
                  Нет диалогов
                </div>
              )}
              {(threads || []).map((t) => (
                <button
                  key={t.id}
                  type="button"
                  className={`msg-thread ${activeId === t.id ? 'msg-thread--active' : ''}`}
                  onClick={() => openThread(t.id)}
                >
                  <div className="msg-thread-avatar">
                    {t.avatar_url ? (
                      <img src={t.avatar_url} alt="" style={{ width: '100%', height: '100%', borderRadius: 10, objectFit: 'cover' }} />
                    ) : (
                      (t.title || '?').slice(0, 1).toUpperCase()
                    )}
                  </div>
                  <div className="msg-thread-body">
                    <div className="msg-thread-title">
                      {t.title}
                      {t.type === 'support' && t.support_queue_status === 'unassigned' && (
                        <span className="msg-thread-tag msg-thread-tag--new">Новое</span>
                      )}
                      {t.type === 'support' && t.support_queue_status === 'mine' && (
                        <span className="msg-thread-tag msg-thread-tag--mine">У вас</span>
                      )}
                      {t.type === 'support' && t.support_queue_status === 'transferred' && (
                        <span className="msg-thread-tag msg-thread-tag--transferred">Передано</span>
                      )}
                      {t.unread_count > 0 && <span className="msg-thread-unread">{t.unread_count > 9 ? '9+' : t.unread_count}</span>}
                    </div>
                    <div className="msg-thread-preview">
                      {t.preview_prefix}: {t.preview || '—'}
                    </div>
                  </div>
                </button>
              ))}
            </div>
          </aside>

          <section className="msg-main">
            {!activeId && !showNotifView && (
              <div className="msg-main-empty">
                Выберите чат слева
                {!isAdminSupport && !['admin', 'moderator'].includes(auth?.user?.role) && (
                  <>
                    <br />
                    или напишите в поддержку
                  </>
                )}
              </div>
            )}

            {showNotifView && (
              <>
                <div className="msg-topbar">
                  <div className="msg-topbar-left">
                    <button type="button" className="msg-mobile-back" onClick={goBackToList}>
                      ← Назад
                    </button>
                    <div className="msg-thread-avatar msg-thread-avatar--bell" style={{ width: 36, height: 36, borderRadius: 8 }}>
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path
                          d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6V11c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"
                          fill="currentColor"
                        />
                      </svg>
                    </div>
                    <div>
                      <div className="msg-topbar-title">Уведомления</div>
                      <div className="msg-notif-sub">Все события аккаунта и заказов</div>
                    </div>
                  </div>
                  {(notifItems || []).some((n) => !n.read) && (
                    <button type="button" className="msg-btn-ghost msg-btn-ghost--compact" onClick={markAllNotifs}>
                      Прочитать все
                    </button>
                  )}
                </div>

                <div className="msg-scroll" ref={notifScrollRef}>
                  <div className="msg-fon">
                    <div className="msg-inner">
                      {groupedNotifs.length === 0 && <div className="msg-main-empty msg-main-empty--inline">Пока нет уведомлений</div>}
                      {groupedNotifs.map((row) =>
                        row.kind === 'date' ? (
                          <div key={row.key} className="msg-date-sep">
                            {row.label}
                          </div>
                        ) : (
                          <div key={row.key} className={`msg-row msg-row--notif ${row.n.read ? 'msg-row--notif-read' : ''}`}>
                            <div className="msg-bubble-avatar msg-bubble-avatar--notif" aria-hidden>
                              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path
                                  d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6V11c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"
                                  fill="currentColor"
                                />
                              </svg>
                            </div>
                            <div
                              role="button"
                              tabIndex={0}
                              className="msg-bubble msg-bubble--notif"
                              onClick={() => !row.n.read && markNotifReadOne(row.n.id)}
                              onKeyDown={(e) => {
                                if ((e.key === 'Enter' || e.key === ' ') && !row.n.read) markNotifReadOne(row.n.id);
                              }}
                            >
                              <div className="msg-bubble-text msg-bubble-text--notif">
                                <strong>{row.n.title}</strong>
                                <div>{row.n.body}</div>
                              </div>
                              <div className="msg-bubble-meta">
                                <span className="msg-bubble-time">{timeInBubble(row.n.created_at)}</span>
                                {row.n.action_url && (
                                  <Link href={row.n.action_url} className="msg-notif-link" onClick={(e) => e.stopPropagation()}>
                                    Открыть
                                  </Link>
                                )}
                              </div>
                            </div>
                          </div>
                        ),
                      )}
                    </div>
                  </div>
                </div>

                <div className="msg-composer msg-composer--disabled">
                  <span className="msg-composer-hint">Ответить на уведомления нельзя — это информационная лента.</span>
                </div>
              </>
            )}

            {activeId && active && !showNotifView && (
              <>
                <div className="msg-topbar">
                  <div className="msg-topbar-left">
                    <button type="button" className="msg-mobile-back" onClick={goBackToList}>
                      ← Назад
                    </button>
                    <ProfileAvatarWrap
                      kind={headKind}
                      userId={headUserId}
                      profileDisabled={embed}
                      className="msg-thread-avatar"
                      style={{ width: 36, height: 36, borderRadius: 8 }}
                    >
                      {active.avatar_url ? (
                        <img src={active.avatar_url} alt="" style={{ width: '100%', height: '100%', borderRadius: 8, objectFit: 'cover' }} />
                      ) : (
                        (active.title || '?').slice(0, 1).toUpperCase()
                      )}
                    </ProfileAvatarWrap>
                    {titleClickable ? (
                      <button type="button" className="msg-topbar-title msg-topbar-title--btn" onClick={() => goProfile(headKind, headUserId)}>
                        {active.title}
                      </button>
                    ) : (
                      <div className="msg-topbar-title">{active.title}</div>
                    )}
                  </div>
                  <div className="msg-topbar-actions">
                    {supportUnassigned && (
                      <button type="button" className="msg-btn-take" onClick={takeSupportChat}>
                        Взять в работу
                      </button>
                    )}
                    {supportTransferred && (
                      <button type="button" className="msg-btn-take msg-btn-take--reclaim" onClick={takeSupportChat}>
                        Забрать обратно
                      </button>
                    )}
                    {supportAssignedToMe && staffForTransfer.length > 0 && (
                      <div className="msg-transfer-wrap">
                        <select
                          className="msg-transfer-select"
                          value={transferStaffId}
                          onChange={(e) => setTransferStaffId(e.target.value)}
                          aria-label="Передать другому сотруднику"
                        >
                          <option value="">Передать…</option>
                          {staffForTransfer.map((s) => (
                            <option key={s.id} value={s.id}>
                              {s.name || `#${s.id}`}
                              {s.role === 'admin' ? ' (админ)' : ' (мод.)'}
                            </option>
                          ))}
                        </select>
                        <button
                          type="button"
                          className="msg-btn-transfer"
                          disabled={!transferStaffId}
                          onClick={transferSupportChat}
                        >
                          Передать
                        </button>
                      </div>
                    )}
                    <button type="button" className="msg-btn-delete" onClick={hideChat}>
                      {isAdminSupport ? 'Убрать из очереди' : 'Удалить чат'}
                    </button>
                  </div>
                </div>

                {supportUnassigned && (
                  <div className="msg-support-hint">
                    Обращение в общей очереди. Нажмите «Взять в работу», чтобы отвечать клиенту.
                  </div>
                )}
                {supportTransferred && (
                  <div className="msg-support-hint msg-support-hint--readonly">
                    Чат передан {active.assigned_staff_name ? `сотруднику ${active.assigned_staff_name}` : 'другому сотруднику'}.
                    История доступна для просмотра. Чтобы снова отвечать — «Забрать обратно» или попросите передать вам.
                  </div>
                )}

                <div className="msg-scroll" ref={scrollRef}>
                  <div className="msg-fon">
                    <div className="msg-inner">
                      {grouped.map((row) =>
                        row.kind === 'date' ? (
                          <div key={row.key} className="msg-date-sep">
                            {row.label}
                          </div>
                        ) : row.m.is_own ? (
                          <div key={row.key} className="msg-row msg-row--own">
                            <ProfileAvatarWrap
                              kind={row.m.sender_profile_kind}
                              userId={row.m.sender_profile_user_id}
                              profileDisabled={embed}
                              className="msg-bubble-avatar"
                            >
                              {row.m.sender_avatar ? (
                                <img src={row.m.sender_avatar} alt="" style={{ width: '100%', height: '100%', borderRadius: 8, objectFit: 'cover' }} />
                              ) : (
                                (row.m.sender_name || '?').slice(0, 1).toUpperCase()
                              )}
                            </ProfileAvatarWrap>
                            <div className={`msg-bubble ${editingId === row.m.id ? 'msg-bubble--editing' : ''}`}>
                              {editingId === row.m.id ? (
                                <div className="msg-edit-box">
                                  {row.m.attachment_url && row.m.is_attachment_image && (
                                    <a href={row.m.attachment_url} target="_blank" rel="noopener noreferrer" className="msg-attachment-img-wrap">
                                      <img src={row.m.attachment_url} alt="" className="msg-attachment-img msg-attachment-img--edit" />
                                    </a>
                                  )}
                                  {row.m.attachment_url && !row.m.is_attachment_image && (
                                    <div className="msg-attachment-file msg-attachment-file--static">
                                      <span className="msg-attachment-file-icon" aria-hidden>
                                        {(row.m.attachment_mime || '').includes('pdf') ? '📄' : '📎'}
                                      </span>
                                      <span className="msg-attachment-file-name">{row.m.attachment_name || 'Вложение'}</span>
                                    </div>
                                  )}
                                  <textarea
                                    className="msg-edit-textarea"
                                    value={editDraft}
                                    onChange={(e) => setEditDraft(e.target.value)}
                                    rows={8}
                                    disabled={editBusy}
                                    placeholder={row.m.attachment_url ? 'Подпись к файлу (необязательно)' : ''}
                                  />
                                  {editError && <div className="msg-edit-error">{editError}</div>}
                                  <div className="msg-edit-actions">
                                    <button type="button" className="msg-btn-ghost msg-btn-ghost--compact" onClick={cancelEdit} disabled={editBusy}>
                                      Отмена
                                    </button>
                                    <button
                                      type="button"
                                      className="msg-composer-send msg-composer-send--compact"
                                      onClick={saveEdit}
                                      disabled={editBusy || (!editDraft.trim() && !row.m.attachment_url)}
                                    >
                                      Сохранить
                                    </button>
                                  </div>
                                </div>
                              ) : (
                                <>
                                  {showSenderNames && <MessageSenderLabel message={row.m} profileDisabled={embed} />}
                                  <MessageBubbleContent message={row.m} />
                                  <MessageBubbleMeta message={row.m} />
                                </>
                              )}
                            </div>
                            {messageCanMutate(row.m.created_at) && editingId !== row.m.id && (
                              <div className="msg-row-tools">
                                <button type="button" className="msg-tool-btn" title="Редактировать" aria-label="Редактировать" onClick={() => startEdit(row.m)}>
                                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
                                    <path
                                      d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"
                                      fill="currentColor"
                                    />
                                  </svg>
                                </button>
                                <button
                                  type="button"
                                  className="msg-tool-btn msg-tool-btn--danger"
                                  title="Удалить"
                                  aria-label="Удалить"
                                  onClick={() => handleDeleteMessage(row.m.id)}
                                >
                                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
                                    <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z" fill="currentColor" />
                                  </svg>
                                </button>
                              </div>
                            )}
                          </div>
                        ) : (
                          <div key={row.key} className="msg-row">
                            <ProfileAvatarWrap
                              kind={row.m.sender_profile_kind}
                              userId={row.m.sender_profile_user_id}
                              profileDisabled={embed}
                              className="msg-bubble-avatar"
                            >
                              {row.m.sender_avatar ? (
                                <img src={row.m.sender_avatar} alt="" style={{ width: '100%', height: '100%', borderRadius: 8, objectFit: 'cover' }} />
                              ) : (
                                (row.m.sender_name || '?').slice(0, 1).toUpperCase()
                              )}
                            </ProfileAvatarWrap>
                            <div className="msg-bubble">
                              {showSenderNames && <MessageSenderLabel message={row.m} profileDisabled={embed} />}
                              <MessageBubbleContent message={row.m} />
                              <MessageBubbleMeta message={row.m} />
                            </div>
                          </div>
                        ),
                      )}
                    </div>
                  </div>
                </div>

                {staffSupportActive && !canReplySupport ? (
                  <div className="msg-composer msg-composer--disabled">
                    <span className="msg-composer-hint">
                      {supportTransferred
                        ? 'Только просмотр. Нажмите «Забрать обратно», чтобы снова отвечать.'
                        : 'Возьмите обращение в работу, чтобы ответить клиенту.'}
                    </span>
                  </div>
                ) : (
                <form className={`msg-composer${pendingFile ? ' msg-composer--with-attach' : ''}`} onSubmit={submitMessage}>
                  {pendingFile && (
                    <div className="msg-pending-attach">
                      {pendingPreviewUrl && <img className="msg-pending-attach-thumb" src={pendingPreviewUrl} alt="" />}
                      <span className="msg-pending-attach-name">{pendingFile.name}</span>
                      <button type="button" className="msg-pending-attach-clear" onClick={() => setPendingFile(null)} aria-label="Убрать файл">
                        ×
                      </button>
                    </div>
                  )}
                  <div className="msg-composer-row">
                    <input
                      ref={fileInputRef}
                      type="file"
                      className="msg-composer-file-input"
                      accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,image/jpeg,image/png,image/gif,image/webp,application/pdf"
                      onChange={onPickAttachment}
                    />
                    {active?.can_attach_files ? (
                      <button type="button" className="msg-composer-clip" title="Прикрепить файл" onClick={() => fileInputRef.current?.click()}>
                        📎
                      </button>
                    ) : (
                      <button
                        type="button"
                        className="msg-composer-clip msg-composer-clip--disabled"
                        title="Файл можно отправить после ответа собеседника"
                        disabled
                      >
                        📎
                      </button>
                    )}
                    <input
                      className="msg-composer-input"
                      placeholder={pendingFile ? 'Подпись к файлу (необязательно)…' : 'Введите сообщение…'}
                      value={form.data.message}
                      onChange={(e) => form.setData('message', e.target.value)}
                    />
                    <button type="submit" className="msg-composer-send" disabled={sendBusy || (!form.data.message.trim() && !pendingFile)}>
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden>
                        <path
                          d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"
                          stroke="currentColor"
                          strokeWidth="2"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                        />
                      </svg>
                    </button>
                  </div>
                </form>
                )}
                {(sendError || form.errors.message) && (
                  <div style={{ color: '#b91c1c', fontSize: 12, padding: '0 1rem 0.5rem' }}>{sendError || form.errors.message}</div>
                )}
              </>
            )}
          </section>
        </div>
      </div>
    </Shell>
  );
}
