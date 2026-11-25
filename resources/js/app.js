import '@tabler/core/dist/js/tabler.min.js';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

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

        if (event.shiftKey && (document.activeElement === first || document.activeElement === this.$refs.panel)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    },
}));

Alpine.start();
