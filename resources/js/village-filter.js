const normalizeLabel = (value) => String(value ?? '').trim().toLocaleLowerCase();

export const findVillageOption = (options, label) => {
    const normalized = normalizeLabel(label);
    if (!normalized) return null;

    return Array.from(options || []).find((option) => (
        option.value && normalizeLabel(option.textContent) === normalized
    )) || null;
};

export const syncVillageFilterLabel = (input, select) => {
    if (!input || !select) return;

    const selected = Array.from(select.options || []).find((option) => (
        option.value && option.value === select.value
    ));
    input.value = selected ? selected.textContent.trim() : '';
};

export const initialiseVillageFilter = (input, select) => {
    if (!input || !select) return;

    let updatingFromInput = false;

    const applySearchValue = () => {
        const option = findVillageOption(select.options, input.value);
        const nextValue = option?.value || '';
        if (select.value === nextValue) return;

        updatingFromInput = true;
        select.value = nextValue;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        updatingFromInput = false;
    };

    input.addEventListener('input', applySearchValue);
    input.addEventListener('change', applySearchValue);
    select.addEventListener('change', () => {
        if (!updatingFromInput) syncVillageFilterLabel(input, select);
    });

    syncVillageFilterLabel(input, select);
};
