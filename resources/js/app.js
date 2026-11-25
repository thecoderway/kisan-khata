import '@tabler/core/dist/js/tabler.min.js';

import Alpine from 'alpinejs';
import { registerCustomerLocation } from './customer-location';

window.Alpine = Alpine;

registerCustomerLocation(Alpine);

Alpine.data('actionDrawer', () => ({
    isOpen: false,
    title: '',
    subtitle: '',
    width: 'default',
    previousFocus: null,
    previousBodyOverflow: '',
    previousInertStates: [],

    init() {
        this.$el.addEventListener('kk-drawer:open', (event) => this.open(event.detail || {}));
    },

    open(options = {}) {
        this.previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        this.title = options.title || '';
        this.subtitle = options.subtitle || '';
        this.width = options.width === 'wide' ? 'wide' : 'default';
        this.previousBodyOverflow = document.body.style.overflow;
        this.isOpen = true;
        document.body.style.overflow = 'hidden';
        this.previousInertStates = Array.from(document.querySelectorAll('.app-main, .app-sidebar')).map((element) => ({ element, inert: element.inert }));
        this.previousInertStates.forEach(({ element }) => { element.inert = true; });

        this.$nextTick(() => {
            const errorSummary = this.$refs.body?.querySelector('[data-drawer-error-summary]');
            if (errorSummary) {
                errorSummary.focus();
                return;
            }
            const first = this.focusableElements()[0];
            (first || this.$refs.panel)?.focus();
        });
    },

    close() {
        if (!this.isOpen) return;

        this.isOpen = false;
        document.body.style.overflow = this.previousBodyOverflow;
        this.previousInertStates.forEach(({ element, inert }) => { element.inert = inert; });
        this.previousInertStates = [];

        this.$nextTick(() => {
            if (this.previousFocus?.isConnected) this.previousFocus.focus();
            this.previousFocus = null;
        });
    },

    onEscape(event) {
        if (!this.isOpen) return;
        event.preventDefault();
        this.close();
    },

    focusableElements() {
        if (!this.$refs.panel) return [];
        return Array.from(this.$refs.panel.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter((element) => element.getClientRects().length > 0);
    },

    trapFocus(event) {
        if (!this.isOpen || event.key !== 'Tab') return;

        const elements = this.focusableElements();
        if (!elements.length) {
            event.preventDefault();
            this.$refs.panel?.focus();
            return;
        }

        const first = elements[0];
        const last = elements[elements.length - 1];

        if (!this.$refs.panel.contains(document.activeElement)) {
            event.preventDefault();
            (event.shiftKey ? last : first).focus();
        } else if (event.shiftKey && (document.activeElement === first || document.activeElement === this.$refs.panel)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    },

    onSubmit(event) {
        const form = event.target;

        if (!this.isOpen || !this.$refs.panel?.contains(form)) return;

        const submitButtons = Array.from(this.$refs.panel.querySelectorAll('button[type="submit"], input[type="submit"]'))
            .filter((button) => button.form === form);

        if (submitButtons.every((button) => button.disabled)) return;

        form.setAttribute('aria-busy', 'true');
        submitButtons.forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
        });
    },
}));

Alpine.start();

document.addEventListener('click', async (event) => {
    const closeButton = event.target.closest('[data-drawer-close]');
    if (closeButton) {
        document.querySelector('.kk-drawer-root')?.dispatchEvent(new CustomEvent('kk-drawer:close', { bubbles: true }));
        return;
    }

    const trigger = event.target.closest('[data-customer-drawer]');
    if (!trigger) return;

    event.preventDefault();
    const root = document.querySelector('.kk-drawer-root');
    const body = root?.querySelector('.kk-drawer-body');
    if (!root || !body) return;

    body.innerHTML = '<div class="py-5 text-center text-secondary" role="status">लोड हो रहा है…</div>';

    try {
        const response = await fetch(trigger.dataset.url, {
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('फॉर्म लोड नहीं हो सका। कृपया दोबारा प्रयास करें।');
        body.innerHTML = await response.text();
        root.dispatchEvent(new CustomEvent('kk-drawer:open', {
            bubbles: true,
            detail: { title: trigger.dataset.title || 'ग्राहक', width: trigger.dataset.width || 'default' },
        }));
    } catch (error) {
        body.innerHTML = '<div class="alert alert-danger" role="alert"></div>';
        body.querySelector('[role="alert"]').textContent = error.message;
        root.dispatchEvent(new CustomEvent('kk-drawer:open', {
            bubbles: true,
            detail: { title: 'त्रुटि', width: 'default' },
        }));
    }
});

