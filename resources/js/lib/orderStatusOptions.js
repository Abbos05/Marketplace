/** Статусы, которые завершают выдачу в ПВЗ (только оператор ПВЗ или администратор). */
export const PICKUP_FINAL_STATUSES = ['ISSUED', 'REFUSED'];

export const ORDER_STATUSES = [
    { value: 'NEW', label: 'Новый заказ' },
    { value: 'INTRANSIT', label: 'В пути' },
    { value: 'DELIVERED', label: 'В пункте выдачи' },
    { value: 'ISSUED', label: 'Выдан' },
    { value: 'CANCELED', label: 'Отменён' },
    { value: 'REFUSED', label: 'Отказ от получения' },
];

export const ORDER_STATUS_MAP = Object.fromEntries(ORDER_STATUSES.map((s) => [s.value, s.label]));

const UNPAID_ALLOWED_STATUSES = ['NEW', 'INTRANSIT', 'DELIVERED', 'CANCELED', 'REFUSED'];

/**
 * @param {{ status: string, payment_status?: string }} order
 * @param {string} [actorRole] — роль текущего пользователя (admin | moderator | …)
 */
export function orderStatusOptionsFor(order, actorRole = 'admin') {
    let base =
        order.payment_status === 'paid'
            ? ORDER_STATUSES
            : ORDER_STATUSES.filter((s) => UNPAID_ALLOWED_STATUSES.includes(s.value));

    if (actorRole !== 'admin') {
        base = base.filter((s) => !PICKUP_FINAL_STATUSES.includes(s.value));
    }

    if (base.some((s) => s.value === order.status)) {
        return base;
    }

    const current = ORDER_STATUSES.find((s) => s.value === order.status);
    return current ? [{ ...current, disabled: true }, ...base] : base;
}
