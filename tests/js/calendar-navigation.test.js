import test from 'node:test';
import assert from 'node:assert/strict';
import { addMonths, dateKey, todayKey } from '../../resources/js/features/calendar/user/utils/calendarDateUtils.js';
import { localDateForUtcDay, utcDateKey } from '../../resources/js/shared/utils/dateOnly.js';

const cases = [
    ['March 31 to February', '2025-03-31', -1, '2025-02-28'],
    ['March 30 to February', '2025-03-30', -1, '2025-02-28'],
    ['March 29 to February', '2025-03-29', -1, '2025-02-28'],
    ['January 31 to prior December', '2025-01-31', -1, '2024-12-31'],
    ['January 31 to February', '2025-01-31', 1, '2025-02-28'],
    ['February 29 to March', '2024-02-29', 1, '2024-03-29'],
    ['December 31 to next January', '2025-12-31', 1, '2026-01-31'],
];

for (const [description, selectedDate, direction, expectedDate] of cases) {
    test(`month navigation: ${description}`, () => {
        const result = addMonths(selectedDate, direction);

        assert.equal(dateKey(result), expectedDate);
        assert.equal(result.getMonth(), Number(expectedDate.slice(5, 7)) - 1);
        assert.equal(result.getFullYear(), Number(expectedDate.slice(0, 4)));
    });
}

test('date-only current-day calculations follow the application UTC day', () => {
    const instant = new Date('2026-11-22T01:30:00+04:00');

    assert.equal(utcDateKey(instant), '2026-11-21');
    assert.equal(dateKey(localDateForUtcDay(instant)), '2026-11-21');
    assert.equal(todayKey(), utcDateKey());
});
