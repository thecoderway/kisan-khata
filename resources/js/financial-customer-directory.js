export async function fetchFinancialCustomers(request) {
    const fetchSet = async (status) => {
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
    };

    const [activeCustomers, inactiveCustomers] = await Promise.all([
        fetchSet('active'),
        fetchSet('inactive'),
    ]);

    return [...activeCustomers, ...inactiveCustomers];
}

export function customerDisplayLabels(customers) {
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

    return labels.map((label, index) => labelCounts.get(label) > 1
        ? label + ' · #' + customers[index].id
        : label);
}
