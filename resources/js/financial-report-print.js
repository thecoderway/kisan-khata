const reportDefinitions = {
    outstanding: {
        title: 'ग्राहक बकाया रिपोर्ट',
        columns: ['ग्राहक', 'स्थिति', 'मूलधन', 'लेट फीस', 'कुल'],
        values: (row, money) => [row.name_hi || row.name_original || row.name_en, row.deleted_at ? 'हटाया गया' : ({ active: 'सक्रिय', inactive: 'निष्क्रिय' }[row.status] || row.status), money(row.principal), money(row.interest), money(row.total)],
    },
    sales: {
        title: 'बिक्री रजिस्टर',
        columns: ['बिक्री नंबर', 'तारीख', 'ग्राहक', 'स्थिति', 'राशि'],
        values: (row, money) => [row.sale_number, row.sale_date, row.customer_name_hi || row.customer_name || row.customer_name_en, row.payment_status, money(row.amount)],
    },
    payments: {
        title: 'जमा रजिस्टर',
        columns: ['जमा नंबर', 'तारीख', 'ग्राहक', 'प्रकार', 'माध्यम', 'राशि', 'लिंक'],
        values: (row, money) => [row.payment_number, row.payment_date, row.customer_name_hi || row.customer_name || row.customer_name_en, ({ NORMAL: 'सामान्य जमा', REVERSAL: 'जमा वापसी', SALE_DAY_AUTO: 'बिक्री के दिन स्वतः जमा' }[row.payment_type] || row.payment_type), ({ CASH: 'नकद', UPI: 'यूपीआई', BANK: 'बैंक', CHEQUE: 'चेक' }[row.payment_method] || row.payment_method), money(row.amount), row.reversed_payment_number || row.replacement_for_payment_number],
    },
    interest: {
        title: 'लेट फीस रिपोर्ट',
        columns: ['ग्राहक', 'बिक्री', 'कारण', 'गणना', 'अंतिम तिथि (शामिल नहीं)', 'अवधि', 'दिन', 'दर %', 'मूलधन आधार', 'दर्ज राशि लेट फीस', 'खाता-बही क्रमांक'],
        values: (row, money) => [
            row.customer_name_hi || row.customer_name || row.customer_name_en,
            row.sale_number, (row.trigger_type === 'CUSTOMER_PAYMENT' ? 'जमा मिलने पर' : 'वार्षिक हिसाब'), ({ COMPLETED: 'पूर्ण', PENDING: 'लंबित', FAILED: 'विफल' }[row.run_status] || row.run_status), row.cutoff_date_exclusive,
            (row.interval_start_date || '—') + ' – ' + (row.interval_end_date_exclusive || '—'),
            row.actual_days, row.monthly_rate_percent, money(row.eligible_principal_basis),
            money(row.posted_interest), row.ledger_entry_id,
        ],
    },
};

const escapeHtml = (value) => String(value ?? '—').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
}[character]));

const formatMoney = (value) => new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(value || 0));

export const fetchAllReportPages = async (fetchPage, endpoint, filters, perPage = 100) => {
    const params = new URLSearchParams(filters);
    params.set('page', '1');
    params.set('per_page', String(perPage));

    const rows = [];
    let summary = {};
    let lastPage = 1;

    for (let page = 1; page <= lastPage; page += 1) {
        params.set('page', String(page));
        const response = await fetchPage(endpoint + '?' + params.toString());
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'रिपोर्ट का पूरा डेटा लोड नहीं हो सका।');
        }

        rows.push(...(payload.data || []));
        if (page === 1) {
            summary = payload.summary || {};
            lastPage = Math.max(1, Number(payload.meta?.last_page || 1));
        }
    }

    return { rows, summary };
};

