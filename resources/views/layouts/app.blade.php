<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full bg-slate-50 dark:bg-slate-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'مشوار - غزة') }}</title>

    <!-- Open Graph / WhatsApp / Facebook Preview Tags -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="مشوار - شبكة المواصلات والرحلات في قطاع غزة">
    <meta property="og:description" content="تتبع حي لمركبات الخطوط، حجز مقاعد فوري، مشاوير خاصة ومراسلة، ومخطط مسارات متكامل يعمل بدون إنترنت.">
    <meta property="og:image" content="{{ asset('images/og-preview.png') }}">
    <meta property="og:image:secure_url" content="{{ asset('images/og-preview.png') }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="مشوار - شبكة المواصلات الحية بغزة">
    <meta property="og:site_name" content="مشوار">

    <!-- Twitter / Telegram Preview Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="مشوار - شبكة المواصلات والرحلات في قطاع غزة">
    <meta name="twitter:description" content="تتبع حي لمركبات الخطوط، حجز مقاعد فوري، مشاوير خاصة ومراسلة، ومخطط مسارات متكامل يعمل بدون إنترنت.">
    <meta name="twitter:image" content="{{ asset('images/og-preview.png') }}">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" href="/favicon.svg">

    <!-- Mobile PWA Standalone Meta Tags -->
    <meta name="mobile-web-app-capable" content="yes">
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

    <!-- Alpine.js Cloak Pre-render Shield -->
    <style>
        [x-cloak] { display: none !important; }
    </style>

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
                <!-- Notification Bell Center -->
                <div class="relative" x-data="notificationCenter()" x-init="init()" @click.away="open = false">
                    <button 
                        @click="toggle()" 
                        class="relative p-2 rounded-2xl hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition"
                        title="الإشعارات"
                    >
                        <span class="text-lg">🔔</span>
                        <template x-if="unreadCount > 0">
                            <span 
                                class="absolute -top-0.5 -right-0.5 min-w-4 h-4 px-1 rounded-full bg-rose-500 text-white text-[10px] font-black flex items-center justify-center font-mono shadow-sm animate-pulse"
                                x-text="unreadCount > 99 ? '+99' : unreadCount"
                            ></span>
                        </template>
                    </button>

                    <!-- Mobile Backdrop Overlay -->
                    <div 
                        x-show="open" 
                        x-cloak 
                        @click="open = false" 
                        class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-40 sm:hidden"
                    ></div>

                    <!-- Dropdown Panel -->
                    <div 
                        x-show="open" 
                        x-cloak 
                        x-transition:enter="transition ease-out duration-150 transform"
                        x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="transition ease-in duration-100 transform"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
                        class="fixed left-3 right-3 top-16 sm:absolute sm:inset-auto sm:left-0 sm:top-full sm:mt-2 sm:w-96 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden z-50 flex flex-col max-h-[78vh]"
                    >
                        <!-- Header -->
                        <div class="p-3.5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between bg-slate-50 dark:bg-slate-900/40">
                            <div class="flex items-center gap-2">
                                <span class="font-black text-xs text-slate-800 dark:text-slate-100">الإشعارات والتنبيهات</span>
                                <template x-if="unreadCount > 0">
                                    <span class="px-2 py-0.5 rounded-full bg-teal-100 dark:bg-teal-900/60 text-teal-700 dark:text-teal-300 font-bold text-[10px]" x-text="unreadCount + ' جديدة'"></span>
                                </template>
                            </div>
                            <template x-if="unreadCount > 0">
                                <button @click="markAllAsRead()" class="text-[11px] font-bold text-teal-600 dark:text-teal-400 hover:underline">
                                    تحديد الكل كمقروء
                                </button>
                            </template>
                        </div>

                        <!-- Notification List -->
                        <div class="overflow-y-auto flex-1 divide-y divide-slate-100 dark:divide-slate-700/60">
                            <template x-for="item in notifications" :key="item.id">
                                <div 
                                    @click="handleClick(item)"
                                    class="p-3.5 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition flex items-start gap-3"
                                    :class="item.read ? 'opacity-70' : 'bg-teal-50/40 dark:bg-teal-950/20'"
                                >
                                    <span class="text-xl shrink-0 p-1 rounded-xl bg-slate-100 dark:bg-slate-700" x-text="item.icon || '🔔'"></span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-bold text-xs text-slate-800 dark:text-slate-100 truncate" x-text="item.title"></span>
                                            <span class="text-[10px] text-slate-400 shrink-0 font-medium" x-text="item.time_ago"></span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 dark:text-slate-300 mt-0.5 leading-relaxed line-clamp-2" x-text="item.message"></p>
                                    </div>
                                    <span x-show="!item.read" class="w-2 h-2 rounded-full bg-teal-500 shrink-0 mt-1.5"></span>
                                </div>
                            </template>

                            <div x-show="notifications.length === 0" class="p-8 text-center text-xs text-slate-400 flex flex-col items-center gap-1.5">
                                <span class="text-2xl">📭</span>
                                <span>لا توجد إشعارات حالياً</span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="p-2.5 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 flex items-center justify-between text-[11px]">
                            <button @click="requestWebNotifications()" class="text-slate-500 dark:text-slate-400 hover:text-teal-600 flex items-center gap-1 font-bold">
                                <span>🔔 تفعيل إشعارات المتصفح</span>
                            </button>
                            <button @click="open = false" class="text-slate-400 hover:text-slate-600 font-bold">
                                إغلاق
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('profile.show') }}" class="flex items-center gap-1.5 p-1 rounded-2xl hover:bg-slate-100 dark:hover:bg-slate-800 transition group" title="الملف الشخصي وتعديل البيانات">
                        @if(Auth::user()->avatar)
                            <img src="{{ Auth::user()->avatar }}" alt="{{ Auth::user()->name }}" class="w-8 h-8 rounded-full border border-teal-500 shrink-0">
                        @else
                            <div class="w-8 h-8 rounded-full bg-teal-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
                                {{ mb_substr(Auth::user()->name, 0, 1) }}
                            </div>
                        @endif
                        <span class="text-xs text-slate-700 dark:text-slate-300 font-bold group-hover:text-teal-600 hidden sm:inline truncate max-w-[120px]">{{ Auth::user()->name }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-xs text-rose-600 dark:text-rose-400 font-medium px-2 py-1 rounded-xl hover:bg-rose-50 dark:hover:bg-rose-950/40 transition">خروج</button>
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
                <a href="{{ route('driver.offers') }}" class="flex flex-col items-center justify-center text-xs gap-1 {{ request()->routeIs('driver.offers') ? 'text-teal-600 dark:text-teal-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                    <span class="text-lg">💼</span>
                    <span>الطلبات</span>
                </a>
                <a href="{{ route('driver.manifest') }}" class="flex flex-col items-center justify-center text-xs gap-1 {{ request()->routeIs('driver.manifest') ? 'text-teal-600 dark:text-teal-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                    <span class="text-lg">📋</span>
                    <span>البيان</span>
                </a>
                <a href="{{ route('driver.history') }}" class="flex flex-col items-center justify-center text-xs gap-1 {{ request()->routeIs('driver.history') ? 'text-teal-600 dark:text-teal-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                    <span class="text-lg">📊</span>
                    <span>السجل</span>
                </a>
                <a href="{{ route('driver.dashboard') }}" class="flex flex-col items-center justify-center text-xs gap-1 {{ request()->routeIs('driver.dashboard') ? 'text-teal-600 dark:text-teal-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                    <span class="text-lg">🚗</span>
                    <span>لوحة السائق</span>
                </a>
            @else
                <a href="{{ route('rides.requests') }}" class="flex flex-col items-center justify-center text-xs gap-1 {{ request()->routeIs('rides.requests*') ? 'text-teal-600 dark:text-teal-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                    <span class="text-lg">🚕</span>
                    <span>مشوار خاص</span>
                </a>
                <a href="{{ route('rider.bookings') }}" class="flex flex-col items-center justify-center text-xs gap-1 {{ request()->routeIs('rider.bookings') ? 'text-teal-600 dark:text-teal-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                    <span class="text-lg">🎟️</span>
                    <span>حجوزاتي</span>
                </a>
                <a href="{{ route('rides.history') }}" class="flex flex-col items-center justify-center text-xs gap-1 {{ request()->routeIs('rides.history') ? 'text-teal-600 dark:text-teal-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                    <span class="text-lg">📜</span>
                    <span>رحلاتي</span>
                </a>
            @endif
        @else
            <a href="{{ route('login') }}" class="flex flex-col items-center justify-center text-xs gap-1 {{ request()->routeIs('login') ? 'text-teal-600 dark:text-teal-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                <span class="text-lg">👤</span>
                <span>تسجيل الدخول</span>
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

    <!-- Notification Center State -->
    @auth
    <script>
    function notificationCenter() {
        return {
            open: false,
            unreadCount: 0,
            notifications: [],
            pollTimer: null,

            async init() {
                await this.fetchNotifications();
                // Periodic background poll every 20s
                this.pollTimer = setInterval(() => {
                    if (navigator.onLine) {
                        this.fetchNotifications();
                    }
                }, 20000);
            },

            toggle() {
                this.open = !this.open;
                if (this.open) {
                    this.fetchNotifications();
                }
            },

            async fetchNotifications() {
                try {
                    const res = await fetch('{{ route('api.notifications.index') }}');
                    const data = await res.json();
                    if (data.ok) {
                        const previousCount = this.unreadCount;
                        this.unreadCount = data.unread_count;
                        this.notifications = data.notifications;

                        // New notification alert (audio / haptic / web notification)
                        if (data.unread_count > previousCount && previousCount > 0) {
                            this.notifyArrival(data.notifications[0]);
                        }
                    }
                } catch (e) {
                    // Graceful offline fallback
                }
            },

            async handleClick(item) {
                if (!item.read) {
                    try {
                        await fetch(`/api/notifications/${item.id}/read`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json'
                            }
                        });
                        item.read = true;
                        this.unreadCount = Math.max(0, this.unreadCount - 1);
                    } catch (e) {}
                }
                if (item.action_url) {
                    window.location.href = item.action_url;
                }
            },

            async markAllAsRead() {
                try {
                    const res = await fetch('{{ route('api.notifications.read_all') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();
                    if (data.ok) {
                        this.unreadCount = 0;
                        this.notifications.forEach(n => n.read = true);
                    }
                } catch (e) {}
            },

            notifyArrival(item) {
                if ('vibrate' in navigator) {
                    navigator.vibrate([100, 50, 100]);
                }
                if ('Notification' in window && Notification.permission === 'granted' && item) {
                    new Notification(item.title, {
                        body: item.message,
                        icon: '/favicon.ico'
                    });
                }
            },

            requestWebNotifications() {
                if ('Notification' in window) {
                    Notification.requestPermission().then(permission => {
                        if (permission === 'granted') {
                            new Notification('تم تفعيل التنبيهات في مشوار ✅', {
                                body: 'ستصلك تنبيهات الحجوزات والعروض والرسائل مباشرة.',
                                icon: '/favicon.ico'
                            });
                        }
                    });
                }
            }
        };
    }
    </script>
    @endauth

    @stack('scripts')
</body>
</html>
