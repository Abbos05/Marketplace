/**
 * Собирает подсказки для главной профиля — по приоритету, выполненные не показываются.
 */

export function buildProfileHomeActions(ctx) {
  const actions = [];
  const {
    needsProfileVerification,
    isUserBlocked,
    isStaffUser,
    isPvzUser,
    sellerProfile,
    sellerRestorePending,
    closedSellerProfile,
    pvzAccess,
    auth,
    orders = [],
  } = ctx;

  if (isUserBlocked) return actions;

  if (needsProfileVerification) {
    actions.push({
      id: 'complete-profile',
      priority: 100,
      tone: 'accent',
      icon: '👤',
      title: 'Заполните профиль',
      text: 'Укажите имя, email и подтвердите телефон — без этого нельзя оформить заказ, открыть компанию или подать заявку на ПВЗ.',
      cta: 'Заполнить профиль',
      action: { type: 'phone-modal' },
    });
  }

  const readyOrder = orders.find((o) => o.frontend_status === 'ready');
  if (readyOrder) {
    actions.push({
      id: `order-ready-${readyOrder.id}`,
      priority: 95,
      tone: 'success',
      icon: '📦',
      title: 'Заказ можно забрать',
      text: `Заказ №${readyOrder.number} уже в пункте выдачи. Покажите код при получении.`,
      cta: 'Перейти к заказу',
      action: { type: 'order', orderId: readyOrder.id },
      meta: readyOrder,
    });
  }

  const shippingOrder = orders.find((o) => o.frontend_status === 'shipping');
  if (shippingOrder) {
    actions.push({
      id: `order-shipping-${shippingOrder.id}`,
      priority: 88,
      tone: 'info',
      icon: '🚚',
      title: 'Заказ в пути',
      text: `Заказ №${shippingOrder.number} доставляется в пункт выдачи. Статус можно отслеживать в разделе заказов.`,
      cta: 'Смотреть заказ',
      action: { type: 'order', orderId: shippingOrder.id },
      meta: shippingOrder,
    });
  }

  const pendingOrder = orders.find((o) => o.frontend_status === 'pending');
  if (pendingOrder && !readyOrder && !shippingOrder) {
    actions.push({
      id: `order-pending-${pendingOrder.id}`,
      priority: 82,
      tone: 'info',
      icon: '🛒',
      title: 'Заказ оформлен',
      text: `Заказ №${pendingOrder.number} принят в обработку. Мы сообщим, когда его можно будет забрать.`,
      cta: 'Подробнее',
      action: { type: 'order', orderId: pendingOrder.id },
    });
  }

  if (!auth.user.default_pickup_point_id) {
    actions.push({
      id: 'pickup-point',
      priority: 75,
      tone: 'neutral',
      icon: '📍',
      title: 'Укажите пункт выдачи',
      text: 'Выберите ПВЗ по умолчанию — так оформление заказа займёт меньше времени. Пункт можно сменить при каждой покупке.',
      cta: 'Выбрать пункт',
      action: { type: 'tab', tab: 'pickup' },
    });
  }

  if (auth.user.newPassw && !isStaffUser) {
    actions.push({
      id: 'security-2fa',
      priority: 70,
      tone: 'neutral',
      icon: '🔒',
      title: 'Включите двухэтапную защиту',
      text: 'Установите пароль — после входа по SMS потребуется ещё и пароль. Это защитит аккаунт от посторонних.',
      cta: 'Настроить безопасность',
      action: { type: 'settings', section: 'security' },
    });
  }

  if (!isStaffUser && !isPvzUser && !sellerProfile && !sellerRestorePending && !closedSellerProfile) {
    actions.push({
      id: 'open-company',
      priority: 55,
      tone: 'neutral',
      icon: '🏢',
      title: 'Откройте компанию продавца',
      text: 'Зарегистрируйте магазин на маркетплейсе — добавляйте товары, принимайте заказы и управляйте продажами из панели продавца.',
      cta: 'Открыть компанию',
      action: { type: 'tab', tab: 'company' },
    });
  }

  if (!isStaffUser && !pvzAccess?.isPvz && !isPvzUser) {
    actions.push({
      id: 'pvz-partner',
      priority: 45,
      tone: 'neutral',
      icon: '🤝',
      title: 'Станьте партнёром ПВЗ',
      text: 'Откройте пункт выдачи ALVORA — принимайте заказы покупателей и получайте вознаграждение за выдачу.',
      cta: 'Узнать условия',
      action: { type: 'tab', tab: 'pvz' },
    });
  }

  return actions.sort((a, b) => b.priority - a.priority);
}
