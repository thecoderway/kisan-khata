<!doctype html>
<html lang="{{ app()->getLocale() === 'hi' ? 'hi' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kisan Khata') · Kisan Khata</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell" x-data="{ sidebarOpen: false }">
    <div class="app-overlay" x-cloak x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"></div>
    <aside class="app-sidebar" :class="{ 'is-open': sidebarOpen }">
        <a class="app-brand" href="{{ route('dashboard') }}">
            <span class="brand-mark"><i class="ti ti-leaf"></i></span>
            <span><strong>Kisan Khata</strong><small>खेती और व्यापार का हिसाब</small></span>
        </a>
        <div class="sidebar-shop">
            <span class="sidebar-shop-icon"><i class="ti ti-building-store"></i></span>
            <span><small>आपकी दुकान</small><strong>{{ app(\App\Tenancy\CurrentShop::class)->require()->name }}</strong></span>
        </div>
        <nav class="app-navigation" aria-label="मुख्य नेविगेशन">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="ti ti-layout-dashboard"></i><span>डैशबोर्ड</span>
            </a>
            <a href="{{ route('customers.index') }}" class="{{ request()->routeIs('customers.*') ? 'active' : '' }}">
                <i class="ti ti-users"></i><span>ग्राहक</span>
            </a>
            @if (auth()->user()->role === \App\Models\User::ROLE_OWNER)
                <a href="{{ route('shop-profile.edit') }}" class="{{ request()->routeIs('shop-profile.*') ? 'active' : '' }}">
                    <i class="ti ti-building-store"></i><span>दुकान की जानकारी</span>
                </a>
            @endif
            @if (in_array(auth()->user()->role, [\App\Models\User::ROLE_OWNER, \App\Models\User::ROLE_MANAGER], true))
                <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.index', 'users.create') ? 'active' : '' }}">
                    <i class="ti ti-users"></i><span>{{ auth()->user()->role === \App\Models\User::ROLE_MANAGER ? 'Staff सूची' : 'यूज़र प्रबंधन' }}</span>
                </a>
            @endif
            @if (auth()->user()->role === \App\Models\User::ROLE_OWNER)
                <a href="{{ route('users.audit') }}" class="{{ request()->routeIs('users.audit') ? 'active' : '' }}">
                    <i class="ti ti-history"></i><span>यूज़र ऑडिट हिस्ट्री</span>
                </a>
            @endif
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-user">
                <span class="user-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <span><strong>{{ auth()->user()->name }}</strong><small>{{ ucfirst(auth()->user()->role) }}</small></span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar-logout"><i class="ti ti-logout"></i> लॉगआउट</button>
            </form>
        </div>
    </aside>
    <div class="app-main">
        <header class="app-topbar">
            <button type="button" class="mobile-menu-button" aria-label="मेन्यू खोलें" @click="sidebarOpen = true"><i class="ti ti-menu-2"></i></button>
            <div class="topbar-title">@yield('page-title', 'डैशबोर्ड')</div>
            <div class="topbar-shop">{{ app(\App\Tenancy\CurrentShop::class)->require()->name }}</div>
        </header>
        <main class="app-content">@yield('content')</main>
        <footer class="app-footer">© {{ date('Y') }} Kisan Khata · सरल, सुरक्षित और भरोसेमंद</footer>
    </div>
</body>
</html>
