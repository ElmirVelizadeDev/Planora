export function utcDateKey(date = new Date()) {
    return [date.getUTCFullYear(), String(date.getUTCMonth() + 1).padStart(2, '0'), String(date.getUTCDate()).padStart(2, '0')].join('-');
}

export function localDateForUtcDay(date = new Date()) {
    const [year, month, day] = utcDateKey(date).split('-').map(Number);
    return new Date(year, month - 1, day);
}
