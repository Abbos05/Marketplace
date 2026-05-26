export const FOOTER_COLUMNS = {
  buyers: {
    title: 'Покупателям',
    links: [
      { label: 'Каталог', href: '/category' },
      { label: 'Корзина', href: '/cart', auth: true },
      { label: 'Мои заказы', href: '/orders', auth: true },
      { label: 'Избранное', href: '/favorites', auth: true },
      { label: 'Сообщения', href: '/messages', auth: true },
      { label: 'Доставка и оплата', href: '/delivery' },
      { label: 'Возвраты', href: '/returns' },
      { label: 'Помощь', href: '/help' },
    ],
  },
  sellers: {
    title: 'Продавцам',
    links: [
      { label: 'Стать продавцом', href: '/profile?tab=company', auth: true },
      { label: 'Панель продавца', href: '/seller/dashboard', auth: true },
      { label: 'Статистика', href: '/seller/statistics', auth: true },
      { label: 'Настройки магазина', href: '/seller/settings', auth: true },
    ],
  },
  company: {
    title: 'О компании',
    links: [
      { label: 'О маркетплейсе', href: '/about' },
      { label: 'Контакты', href: '/contacts' },
      { label: 'Помощь', href: '/help' },
      { label: 'Партнёрство ПВЗ', href: '/pickup/partner' },
    ],
  },
};

export const FOOTER_LEGAL = [
  { label: 'Политика конфиденциальности', href: '/privacy' },
  { label: 'Пользовательское соглашение', href: '/terms' },
  { label: 'Правила платформы', href: '/terms' },
];
