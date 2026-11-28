export function attachVillageDropdown(input, menu) {
    if (!input || !menu || menu.dataset.portalReady === 'true') return () => {};

    menu.dataset.portalReady = 'true';
    menu.classList.remove('position-absolute', 'start-0', 'top-100', 'mt-1', 'w-100');
    menu.hidden = true;
    menu.style.position = 'fixed';
    menu.style.zIndex = '1200';
    menu.style.maxHeight = 'min(18rem, calc(100vh - 16px))';
    menu.style.overflowY = 'auto';
    menu.style.overflowX = 'hidden';
    menu.style.width = 'auto';
    menu.style.maxWidth = 'calc(100vw - 16px)';
    menu.style.margin = '0';
    document.body.append(menu);

    const position = () => {
        if (menu.hidden || !input.isConnected) return;

        const rect = input.getBoundingClientRect();
        const viewportPadding = 8;
        const gap = 4;
        const width = Math.min(rect.width, window.innerWidth - viewportPadding * 2);
        const left = Math.max(viewportPadding, Math.min(rect.left, window.innerWidth - width - viewportPadding));
        const below = Math.max(0, window.innerHeight - rect.bottom - gap - viewportPadding);
        const above = Math.max(0, rect.top - gap - viewportPadding);
        const naturalHeight = Math.min(288, Math.max(40, menu.scrollHeight));
        const openAbove = below < naturalHeight && above > below;
        const availableHeight = Math.max(40, openAbove ? above : below);
        const height = Math.min(naturalHeight, availableHeight);

        menu.style.left = left + 'px';
        menu.style.width = width + 'px';
        menu.style.maxHeight = height + 'px';
        menu.style.top = (openAbove ? rect.top - height - gap : rect.bottom + gap) + 'px';
    };

    const setOpen = (open) => {
        menu.hidden = !open;
        if (open) position();
    };

    window.addEventListener('resize', position);
    window.addEventListener('scroll', position, true);

    return setOpen;
}
