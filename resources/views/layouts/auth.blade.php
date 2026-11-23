<!doctype html>
<html lang="hi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kisan Khata') · Kisan Khata</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-shell">
    <main class="auth-wrap">
        <a class="brand-lockup" href="{{ route('login') }}">
            <span class="brand-mark"><i class="ti ti-leaf"></i></span>
            <span><strong>Kisan Khata</strong><small>खेती और व्यापार का हिसाब</small></span>
        </a>
        <section class="auth-card">@yield('content')</section>
        <p class="auth-footer">© {{ date('Y') }} Kisan Khata · सरल, सुरक्षित और भरोसेमंद</p>
    </main>
</body>
</html>
