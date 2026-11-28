export function attachVillageDropdown(input, menu) {
    if (!input || !menu || menu.dataset.portalReady === 'true') return () => {};

    menu.dataset.portalReady = 'true';
    menu.hidden = true;
    menu.style.position = 'fixed';
    menu.style.zIndex = '1200';
    menu.style.maxHeight = 'min(18rem, calc(100vh - 16px))';
    menu.style.overflowY = 'auto';
    menu.style.overflowX = 'hidden';
    menu.style.width = 'auto';
    menu.style.maxWidth = 'calc(100vw - 16px)';
    menu.style.margin = '4px 0 0';
    document.body.append(menu);

    const position = () => {
        if (menu.hidden || !input.isConnected) return;
        const rect = input.getBoundingClientRect();
        const gap = 4;
        const viewportPadding = 8;
        const width = Math.min(rect.width, window.innerWidth - viewportPadding * 2);
        const left = Math.max(viewportPadding, Math.min(rect.left, window.innerWidth - width - viewportPadding));
        const below = window.innerHeight - rect.bottom - gap - viewportPadding;
        const above = rect.top - gap - viewportPadding;
        const openAbove = below < 160 && above > below;
        const availableHeight = Math.max(100, openAbove ? above : below);

        menu.style.left = left + 'px';
        menu.style.width = width + 'px';
        menu.style.maxHeight = Math.min(288, availableHeight) + 'px';
        menu.style.top = openAbove
            ? Math.max(viewportPadding, rect.top - Math.min(288, availableHeight) - gap) + 'px'
            : rect.bottom + gap + 'px';
    };

    const setOpen = (open) => {
        menu.hidden = !open;
        if (open) position();
    };

    const onViewportChange = () => position();
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, true);

    return setOpen;
}
