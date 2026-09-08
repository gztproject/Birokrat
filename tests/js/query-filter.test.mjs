import assert from 'node:assert/strict';
import { isoDateFromUnix, unixEndOfDay, unixStartOfDay, withQuery } from '../../assets/js/common/filters/query.js';

const start = unixStartOfDay('2024-01-01');
const end = unixEndOfDay('2024-12-31');

assert.equal(isoDateFromUnix(String(start)), '2024-01-01');
assert.equal(isoDateFromUnix(String(end)), '2024-12-31');
assert.equal(unixStartOfDay(''), undefined);
assert.equal(unixEndOfDay('not-a-date'), undefined);

const originalOrigin = globalThis.window;
globalThis.window = { location: { origin: 'http://localhost' } };
try {
    assert.equal(
        withQuery('http://localhost/dashboard/report?page=2', { year: '2024', dateFrom: '1', dateTo: '2' }),
        '/dashboard/report?year=2024&dateFrom=1&dateTo=2',
    );
    assert.equal(
        withQuery('http://localhost/dashboard/report?year=2024&dateFrom=1', { year: undefined, dateFrom: '9' }),
        '/dashboard/report?dateFrom=9',
    );
} finally {
    globalThis.window = originalOrigin;
}

console.log('query-filter helpers ok');
