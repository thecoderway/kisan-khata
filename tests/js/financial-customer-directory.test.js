import test from 'node:test';
import assert from 'node:assert/strict';
import { customerDisplayLabels, fetchFinancialCustomers } from '../../resources/js/financial-customer-directory.js';

test('transaction selector fetches only active customers', async () => {
    const calls = [];
    const request = async (path) => {
        calls.push(path);
        const params = new URLSearchParams(path.split('?')[1]);
        assert.equal(params.get('status'), 'active');
        return { data: [{ id: 1, name_original: 'Asha', status: 'active' }], meta: { last_page: 1 } };
    };

    const customers = await fetchFinancialCustomers(request, 'transaction');

    assert.deepEqual(customers.map((customer) => customer.id), [1]);
    assert.equal(calls.length, 1);
});

test('historical selector follows all pages from financial-history directory', async () => {
    const calls = [];
    const request = async (path) => {
        calls.push(path);
        const params = new URLSearchParams(path.split('?')[1]);
        assert.equal(params.get('status'), 'financial-history');
        const page = Number(params.get('page'));
        if (page === 1) {
            return { data: [{ id: 1, name_original: 'Asha', status: 'active' }], meta: { last_page: 2 } };
        }
        return { data: [{ id: 2, name_original: 'Bharat', status: 'inactive' }], meta: { last_page: 2 } };
    };

    const customers = await fetchFinancialCustomers(request, 'history');

    assert.deepEqual(customers.map((customer) => customer.id), [1, 2]);
    assert.equal(calls.length, 2);
    assert.ok(calls.every((path) => path.includes('pageSize=100')));
});

test('policy selector fetches all non-deleted customers', async () => {
    let called = '';
    const customers = await fetchFinancialCustomers(async (path) => {
        called = path;
        return { data: [{ id: 1, status: 'active' }, { id: 2, status: 'inactive' }], meta: { last_page: 1 } };
    }, 'policy');

    assert.match(called, /status=all/);
    assert.deepEqual(customers.map((customer) => customer.id), [1, 2]);
});

test('rejects unknown selector modes instead of silently broadening visibility', async () => {
    await assert.rejects(
        () => fetchFinancialCustomers(async () => ({ data: [] }), 'deleted'),
        /Unsupported financial customer directory mode/
    );
});

test('disambiguates duplicate customer names and labels historical lifecycle state', () => {
    const customers = [
        { id: 1, name_original: 'Ramlal', mobile: '9000000001', status: 'active' },
        { id: 2, name_original: 'Ramlal', mobile: '9000000002', status: 'inactive' },
        { id: 3, name_original: 'Sita', mobile: null, status: 'active' },
        { id: 4, name_original: 'Mohan', mobile: '', status: 'inactive' },
        { id: 5, name_original: 'Mohan', mobile: '', status: 'inactive', deleted_at: '2026-01-01T00:00:00Z' },
    ];

    assert.deepEqual(customerDisplayLabels(customers), [
        'Ramlal · 9000000001',
        'Ramlal · 9000000002',
        'Sita',
        'Mohan · #4',
        'Mohan · #5',
    ]);
    assert.deepEqual(customerDisplayLabels(customers, { includeStatus: true }), [
        'Ramlal · 9000000001',
        'Ramlal · 9000000002 (निष्क्रिय)',
        'Sita',
        'Mohan · #4 (निष्क्रिय)',
        'Mohan · #5 (हटाया गया)',
    ]);
});
