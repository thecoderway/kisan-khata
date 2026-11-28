const DATE_INPUT_SELECTOR = 'input[type="date"]:not([data-kk-datepicker-ready])';

const enhanceDateInput = (input) => {
    if (!(input instanceof HTMLInputElement) || input.dataset.kkDatepickerReady === 'true') return;
    if (!input.parentNode) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'input-icon kk-datepicker';

    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    input.classList.add('kk-datepicker-input');
    input.dataset.kkDatepickerReady = 'true';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'input-icon-addon kk-datepicker-trigger';
    trigger.setAttribute('aria-label', 'कैलेंडर खोलें');
    trigger.innerHTML = '<i class="ti ti-calendar" aria-hidden="true"></i>';
    trigger.addEventListener('click', () => {
        if (input.disabled || input.readOnly) return;

        if (typeof input.showPicker === 'function') {
            try {
                input.showPicker();
                return;
            } catch {
                // Fall back to the browser's native input interaction.
            }
        }

        input.focus();
        input.click();
    });

    wrapper.appendChild(trigger);
};

const scanDateInputs = (root) => {
    if (root instanceof HTMLInputElement && root.matches('input[type="date"]')) {
        enhanceDateInput(root);
    }

    root.querySelectorAll?.(DATE_INPUT_SELECTOR).forEach(enhanceDateInput);
};

export const initialiseDatepickers = (root = document) => {
    scanDateInputs(root);

    if (root !== document || !document.documentElement || document.documentElement.dataset.kkDatepickerObserver === 'true') return;

    document.documentElement.dataset.kkDatepickerObserver = 'true';
    const observer = new MutationObserver((records) => {
        records.forEach((record) => {
            record.addedNodes.forEach((node) => {
                if (node instanceof HTMLElement) scanDateInputs(node);
            });
        });
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
};
