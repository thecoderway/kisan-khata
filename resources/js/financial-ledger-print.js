export const fetchAllLedgerPages = async (request, customerId, { from = '', to = '', perPage = 100 } = {}) => {
    const pageQuery = (page) => {
        const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });
        if (from) params.set('from', from);
        if (to) params.set('to', to);
        return '/customers/' + encodeURIComponent(customerId) + '/ledger?' + params.toString();
    };

    const first = await request(pageQuery(1));
    const lastPage = Math.max(1, Number(first.meta?.last_page || 1));
    const rows = [...(first.data || [])];

    for (let page = 2; page <= lastPage; page += 1) {
        const payload = await request(pageQuery(page));
        rows.push(...(payload.data || []));
    }

    return { rows, meta: first.meta || {}, pages: lastPage };
};
