import test from 'node:test';
import assert from 'node:assert/strict';
import { customerDisplayLabels, fetchFinancialCustomers } from '../../resources/js/financial-customer-directory.js';

test('fetches active and inactive customers and follows every result page', async () => {
    const calls = [];
    const request = async (path) => {
        calls.push(path);
        const params = new URLSearchParams(path.split('?')[1]);
        const status = params.get('status');
        const page = Number(params.get('page'));

        if (status === 'active' && page === 1) {
            return { data: [{ id: 1, name_original: 'Asha' }], meta: { last_page: 2 } };
        }
        if (status === 'active' && page === 2) {
            return { data: [{ id: 2, name_original: 'Bharat' }], meta: { last_page: 2 } };
        }
        if (status === 'inactive' && page === 1) {
            return { data: [{ id: 3, name_original: 'Champa' }], meta: { last_page: 1 } };
        }

        throw new Error('Unexpected customer request: ' + path);
    };

    const customers = await fetchFinancialCustomers(request);

    assert.deepEqual(customers.map((customer) => customer.id), [1, 2, 3]);
    assert.equal(calls.length, 3);
    assert.ok(calls.every((path) => path.includes('pageSize=100')));
});

test('disambiguates duplicate customer names without changing unique labels', () => {
    const customers = [
        { id: 1, name_original: 'Ramlal', mobile: '9000000001' },
        { id: 2, name_original: 'Ramlal', mobile: '9000000002' },
        { id: 3, name_original: 'Sita', mobile: null },
        { id: 4, name_original: 'Mohan', mobile: '' },
        { id: 5, name_original: 'Mohan', mobile: '' },
    ];

    assert.deepEqual(customerDisplayLabels(customers), [
        'Ramlal · 9000000001',
        'Ramlal · 9000000002',
        'Sita',
        'Mohan · #4',
        'Mohan · #5',
    ]);
});
