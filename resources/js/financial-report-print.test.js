import test from 'node:test';
import assert from 'node:assert/strict';
import { buildFinancialReportPrintHtml, fetchAllReportPages } from './financial-report-print.js';

test('fetches every filtered report page and preserves the first-page summary', async () => {
    const requests = [];
    const fetchPage = async (url) => {
        requests.push(new URL(url, 'http://localhost'));
        const page = Number(requests.at(-1).searchParams.get('page'));
        return {
            ok: true,
            json: async () => ({
                data: [{ id: page }],
                summary: { grand_total: '125.00' },
                meta: { last_page: 2 },
            }),
        };
    };

    const result = await fetchAllReportPages(fetchPage, '/api/v1/reports/outstanding', { as_of: '2026-11-28', customer_id: '7' });

    assert.deepEqual(result.rows, [{ id: 1 }, { id: 2 }]);
    assert.deepEqual(result.summary, { grand_total: '125.00' });
    assert.equal(requests.length, 2);
    assert.equal(requests[0].searchParams.get('per_page'), '100');
    assert.equal(requests[1].searchParams.get('page'), '2');
    assert.equal(requests[1].searchParams.get('customer_id'), '7');
    assert.equal(requests[1].searchParams.get('as_of'), '2026-11-28');
});

test('stops and reports a failed page instead of printing incomplete data', async () => {
    let calls = 0;
    const fetchPage = async () => {
        calls += 1;
        return {
            ok: calls === 1,
            json: async () => calls === 1
                ? { data: [{ id: 1 }], meta: { last_page: 2 } }
                : { message: 'Page unavailable' },
        };
    };

    await assert.rejects(
        fetchAllReportPages(fetchPage, '/api/v1/reports/sales', {}),
        /Page unavailable/,
    );
    assert.equal(calls, 2);
});

test('renders all report types with their own columns and escapes untrusted values', () => {
    const cases = [
        ['outstanding', [{ name_hi: '<ग्राहक>', principal: '10.00', interest: '2.00', total: '12.00' }], 'Principal'],
        ['sales', [{ sale_number: 'S-1', customer_name: '<script>alert(1)</script>', amount: '10.00' }], 'बिक्री नंबर'],
        ['payments', [{ payment_number: 'P-1', payment_type: 'NORMAL', amount: '10.00' }], 'जमा नंबर'],
        ['interest', [{ sale_number: 'S-1', posted_interest: '2.00', eligible_principal_basis: '10.00' }], 'Posted interest'],
    ];

    for (const [report, rows, expectedColumn] of cases) {
        const html = buildFinancialReportPrintHtml({
            report,
            rows,
            summary: { grand_total: '12.00', record_count: 1 },
            filters: { customer: '<script>' },
            shopName: 'Test Shop',
        });

        assert.match(html, new RegExp(expectedColumn.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
        assert.match(html, /₹/);
        assert.match(html, /&lt;script&gt;/);
        assert.doesNotMatch(html, /<script>alert\(1\)<\/script>/);
        assert.match(html, /कुल रिकॉर्ड: 1/);
    }
});

test('rejects an unknown report type', () => {
    assert.throws(
        () => buildFinancialReportPrintHtml({ report: 'unknown', rows: [], summary: {}, filters: {} }),
        /प्रिंट के लिए उपलब्ध नहीं/,
    );
});
