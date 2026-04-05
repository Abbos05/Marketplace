import { useState, useEffect } from 'react';
import { useForm, Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { router, Link, usePage } from '@inertiajs/react';
import '../../../css/profile/admin.css';


// Функция определения браузера
const getBrowserName = (ua) => {
  if (!ua) return 'Неизвестно';
  if (ua.includes('Edg')) return 'Edge';
  if (ua.includes('Chrome')) return 'Chrome';
  if (ua.includes('Firefox')) return 'Firefox';
  if (ua.includes('Safari') && !ua.includes('Chrome')) return 'Safari';
  return 'Другой';
};

export default function AdminIndex({ filter, search = '', }) {

  // Внутри компонента:
  const [now] = useState(Date.now());


  const { props } = usePage();
  const users = props.users || [];
  const sessions = props.sessions || [];
  const currentSessionId = props.currentSessionId;

  // онлай или нет
  const MINUTES_ONLINE = 1;
  const isUserOnline = (sessions) => {
    if (!sessions?.length) return false;
    const latest = Math.max(...sessions.map(s => new Date(s.last_activity).getTime()));
    return (now - latest) < MINUTES_ONLINE * 60 * 1000;
  };

  // // онлай или нет
  // === Группировка сессий: по пользователю → по уникальному устройству (IP + User-Agent) ===
  const sessionsByUserId = {};

  sessions.forEach((session) => {
    const key = `${session.ip_address}-${session.user_agent || 'unknown'}`;
    if (!sessionsByUserId[session.user_id]) {
      sessionsByUserId[session.user_id] = {};
    }

    const existing = sessionsByUserId[session.user_id][key];
    // Оставляем только самую свежую сессию для этого устройства
    if (!existing || session.last_activity > existing.last_activity) {
      sessionsByUserId[session.user_id][key] = session;
    }
  });

  // Преобразуем в массив сессий на пользователя
  const finalSessionsByUserId = {};
  for (const [userId, deviceMap] of Object.entries(sessionsByUserId)) {
    finalSessionsByUserId[userId] = Object.values(deviceMap);
  }

  // === Функции действий ===
  const kickSession = (sessionId) => {
    if (confirm('Завершить сессию пользователя?')) {
      router.delete(route('admin.sessions.destroy', sessionId), {
        preserveScroll: true,
        onSuccess: () => console.log('Сессия завершена'),
      });
    }
  };

  const deleteUser = (userId) => {
    if (confirm('Вы уверены, что хотите удалить этого пользователя?')) {
      router.delete(route('admin.users.destroy', userId), {
        preserveScroll: true,
        onSuccess: () => {
          console.log('Пользователь удален')
        },
        onError: (errors) => {
          console.error('Ошибка при удалении:', errors);
        },
      });
    }
  };


  const toggleBlockUser = (userId) => {
    if (confirm('Вы уверены, что хотите изменить статус блокировки этого пользователя?')) {

      router.put(route('admin.users.block', userId), {
        preserveScroll: true,
        onSuccess: () => {
          console.log('Статус блокировки изменен');
          router.reload({ only: ['users'] }); // Перезагружает users
        },
        onError: (errors) => {
          console.error('Ошибка при изменении статуса:', errors);
        },
      });
    }
  };

  const handleUserClick = (userId) => {
    router.visit(route('admin.users.show', userId));
  };


  return (
    <AuthenticatedLayout>
      <Head title="Управление пользователями" />
      <div className="user__block container" id="user__block">
        <div className="user__title__block">
          <h2 className="user__title" id="user__title">Управление пользователями</h2>
          {search && (
            <div style={{ display: 'flex', gap: 15 }}>
              <span>Результат по поиску: {search}</span>
              <span>—</span>
              <Link href={route('admins')} className="clear-search">Очистить поиск</Link>
              <br />
              <br />
            </div>
          )}
        </div>

        {users.length > 0 ? (
          <div className="user__table">
            <div className="user__table__header">
              <p>Пользователь</p>
              {/* <p>Статус</p>
              <p>Активность</p> */}
              <p>Email</p>
              <p>Активные сессии</p>
              <p>Действия</p>
            </div>

            {users.map((user) => {
              const sessions = finalSessionsByUserId[user.id] || [];
              const online = isUserOnline(sessions);
              return (
                <div
                  key={user.id}
                  className={`user__table__row cursor-pointer hover:bg-gray-50 transition
                  ${user.role !== 'user' ? 'user__table__row--admin' : ''}
                  ${user.is_blocked ? 'user__table__row--block' : ''}
                  ${user.nft.map(nft => (
                    nft.status == 'moderation' && (
                      ' user__table__row--moderation '
                    )
                  ))}
                  ${user.phone ? 'user__table__phone' : ''}`}

                >

                  <div className="user__cell">
                    <img src={user.avatar ? `/${user.avatar}` : "/admin/img/avatar.svg"} alt="avatar" className={`user__avatar ${online ? 'imgOnline' : ''}`} />

                    <span style={{ cursor: 'pointer' }} onClick={() => handleUserClick(user.id)} title={`ID: ${user.id}, Почта: ${user.email}`}>{user.name}</span>
                    {user.phone && (
                      <img title='Пользователь верифицирован' src="/img/profiles/check.png" alt="Коллекция" className="icon" />
                    )}
                  </div>

                  {/* <div className="user__cell">
                    <span>{user.is_blocked ? 'Не активен' : 'Активен'}</span>
                  </div>
                  <div className="user__cell">
                    <span>{online ? 'Онлайн' : 'Неактивен'}</span>
                  </div> */}
                  <div className="user__cell">
                    <span title={user.email}>{user.email || 'Нет почты'}</span>
                  </div>

                  <div className="user__cell">
                    {sessions.length > 0 ? (
                      <ul className="sessions-list">
                        {sessions.map((session) => (
                          <li
                            key={`${session.ip_address}-${session.user_agent || 'unknown'}`}
                            className="session-item"
                          >
                            <span>IP: {session.ip_address}</span>
                            <span>Браузер: {getBrowserName(session.user_agent)}</span>
                            <span>Активность: {new Date(session.last_activity).toLocaleString()}</span>
                            {session.session_id === currentSessionId ? (
                              <span className="current-session-label">Это вы</span>
                            ) : (
                              <button
                                onClick={() => kickSession(session.session_id)}
                                className="kick-button"
                              >
                                Кикнуть
                              </button>
                            )}
                          </li>
                        ))}
                      </ul>
                    ) : (
                      <span>Нет активных сессий</span>
                    )}
                  </div>

                  <div className="user__cell user__actions">
                    <button
                      onClick={() => toggleBlockUser(user.id)}
                      className={`block__button ${user.is_blocked ? 'unblock' : ''}`}
                    >
                      {user.is_blocked ? 'Разблокировать' : 'Заблокировать'}
                    </button>
                    <button onClick={() => deleteUser(user.id)} className="delete__button">
                      Удалить
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        ) : (
          <h2 className="nouser">Нет таких пользователей</h2>
        )}
      </div>
    </AuthenticatedLayout>
  );
}