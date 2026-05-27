export const isStaff = (user) => ['admin', 'moderator'].includes(user?.role);

export const canAssignStaffRoles = (user) => user?.role === 'admin';

export const isPrimaryAdmin = (staffAccess) => staffAccess?.isPrimaryAdmin === true;

export const isStaffAccount = (user) => isStaff(user);

export const ROLE_OPTIONS_ALL = [
    { value: 'user', label: 'Пользователь' },
    { value: 'seller', label: 'Продавец' },
    { value: 'pvz', label: 'Оператор ПВЗ' },
    { value: 'moderator', label: 'Модератор' },
    { value: 'admin', label: 'Админ' },
];

export const ROLE_OPTIONS_MODERATOR = [
    { value: 'user', label: 'Пользователь' },
    { value: 'seller', label: 'Продавец' },
];

export const roleOptionsFor = (actor) =>
    canAssignStaffRoles(actor) ? ROLE_OPTIONS_ALL : ROLE_OPTIONS_MODERATOR;

/** Роли, которые можно назначить конкретному пользователю (с бэкенда). */
export const roleOptionsForTarget = (actor, targetUser) => {
    const allowed = targetUser?.assignable_roles;
    const all = roleOptionsFor(actor);
    if (!allowed?.length) {
        return all;
    }
    const opts = all.filter((opt) => allowed.includes(opt.value));
    if (targetUser?.role && !opts.some((o) => o.value === targetUser.role)) {
        const current = all.find((o) => o.value === targetUser.role);
        if (current) {
            opts.unshift(current);
        }
    }
    return opts;
};

export const canManageUserAsStaff = (actor, targetUser) => {
    if (!targetUser) return false;
    if (targetUser.id === 1 || targetUser.id === actor?.id) return false;
    if (canAssignStaffRoles(actor)) return true;
    return !isStaffAccount(targetUser);
};
