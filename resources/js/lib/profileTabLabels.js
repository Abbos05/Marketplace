export const PROFILE_TAB_LABELS = {
  main: 'Главная',
  orders: 'Заказы',
  favorites: 'Избранное',
  reviews: 'Мои отзывы',
  messages: 'Сообщения',
  pickup: 'Пункт выдачи',
  company: 'Компания',
  pvz: 'Партнёрство ПВЗ',
  settings: 'Настройки',
};

export function profileSettingsSectionLabel(section) {
  const map = {
    personal: 'Личные данные',
    contacts: 'Контакты',
    security: 'Безопасность',
    sessions: 'Устройства и входы',
  };
  return map[section] || 'Настройки';
}

export function profileMobileTitle(activeTab, settingsSection) {
  if (activeTab === 'settings' && settingsSection) {
    return profileSettingsSectionLabel(settingsSection);
  }
  return PROFILE_TAB_LABELS[activeTab] || 'Профиль';
}
