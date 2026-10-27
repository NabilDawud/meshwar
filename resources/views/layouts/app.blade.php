<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full bg-slate-50 dark:bg-slate-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'مشوار - غزة') }}</title>

    <!-- iOS Safari PWA Standalone Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="مشوار">
    <meta name="theme-color" content="#0d9488">
    <link rel="manifest" href="/manifest.json">

    <!-- Fonts: Cairo for Arabic UI -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind & App Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    @stack('styles')
</head>
<body class="flex flex-col min-h-dvh text-slate-800 dark:text-slate-100 antialiased selection:bg-teal-500 selection:text-white" style="padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom);">
    <!-- Top Header -->
    <header class="sticky top-0 z-40 bg-white/90 dark:bg-slate-800/90 backdrop-blur border-b border-slate-200 dark:border-slate-700 px-4 h-14 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-lg text-teal-600 dark:text-teal-400">
                <span class="text-2xl">🚐</span>
                <span>مشوار</span>
            </a>
            <span class="text-xs px-2 py-0.5 rounded-full bg-teal-100 text-teal-800 dark:bg-teal-900/60 dark:text-teal-300 font-medium">غزة</span>
        </div>

        <div class="flex items-center gap-2">
            @auth
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium hidden sm:inline">{{ Auth::user()->name }}</span>
                    @if(Auth::user()->avatar)
                        <img src="{{ Auth::user()->avatar }}" alt="{{ Auth::user()->name }}" class="w-8 h-8 rounded-full border border-teal-500">
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-xs text-rose-600 dark:text-rose-400 font-medium px-2 py-1 rounded hover:bg-rose-50 dark:hover:bg-rose-950/40">خروج</button>
                    </form>
                </div>
            @else
                <a href="{{ route('login') }}" class="text-xs font-semibold px-3 py-1.5 rounded-full bg-teal-600 text-white hover:bg-teal-700 transition">
                    تسجيل الدخول
                </a>
            @endauth
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col w-full max-w-lg mx-auto">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- Bottom Navigation Bar (Fixed for Mobile) -->
    <nav class="sticky bottom-0 z-40 bg-white/95 dark:bg-slate-800/95 backdrop-blur border-t border-slate-200 dark:border-slate-700 px-4 h-16 flex items-center justify-around">
        <a href="{{ route('home') }}" class="flex flex-col items-center justify-center text-xs gap-1 {{ request()->routeIs('home') ? 'text-teal-600 dark:text-teal-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
            <span class="text-lg">🗺️</span>
            <span>الرئيسية</span>
        </a>

        <a href="{{ route('rides.plan') }}" class="flex flex-col items-center justify-center text-xs gap-1 {{ request()->routeIs('rides.plan') ? 'text-teal-600 dark:text-teal-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
            <span class="text-lg">🧭</span>
            <span>المخطط</span>
        </a>

        @auth
            @if(Auth::user()->role === 'driver')
                <a href="{{ route('driver.dashboard') }}" class="flex flex-col items-center justify-center text-xs gap-1 {{ request()->routeIs('driver.*') ? 'text-teal-600 dark:text-teal-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                    <span class="text-lg">🚗</span>
                    <span>لوحة السائق</span>
                </a>
            @else
                <a href="{{ route('rider.home') }}" class="flex flex-col items-center justify-center text-xs gap-1 {{ request()->routeIs('rider.*') ? 'text-teal-600 dark:text-teal-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                    <span class="text-lg">📍</span>
                    <span>المركبات</span>
                </a>
            @endif
        @else
            <a href="{{ route('login') }}" class="flex flex-col items-center justify-center text-xs gap-1 {{ request()->routeIs('login') ? 'text-teal-600 dark:text-teal-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                <span class="text-lg">👤</span>
                <span>حسابي</span>
            </a>
        @endauth
    </nav>

    <!-- PWA Service Worker Registration for Offline Tiles -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(err => {
                    console.warn('Service worker registration note:', err);
                });
            });
        }
    </script>

    @stack('scripts')
</body>
</html>
