export const isStaff = (user) => ['admin', 'moderator'].includes(user?.role);

export const canAssignStaffRoles = (user) => user?.role === 'admin';

export const isStaffAccount = (user) => isStaff(user);

export const ROLE_OPTIONS_ALL = [
    { value: 'user', label: 'Пользователь' },
    { value: 'seller', label: 'Продавец' },
    { value: 'moderator', label: 'Модератор' },
    { value: 'admin', label: 'Админ' },
];

export const ROLE_OPTIONS_MODERATOR = [
    { value: 'user', label: 'Пользователь' },
    { value: 'seller', label: 'Продавец' },
];

export const roleOptionsFor = (actor) =>
    canAssignStaffRoles(actor) ? ROLE_OPTIONS_ALL : ROLE_OPTIONS_MODERATOR;

export const canManageUserAsStaff = (actor, targetUser) => {
    if (!targetUser) return false;
    if (targetUser.id === 1 || targetUser.id === actor?.id) return false;
    if (canAssignStaffRoles(actor)) return true;
    return !isStaffAccount(targetUser);
};
