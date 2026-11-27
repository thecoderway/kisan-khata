import test from 'node:test';
import assert from 'node:assert/strict';
import { fetchAllLedgerPages } from '../../resources/js/financial-ledger-print.js';

test('print loader fetches every ledger page with the same customer and date filters', async () => {
    const calls = [];
    const request = async (path) => {
        calls.push(path);
        const url = new URL(path, 'https://example.test');
        const page = Number(url.searchParams.get('page'));
        assert.equal(url.pathname, '/customers/42/ledger');
        assert.equal(url.searchParams.get('per_page'), '100');
        assert.equal(url.searchParams.get('from'), '2026-11-01');
        assert.equal(url.searchParams.get('to'), '2026-11-30');

        return {
            data: [{ id: page, total_balance: String(page * 100) }],
            meta: { page, per_page: 100, total: 201, last_page: 3, opening_balance: { principal: '0.00', interest: '0.00', total: '0.00' } },
        };
    };

    const result = await fetchAllLedgerPages(request, 42, {
        from: '2026-11-01',
        to: '2026-11-30',
        perPage: 100,
    });

    assert.deepEqual(result.rows.map((row) => row.id), [1, 2, 3]);
    assert.equal(result.pages, 3);
    assert.equal(calls.length, 3);
    assert.equal(new URL(calls[2], 'https://example.test').searchParams.get('page'), '3');
});

test('print loader returns an empty statement after the first page when the ledger has no rows', async () => {
    const calls = [];
    const result = await fetchAllLedgerPages(async (path) => {
        calls.push(path);
        return { data: [], meta: { total: 0, last_page: 1, opening_balance: { principal: '25.00', interest: '5.00', total: '30.00' } } };
    }, '7');

    assert.deepEqual(result.rows, []);
    assert.equal(result.pages, 1);
    assert.equal(calls.length, 1);
    assert.match(calls[0], /^\/customers\/7\/ledger\?/);
});
