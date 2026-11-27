export async function fetchFinancialCustomers(request, mode = 'history') {
    const statusByMode = {
        transaction: 'active',
        history: 'financial-history',
        policy: 'all',
    };
    const status = statusByMode[mode];
    if (!status) throw new Error('Unsupported financial customer directory mode: ' + mode);

    const params = new URLSearchParams({ pageSize: '100', page: '1', status });
    const first = await request('/customers?' + params.toString());
    const records = [...(first.data || [])];
    const lastPage = Number(first.meta?.last_page || 1);

    for (let page = 2; page <= lastPage; page++) {
        params.set('page', String(page));
        const next = await request('/customers?' + params.toString());
        records.push(...(next.data || []));
    }

    return records;
}

export function customerDisplayLabels(customers, { includeStatus = false } = {}) {
    const nameFor = (customer) => String(
        customer.name_original || customer.name_hi || customer.name_en || ('Customer #' + customer.id)
    ).trim();
    const counts = new Map();

    customers.forEach((customer) => {
        const key = nameFor(customer).toLocaleLowerCase();
        counts.set(key, (counts.get(key) || 0) + 1);
    });

    const labels = customers.map((customer) => {
        const name = nameFor(customer);
        if (counts.get(name.toLocaleLowerCase()) < 2) return name;

        const mobile = String(customer.mobile || '').trim();
        return name + ' · ' + (mobile || ('#' + customer.id));
    });
    const labelCounts = new Map();

    labels.forEach((label) => labelCounts.set(label, (labelCounts.get(label) || 0) + 1));

    return labels.map((label, index) => {
        const uniqueLabel = labelCounts.get(label) > 1
            ? label + ' · #' + customers[index].id
            : label;
        if (!includeStatus) return uniqueLabel;
        if (customers[index].deleted_at) return uniqueLabel + ' (हटाया गया)';
        if (customers[index].status === 'inactive') return uniqueLabel + ' (निष्क्रिय)';
        return uniqueLabel;
    });
}
