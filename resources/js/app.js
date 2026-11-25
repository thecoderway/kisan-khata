import '@tabler/core/dist/js/tabler.min.js';

import Alpine from 'alpinejs';
import { registerCustomerLocation } from './customer-location';
import { registerCustomerDirectory } from './customer-directory';

window.Alpine = Alpine;

registerCustomerLocation(Alpine);
registerCustomerDirectory();

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

        if (!form.hasAttribute('data-drawer-ajax')) {
            const submitButtons = Array.from(this.$refs.panel.querySelectorAll('button[type="submit"], input[type="submit"]'))
                .filter((button) => button.form === form);

            form.setAttribute('aria-busy', 'true');
            submitButtons.forEach((button) => {
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
            });
            return;
        }

        event.preventDefault();

        if (form.dataset.submitting === 'true') return;

        this.submitDrawerForm(form);
    },

    clearDrawerErrors(form) {
        form.querySelectorAll('[data-server-error]').forEach((element) => element.remove());
        form.querySelectorAll('.is-invalid').forEach((element) => {
            element.classList.remove('is-invalid');
            element.removeAttribute('aria-invalid');
        });
        form.querySelectorAll('[data-server-error-summary]').forEach((element) => element.remove());
    },

    showDrawerMessage(form, message, errors = {}) {
        const summary = document.createElement('div');
        summary.className = 'alert alert-danger';
        summary.setAttribute('role', 'alert');
        summary.setAttribute('tabindex', '-1');
        summary.setAttribute('data-drawer-error-summary', '');
        summary.setAttribute('data-server-error-summary', '');

        const heading = document.createElement('strong');
        heading.textContent = message || 'जानकारी सेव नहीं हो सकी।';
        summary.appendChild(heading);

        const messages = Object.values(errors).flat().filter(Boolean);
        if (messages.length) {
            const list = document.createElement('ul');
            list.className = 'mb-0 mt-2';
            messages.forEach((messageText) => {
                const item = document.createElement('li');
                item.textContent = messageText;
                list.appendChild(item);
            });
            summary.appendChild(list);
        }

        form.prepend(summary);

        Object.entries(errors).forEach(([name, fieldMessages]) => {
            const field = Array.from(form.elements).find((element) => element.name === name);
            if (!field || field.type === 'hidden' || !fieldMessages?.length) return;

            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');

            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-block';
            feedback.setAttribute('data-server-error', '');
            feedback.textContent = fieldMessages[0];
            field.insertAdjacentElement('afterend', feedback);

            if (field.id) {
                const describedBy = (field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
                describedBy.push(feedback.id || (feedback.id = field.id + '-server-error'));
                field.setAttribute('aria-describedby', [...new Set(describedBy)].join(' '));
            }
        });

        summary.focus();
    },

    async submitDrawerForm(form) {
        const submitButtons = Array.from(this.$refs.panel.querySelectorAll('button[type="submit"], input[type="submit"]'))
            .filter((button) => button.form === form);
        const originalLabels = submitButtons.map((button) => button.innerHTML);

        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        this.clearDrawerErrors(form);

        submitButtons.forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
            button.setAttribute('aria-busy', 'true');
        });

        try {
            const response = await fetch(form.action, {
                method: (form.method || 'POST').toUpperCase(),
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => ({}));

            if (response.status === 422) {
                this.showDrawerMessage(form, payload.message || 'कृपया दर्ज की गई जानकारी जाँचें।', payload.errors || {});
                return;
            }

            if (!response.ok || payload.success !== true) {
                const message = response.status === 401 || response.status === 419
                    ? 'आपका सत्र समाप्त हो गया है। कृपया पेज रीफ्रेश करके फिर से लॉगिन करें।'
                    : response.status === 403
                        ? 'आपको यह कार्रवाई करने की अनुमति नहीं है।'
                        : payload.message || 'कार्रवाई पूरी नहीं हो सकी। कृपया रिकॉर्ड की स्थिति जाँचकर दोबारा प्रयास करें।';

                this.showDrawerMessage(form, message);
                return;
            }

            this.close();
            window.dispatchEvent(new CustomEvent('kk-drawer:success', { detail: payload }));
        } catch (error) {
            this.showDrawerMessage(form, 'नेटवर्क समस्या के कारण परिणाम की पुष्टि नहीं हो सकी। दोबारा भेजने से पहले रिकॉर्ड की स्थिति जाँचें।');
        } finally {
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            submitButtons.forEach((button, index) => {
                button.disabled = false;
                button.removeAttribute('aria-disabled');
                button.removeAttribute('aria-busy');
                if (button.innerHTML !== originalLabels[index] && !button.dataset.keepLabel) {
                    button.innerHTML = originalLabels[index];
                }
            });
        }
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

