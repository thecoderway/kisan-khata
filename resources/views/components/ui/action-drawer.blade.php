@props([
    'id' => 'action-drawer',
    'title' => 'Details',
    'width' => 'default',
])

<div
    x-data="actionDrawer()"
    x-init="$nextTick(() => { if ($refs.body.querySelector('[data-drawer-error-summary]')) open({ title: @js($title), width: @js($width) }) })"
    x-on:keydown.escape.window="onEscape($event)"
    x-on:kk-drawer:close="close()"
    x-on:keydown.tab="trapFocus($event)"
    x-on:submit="onSubmit($event)"
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

