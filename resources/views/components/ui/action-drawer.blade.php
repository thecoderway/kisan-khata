@props([
    'id' => 'action-drawer',
    'title' => 'Details',
    'width' => 'default',
])

<div
    x-data="actionDrawer()"
    x-on:keydown.escape.window="onEscape($event)"
    x-on:keydown.tab="trapFocus($event)"
    x-show="isOpen"
    x-cloak
    class="kk-drawer-root"
    :aria-hidden="(!isOpen).toString()"
>
    <div
        class="kk-drawer-backdrop"
        x-show="isOpen"
        x-transition.opacity
        x-on:click="close()"
        aria-hidden="true"
    ></div>

    <section
        id="{{ $id }}"
        class="kk-action-drawer"
        :class="{ 'is-wide': width === 'wide' }"
        x-show="isOpen"
        x-transition:enter="kk-drawer-enter"
        x-transition:enter-start="kk-drawer-enter-start"
        x-transition:enter-end="kk-drawer-enter-end"
        x-transition:leave="kk-drawer-leave"
        x-transition:leave-start="kk-drawer-leave-start"
        x-transition:leave-end="kk-drawer-leave-end"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $id }}-title"
        tabindex="-1"
        x-ref="panel"
    >
        <header class="kk-drawer-header">
            <div>
                <h2 id="{{ $id }}-title" class="kk-drawer-title" x-text="title || @js($title)">{{ $title }}</h2>
                <p class="kk-drawer-subtitle" x-show="subtitle" x-text="subtitle"></p>
            </div>
            <button type="button" class="btn btn-icon btn-ghost-secondary" aria-label="Drawer बंद करें" x-on:click="close()">
                <i class="ti ti-x" aria-hidden="true"></i>
            </button>
        </header>

        <div class="kk-drawer-body" x-ref="body">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="kk-drawer-footer">
                {{ $footer }}
            </footer>
        @endisset
    </section>
</div>

<style>
    .kk-drawer-root { position:relative; z-index:1090; }
    .kk-drawer-backdrop { position:fixed; inset:0; z-index:1090; background:rgb(15 23 42 / 48%); }
    .kk-action-drawer { position:fixed; z-index:1091; inset:0 0 0 auto; display:flex; flex-direction:column; width:min(480px,100vw); max-width:100vw; height:100dvh; background:#fff; box-shadow:-12px 0 40px rgb(15 23 42 / 16%); outline:none; }
    .kk-action-drawer.is-wide { width:min(640px,100vw); }
    .kk-drawer-header,.kk-drawer-footer { display:flex; flex:0 0 auto; align-items:center; justify-content:space-between; gap:16px; padding:16px 20px; background:#fff; }
    .kk-drawer-header { min-height:68px; border-bottom:1px solid #e2e8f0; }
    .kk-drawer-footer { justify-content:flex-end; border-top:1px solid #e2e8f0; }
    .kk-drawer-title { margin:0; color:#0f172a; font-size:17px; font-weight:750; }
    .kk-drawer-subtitle { margin:3px 0 0; color:#64748b; font-size:12px; }
    .kk-drawer-body { flex:1 1 auto; min-height:0; overflow-y:auto; overscroll-behavior:contain; padding:20px; }
    .kk-drawer-enter,.kk-drawer-leave { transition:transform .22s ease,opacity .22s ease; }
    .kk-drawer-enter-start,.kk-drawer-leave-end { transform:translateX(100%); opacity:.7; }
    .kk-drawer-enter-end,.kk-drawer-leave-start { transform:translateX(0); opacity:1; }
    @media(max-width:767px) {
        .kk-action-drawer,.kk-action-drawer.is-wide { width:100vw; }
        .kk-drawer-header,.kk-drawer-footer { padding:14px 16px; }
        .kk-drawer-body { padding:16px; }
    }
    @media(prefers-reduced-motion:reduce) {
        .kk-drawer-enter,.kk-drawer-leave { transition:none; }
    }
</style>
