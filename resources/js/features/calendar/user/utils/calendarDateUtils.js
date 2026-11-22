import { localDateForUtcDay, utcDateKey } from '../../../../shared/utils/dateOnly.js';

const parse = value => {
    if (value instanceof Date) return new Date(value.getFullYear(), value.getMonth(), value.getDate());
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [year, month, day] = value.split('-').map(Number);
        return new Date(year, month - 1, day);
    }
    const date = new Date(value);
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
};

export const dateKey = value => {
    const date = parse(value);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};
export const addDays = (value, amount) => { const date = parse(value); date.setDate(date.getDate() + amount); return date; };
export const addMonths = (value, amount) => {
    const date = parse(value);
    const targetMonth = new Date(date.getFullYear(), date.getMonth() + amount, 1);
    const lastDay = new Date(targetMonth.getFullYear(), targetMonth.getMonth() + 1, 0).getDate();
    return new Date(targetMonth.getFullYear(), targetMonth.getMonth(), Math.min(date.getDate(), lastDay));
};
export const mondayStart = value => { const date = parse(value); date.setDate(date.getDate() - ((date.getDay() + 6) % 7)); return date; };
export const monthGrid = value => {
    const date = parse(value), start = mondayStart(new Date(date.getFullYear(), date.getMonth(), 1));
    return Array.from({ length: 42 }, (_, index) => addDays(start, index));
};
export const formatDate = (value, options = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) =>
    parse(value).toLocaleDateString(undefined, options);
export const isSameDay = (a, b) => dateKey(a) === dateKey(b);
export const todayKey = () => utcDateKey();
export const todayDate = () => localDateForUtcDay();
