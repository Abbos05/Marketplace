export function formatRub(value, { fractionDigits = 0 } = {}) {
    return (
        Number(value).toLocaleString('ru-RU', { maximumFractionDigits: fractionDigits }) + ' ₽'
    );
}

export function formatInt(value) {
    return Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 0 });
}
