import { fetchAllReportPages } from './financial-report-print.js';

const definitions = {
    outstanding: {
        title: 'ग्राहक बकाया रिपोर्ट',
        columns: ['ग्राहक', 'स्थिति', 'Principal', 'Interest', 'कुल'],
        values: (row) => [row.name_hi || row.name_original || row.name_en, row.deleted_at ? 'हटाया गया' : row.status, row.principal, row.interest, row.total],
    },
    sales: {
        title: 'बिक्री रजिस्टर',
        columns: ['बिक्री नंबर', 'तारीख', 'ग्राहक', 'स्थिति', 'राशि'],
        values: (row) => [row.sale_number, row.sale_date, row.customer_name_hi || row.customer_name || row.customer_name_en, row.payment_status, row.amount],
    },
    payments: {
        title: 'जमा रजिस्टर',
        columns: ['जमा नंबर', 'तारीख', 'ग्राहक', 'प्रकार', 'माध्यम', 'राशि', 'लिंक'],
        values: (row) => [row.payment_number, row.payment_date, row.customer_name_hi || row.customer_name || row.customer_name_en, row.payment_type, row.payment_method, row.amount, row.reversed_payment_number || row.replacement_for_payment_number],
    },
    interest: {
        title: 'ब्याज रिपोर्ट',
        columns: ['ग्राहक', 'बिक्री', 'Trigger', 'Run', 'Cutoff (exclusive)', 'Interval start', 'Interval end (exclusive)', 'Days', 'Grace days', 'Rate %', 'Principal basis', 'Unrounded interest', 'Posted interest', 'Capitalized', 'Shop policy version', 'Customer policy version', 'Ledger ID'],
        values: (row) => [
            row.customer_name_hi || row.customer_name || row.customer_name_en, row.sale_number, row.trigger_type,
            row.run_status, row.cutoff_date_exclusive, row.interval_start_date, row.interval_end_date_exclusive,
            row.actual_days, row.grace_days_applied, row.monthly_rate_percent, row.eligible_principal_basis,
            row.unrounded_interest, row.posted_interest, row.capitalization_enabled, row.shop_policy_version_id,
            row.customer_policy_version_id, row.ledger_entry_id,
        ],
    },
};

const csvCell = (value) => {
    let text = String(value ?? '');
    const numeric = /^-?(?:0|[1-9]\d*)(?:\.\d+)?$/.test(text);
    if (!numeric && /^[=+@\-\t\r]/.test(text)) text = "'" + text;
    return '"' + text.replace(/"/g, '""') + '"';
};

export const buildFinancialReportCsv = ({ report, rows, summary, filters, shopName = 'Kisan Khata', generatedAt = new Date() }) => {
    const definition = definitions[report];
    if (!definition) throw new Error('चुनी गई रिपोर्ट export के लिए उपलब्ध नहीं है।');

    const records = [
        ['दुकान', shopName],
        ['रिपोर्ट', definition.title],
        ['तैयार किया गया', generatedAt.toISOString()],
        ['कुल रिकॉर्ड', rows.length],
        ...Object.entries(filters || {}).map(([key, value]) => ['फ़िल्टर: ' + key, value]),
        ...Object.entries(summary || {}).map(([key, value]) => ['सारांश: ' + key, value]),
        [],
        definition.columns,
        ...rows.map(definition.values),
    ];

    return '\uFEFF' + records.map((record) => record.map(csvCell).join(',')).join('\r\n') + '\r\n';
};

const readFilters = (report) => {
    const value = (id) => document.getElementById(id)?.value?.trim() || '';
    const filters = report === 'outstanding'
        ? { as_of: value('report-as-of') }
        : { from: value('report-from'), to: value('report-to') };

    filters.customer_id = value('report-customer');
    if (report !== 'outstanding') filters.q = value('report-search');
    if (report === 'sales') filters.payment_status = value('report-status');
    if (report === 'payments') {
        filters.payment_method = value('report-method');
        filters.payment_type = value('report-payment-type');
    }

    return Object.fromEntries(Object.entries(filters).filter(([, item]) => item !== ''));
};

const filterLabels = (filters) => {
    const labels = { ...filters };
    const customer = document.getElementById('report-customer');
    if (filters.customer_id && customer?.selectedOptions?.[0]) labels.customer = customer.selectedOptions[0].textContent.trim();
    delete labels.customer_id;
    return labels;
};

const filenameDate = (date) => date.toISOString().slice(0, 10);

export const initialiseFinancialReportExport = () => {
    const button = document.getElementById('report-export');
    if (!button) return;

    button.addEventListener('click', async () => {
        const report = document.getElementById('report-type').value;
        const filters = readFilters(report);
        const feedback = document.getElementById('report-feedback');
        button.disabled = true;

        try {
            if (report === 'outstanding' && !filters.as_of) throw new Error('कृपया As-of तारीख चुनें।');
            if (report === 'interest' && button.dataset.userRole !== 'owner') throw new Error('ब्याज रिपोर्ट केवल Owner के लिए उपलब्ध है।');

            const { rows, summary } = await fetchAllReportPages((url) => fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            }), button.dataset.api + '/' + report, filters);

            const csv = buildFinancialReportCsv({
                report, rows, summary, filters: filterLabels(filters),
                shopName: button.dataset.shopName || 'Kisan Khata',
            });
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'kisan-khata-' + report + '-' + filenameDate(new Date()) + '.csv';
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
            feedback.textContent = '';
        } catch (error) {
            feedback.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });
};
