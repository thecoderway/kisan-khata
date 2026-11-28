import test from 'node:test';
import assert from 'node:assert/strict';
import { buildFinancialReportCsv } from './financial-report-export.js';

test('exports metadata, filters, summary and all rows with UTF-8 BOM', () => {
    const csv = buildFinancialReportCsv({
        report: 'sales',
        shopName: 'Test Shop',
        generatedAt: new Date('2026-11-28T10:00:00.000Z'),
        filters: { from: '2026-11-01', customer: 'राम' },
        summary: { sale_count: 2, gross_sales: '2500.00' },
        rows: [
            { sale_number: 'S-1', sale_date: '2026-11-01', customer_name_hi: 'राम', payment_status: 'UNPAID', amount: '1000.00' },
            { sale_number: 'S-2', sale_date: '2026-11-02', customer_name_hi: 'श्याम', payment_status: 'PAID', amount: '1500.00' },
        ],
    });

    assert.ok(csv.startsWith('\uFEFF'));
    assert.match(csv, /Test Shop/);
    assert.match(csv, /सारांश: gross_sales/);
    assert.match(csv, /S-1/);
    assert.match(csv, /S-2/);
    assert.match(csv, /2500\.00/);
});

test('quotes CSV fields and neutralizes formula-like untrusted text', () => {
    const csv = buildFinancialReportCsv({
        report: 'sales',
        rows: [{ sale_number: '=HYPERLINK("https://evil.test")', customer_name: 'राम, "जी"', amount: '12.00' }],
        summary: {},
        filters: {},
    });

    assert.match(csv, /"'=HYPERLINK\(""https:\/\/evil\.test""\)"/);
    assert.match(csv, /"राम, ""जी"""/);
});

test('exports all interest audit fields and rejects unknown report types', () => {
    const csv = buildFinancialReportCsv({
        report: 'interest',
        rows: [{
            sale_number: 'S-1', trigger_type: 'SCHEDULED', run_status: 'COMPLETED',
            cutoff_date_exclusive: '2026-11-01', interval_start_date: '2026-10-01',
            interval_end_date_exclusive: '2026-11-01', actual_days: 31, grace_days_applied: 30,
            monthly_rate_percent: '1.50', eligible_principal_basis: '1000.00',
            unrounded_interest: '15.5000', posted_interest: '15.50', capitalization_enabled: 0,
            shop_policy_version_id: 2, customer_policy_version_id: 3, ledger_entry_id: 99,
        }],
        summary: {},
        filters: {},
    });

    for (const field of ['छूट अवधि (दिन)', 'बिना पूर्णांक की राशि लेट फीस', 'दुकान नीति संस्करण', 'ग्राहक नीति संस्करण', '99']) {
        assert.ok(csv.includes(field));
    }
    assert.doesNotMatch(csv, /Late Fee|Interest|ब्याज/);
    assert.throws(() => buildFinancialReportCsv({ report: 'unknown', rows: [], summary: {}, filters: {} }));
});

test('neutralizes spreadsheet formulas after leading whitespace and control characters while preserving negative decimals', () => {
    const values = [' =1+1', '\t=1+1', '\n@SUM(A1:A2)', '\r-1+2'];
    const csv = buildFinancialReportCsv({
        report: 'sales',
        rows: values.map((sale_number) => ({ sale_number, amount: '-123.45' })),
        summary: {},
        filters: {},
    });

    for (const value of values) {
        assert.ok(csv.includes('"' + "'" + value + '"'));
    }
    assert.ok(csv.includes('"-123.45"'));
});
