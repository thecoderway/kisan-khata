const normalizeLabel = (value) => String(value ?? '').normalize('NFC').trim().toLocaleLowerCase();

export const filterVillageOptions = (options, query) => {
    const normalized = normalizeLabel(query);
    return Array.from(options || []).filter((option) => {
        if (!option.value) return false;
        if (!normalized) return true;

        return normalizeLabel(option.textContent).includes(normalized)
            || normalizeLabel(option.dataset?.search || '').includes(normalized)
            || normalizeLabel(option.value).includes(normalized);
    });
};

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
    if (!input || !select || input.dataset.villageFilterReady === 'true') return;

    input.dataset.villageFilterReady = 'true';
    input.removeAttribute('list');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-haspopup', 'listbox');
    input.setAttribute('aria-expanded', 'false');

    const wrapper = input.parentElement;
    wrapper?.classList.add('position-relative');

    const menu = input.ownerDocument.createElement('div');
    menu.id = input.id ? input.id + '-options' : 'village-filter-options';
    menu.className = 'list-group position-absolute start-0 top-100 mt-1 w-100 shadow';
    menu.style.zIndex = '1050';
    menu.style.maxHeight = '18rem';
    menu.style.overflowY = 'auto';
    menu.setAttribute('role', 'listbox');
    menu.hidden = true;
    input.insertAdjacentElement('afterend', menu);
    input.setAttribute('aria-controls', menu.id);

    let activeIndex = -1;
    let updatingFromInput = false;

    const closeMenu = () => {
        menu.hidden = true;
        activeIndex = -1;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    };

    const chooseOption = (option) => {
        if (!option) return;
        select.value = option.value;
        updatingFromInput = true;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        updatingFromInput = false;
        input.value = option.textContent.trim();
        closeMenu();
    };

    const renderOptions = () => {
        const matches = filterVillageOptions(select.options, input.value);
        menu.replaceChildren();
        activeIndex = -1;

        if (!matches.length) {
            const empty = input.ownerDocument.createElement('div');
            empty.className = 'list-group-item text-muted small';
            empty.textContent = 'कोई गाँव नहीं मिला।';
            menu.append(empty);
        } else {
            matches.forEach((option, index) => {
                const button = input.ownerDocument.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';
                button.id = menu.id + '-' + index;
                button.dataset.optionIndex = String(Array.from(select.options).indexOf(option));
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');
                button.textContent = option.textContent.trim();
                button.addEventListener('mousedown', (event) => event.preventDefault());
                button.addEventListener('click', () => chooseOption(option));
                menu.append(button);
            });
        }

        menu.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    input.addEventListener('input', () => {
        const selected = Array.from(select.options || []).find((option) => option.value === select.value);
        if (selected && normalizeLabel(input.value) !== normalizeLabel(selected.textContent)) {
            select.value = '';
            updatingFromInput = true;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            updatingFromInput = false;
        }
        renderOptions();
    });

    input.addEventListener('focus', renderOptions);

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
            return;
        }

        if (menu.hidden) return;
        const options = Array.from(menu.querySelectorAll('[role="option"]'));
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (!options.length) return;
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            activeIndex = Math.max(0, Math.min(options.length - 1, activeIndex + direction));
            options.forEach((option, index) => {
                option.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
            });
            input.setAttribute('aria-activedescendant', options[activeIndex].id);
            options[activeIndex].scrollIntoView?.({ block: 'nearest' });
        }

        if (event.key === 'Enter') {
            const exact = findVillageOption(select.options, input.value);
            const option = activeIndex >= 0
                ? select.options[Array.from(menu.querySelectorAll('[role="option"]'))[activeIndex]?.dataset.optionIndex]
                : exact;
            if (option) {
                event.preventDefault();
                chooseOption(option);
            }
        }
    });

    select.addEventListener('change', () => {
        if (!updatingFromInput) {
            syncVillageFilterLabel(input, select);
            closeMenu();
        }
    });

    input.ownerDocument.addEventListener('click', (event) => {
        if (!wrapper?.contains(event.target)) closeMenu();
    });

    syncVillageFilterLabel(input, select);
};