export const buildFinancialReportPrintHtml = ({ report, rows, summary, filters, shopName = 'Kisan Khata', generatedAt = new Date() }) => {
    const definition = reportDefinitions[report];
    if (!definition) throw new Error('चुनी गई रिपोर्ट प्रिंट के लिए उपलब्ध नहीं है।');

    const summaryItems = Object.entries(summary || {}).map(([key, value]) => {
        const display = typeof value === 'string' && /^-?\d+\.\d{2}$/.test(value)
            ? formatMoney(value)
            : escapeHtml(value);
        return '<div class="summary-item"><span>' + escapeHtml(key.replaceAll('_', ' ').replace(/interest/gi, 'लेट फीस')) +
            '</span><strong>' + display + '</strong></div>';
    }).join('');

    const filterItems = Object.entries(filters || {}).filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '')
        .map(([key, value]) => '<div><span>' + escapeHtml(key.replaceAll('_', ' ')) + '</span><strong>' + escapeHtml(value) + '</strong></div>')
        .join('');

    const header = '<thead><tr>' + definition.columns.map((column) => '<th>' + escapeHtml(column) + '</th>').join('') + '</tr></thead>';
    const body = rows.length
        ? rows.map((row) => '<tr>' + definition.values(row, formatMoney).map((value) => '<td>' + escapeHtml(value) + '</td>').join('') + '</tr>').join('')
        : '<tr><td class="empty" colspan="' + definition.columns.length + '">चुने गए फ़िल्टर के लिए कोई रिकॉर्ड उपलब्ध नहीं है।</td></tr>';

    return '<!doctype html><html lang="hi"><head><meta charset="utf-8">' +
        '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' + escapeHtml(definition.title) + '</title>' +
        '<style>' +
        '@page{size:A4 landscape;margin:12mm}' +
        '*{box-sizing:border-box}body{font-family:Arial,"Noto Sans Devanagari",sans-serif;color:#172033;margin:0;font-size:10px}' +
        'h1{font-size:21px;margin:3px 0 5px}.muted{color:#64748b}.header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;border-bottom:2px solid #166534;padding-bottom:12px;margin-bottom:14px}' +
        '.filters,.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;margin:12px 0}.filters div,.summary-item{border:1px solid #dbe3ec;border-radius:5px;padding:7px;min-width:0}.filters span,.summary-item span{display:block;color:#64748b;font-size:9px;text-transform:capitalize}.filters strong,.summary-item strong{display:block;margin-top:3px;font-size:11px;overflow-wrap:anywhere}' +
        'table{width:100%;border-collapse:collapse;font-size:9px}thead{display:table-header-group}th,td{border:1px solid #cbd5e1;padding:6px 5px;text-align:left;vertical-align:top}th{background:#f1f5f9;font-weight:700}tr{break-inside:avoid}.empty{text-align:center;padding:18px}.footer{display:flex;justify-content:space-between;margin-top:12px;color:#64748b;font-size:9px}' +
        '@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}' +
        '</style></head><body>' +
        '<header class="header"><div><div class="muted">' + escapeHtml(shopName) + ' · KISAN KHATA</div><h1>' + escapeHtml(definition.title) + '</h1><div class="muted">यह रिपोर्ट सिस्टम में उपलब्ध posted financial records पर आधारित है।</div></div>' +
        '<div class="muted">तैयार किया गया: ' + escapeHtml(generatedAt.toLocaleString('en-IN')) + '</div></header>' +
        (filterItems ? '<section class="filters">' + filterItems + '</section>' : '') +
        (summaryItems ? '<section class="summary">' + summaryItems + '</section>' : '') +
        '<table>' + header + '<tbody>' + body + '</tbody></table>' +
        '<footer class="footer"><span>कुल रिकॉर्ड: ' + rows.length + '</span><span>यह रिपोर्ट read-only है; इससे कोई वित्तीय रिकॉर्ड परिवर्तित नहीं होता।</span></footer>' +
        '</body></html>';
};

const readReportFilters = () => {
    const report = document.getElementById('report-type').value;
    const filters = {};
    const value = (id) => document.getElementById(id)?.value?.trim() || '';

    if (report === 'outstanding') {
        filters.as_of = value('report-as-of');
    } else {
        filters.from = value('report-from');
        filters.to = value('report-to');
    }

    filters.customer_id = value('report-customer');
    filters.village_code = value('report-village');
    if (report !== 'outstanding') filters.q = value('report-search');
    if (report === 'sales') filters.payment_status = value('report-status');
    if (report === 'payments') {
        filters.payment_method = value('report-method');
        filters.payment_type = value('report-payment-type');
    }

    return Object.fromEntries(Object.entries(filters).filter(([, item]) => item !== ''));
};

const filterLabels = (filters) => {
    const customer = document.getElementById('report-customer');
    const labels = { ...filters };
    if (filters.customer_id && customer?.selectedOptions?.[0]) labels.customer = customer.selectedOptions[0].textContent.trim();
    const village = document.getElementById('report-village');
    if (filters.village_code && village?.selectedOptions?.[0]) labels.village = village.selectedOptions[0].textContent.trim();
    delete labels.customer_id;
    delete labels.village_code;
    return labels;
};

export const initialiseFinancialReportPrint = () => {
    const button = document.getElementById('report-print');
    if (!button) return;

    button.addEventListener('click', async () => {
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            document.getElementById('report-feedback').textContent = 'ब्राउज़र ने print window रोक दी। कृपया इस साइट के pop-ups की अनुमति दें।';
            return;
        }

        button.disabled = true;
        const report = document.getElementById('report-type').value;
        const filters = readReportFilters();
        const feedback = document.getElementById('report-feedback');

        try {
            if (report === 'outstanding' && !filters.as_of) throw new Error('कृपया As-of तारीख चुनें।');

            const api = button.dataset.api;
            const { rows, summary } = await fetchAllReportPages((url) => fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            }), api + '/' + report, filters);

            const html = buildFinancialReportPrintHtml({
                report,
                rows,
                summary,
                filters: filterLabels(filters),
                shopName: button.dataset.shopName || 'Kisan Khata',
            });

            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();
            window.setTimeout(() => {
                printWindow.focus();
                printWindow.print();
            }, 300);
            feedback.textContent = '';
        } catch (error) {
            printWindow.document.open();
            printWindow.document.write('<!doctype html><html lang="hi"><meta charset="utf-8"><body><p>' + escapeHtml(error.message) + '</p></body></html>');
            printWindow.document.close();
            feedback.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });
};
