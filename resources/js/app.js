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
        if (!this.isOpen || this.$refs.panel?.querySelector('form[data-submitting="true"]')) return;

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
        this.$refs.body?.querySelectorAll('[data-drawer-error-summary]').forEach((element) => element.remove());
        form.querySelectorAll('[aria-describedby]').forEach((field) => {
            const remaining = (field.getAttribute('aria-describedby') || '')
                .split(' ')
                .filter((id) => id && !id.endsWith('-server-error'));
            if (remaining.length) {
                field.setAttribute('aria-describedby', remaining.join(' '));
            } else {
                field.removeAttribute('aria-describedby');
            }
        });
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
        const pendingIndicators = [];

        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        this.clearDrawerErrors(form);

        submitButtons.forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
            button.setAttribute('aria-busy', 'true');

            if (button instanceof HTMLButtonElement) {
                button.classList.add('kk-drawer-submit-pending');

                const indicator = document.createElement('span');
                indicator.className = 'kk-drawer-submit-indicator';
                indicator.setAttribute('role', 'status');
                indicator.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>प्रक्रिया जारी है…</span>';
                button.appendChild(indicator);
                pendingIndicators.push({ button, indicator });
            }
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

            delete form.dataset.submitting;
            this.close();
            window.dispatchEvent(new CustomEvent('kk-toast:show', {
                detail: {
                    message: payload.message || 'कार्रवाई सफलतापूर्वक पूरी हुई।',
                    variant: 'success',
                },
            }));
            window.dispatchEvent(new CustomEvent('kk-drawer:success', { detail: payload }));
        } catch (error) {
            this.showDrawerMessage(form, 'नेटवर्क समस्या के कारण परिणाम की पुष्टि नहीं हो सकी। दोबारा भेजने से पहले रिकॉर्ड की स्थिति जाँचें।');
        } finally {
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            pendingIndicators.forEach(({ button, indicator }) => {
                indicator.remove();
                button.classList.remove('kk-drawer-submit-pending');
            });
            submitButtons.forEach((button) => {
                button.disabled = false;
                button.removeAttribute('aria-disabled');
                button.removeAttribute('aria-busy');
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

    const trigger = event.target.closest('[data-customer-drawer], [data-user-drawer]');
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
            detail: { title: trigger.dataset.title || (trigger.hasAttribute('data-user-drawer') ? 'यूज़र' : 'ग्राहक'), width: trigger.dataset.width || 'default' },
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



const customerDropdownPortals = new WeakMap();

document.addEventListener('shown.bs.dropdown', (event) => {
    const toggle = event.target;
    const dropdown = toggle.closest('[data-customer-dropdown-portal]');
    const menu = dropdown?.querySelector('.dropdown-menu');

    if (!menu || customerDropdownPortals.has(toggle)) return;

    const placeholder = document.createComment('customer-dropdown-menu');
    menu.parentNode.insertBefore(placeholder, menu);
    document.body.appendChild(menu);
    menu.classList.add('kk-dropdown-menu-portal');

    const positionMenu = () => {
        if (!menu.isConnected || !toggle.isConnected) return;

        const triggerRect = toggle.getBoundingClientRect();
        const menuWidth = menu.offsetWidth;
        const menuHeight = menu.offsetHeight;
        const spaceBelow = window.innerHeight - triggerRect.bottom;
        const placeBelow = spaceBelow >= menuHeight + 8 || spaceBelow >= triggerRect.top;
        const top = placeBelow
            ? Math.min(triggerRect.bottom + 4, window.innerHeight - menuHeight - 8)
            : Math.max(8, triggerRect.top - menuHeight - 4);
        const left = Math.max(8, Math.min(triggerRect.right - menuWidth, window.innerWidth - menuWidth - 8));

        menu.style.position = 'fixed';
        menu.style.inset = 'auto';
        menu.style.top = top + 'px';
        menu.style.left = left + 'px';
        menu.style.right = 'auto';
        menu.style.transform = 'none';
        menu.style.margin = '0';
    };

    const state = { menu, placeholder, positionMenu };
    customerDropdownPortals.set(toggle, state);
    positionMenu();
    window.addEventListener('resize', positionMenu);
    window.addEventListener('scroll', positionMenu, true);
});

document.addEventListener('hidden.bs.dropdown', (event) => {
    const state = customerDropdownPortals.get(event.target);
    if (!state) return;

    window.removeEventListener('resize', state.positionMenu);
    window.removeEventListener('scroll', state.positionMenu, true);
    state.menu.classList.remove('kk-dropdown-menu-portal');
    state.menu.removeAttribute('style');

    if (state.placeholder.parentNode) {
        state.placeholder.parentNode.insertBefore(state.menu, state.placeholder);
        state.placeholder.remove();
    }

    customerDropdownPortals.delete(event.target);
});


window.addEventListener('kk-toast:show', (event) => {
    const region = document.getElementById('kk-toast-region');
    const message = String(event.detail?.message || '').trim();

    if (!region || !message) return;

    const toast = document.createElement('div');
    toast.className = 'kk-floating-toast is-' + (event.detail?.variant === 'error' ? 'error' : 'success');
    toast.setAttribute('role', event.detail?.variant === 'error' ? 'alert' : 'status');

    const icon = document.createElement('i');
    icon.className = event.detail?.variant === 'error' ? 'ti ti-alert-circle' : 'ti ti-circle-check';
    icon.setAttribute('aria-hidden', 'true');

    const text = document.createElement('span');
    text.className = 'kk-floating-toast-message';
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'kk-floating-toast-close';
    close.setAttribute('aria-label', 'सूचना बंद करें');
    close.innerHTML = '<i class="ti ti-x" aria-hidden="true"></i>';

    const dismiss = () => {
        window.clearTimeout(timeout);
        toast.remove();
    };
    close.addEventListener('click', dismiss);

    toast.append(icon, text, close);
    region.appendChild(toast);

    const timeout = window.setTimeout(dismiss, 5000);
});

window.addEventListener('kk-drawer:success', () => {
    if (document.querySelector('[data-user-directory]')) window.location.reload();
});
