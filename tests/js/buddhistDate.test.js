import test from 'node:test';
import assert from 'node:assert/strict';
import { toBuddhistDate, toIsoDate } from '../../resources/js/utils/buddhistDate.js';

test('round-trip assessment date between Buddhist display and ISO storage', () => {
    assert.equal(toBuddhistDate('2026-09-24'), '24/09/2569');
    assert.equal(toIsoDate('24/09/2569'), '2026-09-24');
    assert.equal(toIsoDate('๒๔/๐๙/๒๕๖๙'), '2026-09-24');
    assert.equal(toIsoDate('1/1/2569'), '2026-01-01');
});

test('rejects Gregorian years and invalid Buddhist dates', () => {
    for (const value of ['24/09/2026', '31/04/2569', '29/02/2569', '24-09-2569', '00/01/2569']) {
        assert.equal(toIsoDate(value), null, value);
    }
    assert.equal(toIsoDate('29/02/2567'), '2024-02-29');
});
