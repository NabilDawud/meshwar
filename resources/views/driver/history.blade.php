@extends('layouts.app')

@section('title', 'سجل رحلات السائق والدخل - مشوار')

@section('content')
<div class="p-4 flex flex-col gap-4 max-w-lg mx-auto" x-data="driverHistoryState()" x-init="init()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span>📊</span>
                <span>سجل رحلاتي وأرباحي</span>
            </h1>
            <p class="text-xs text-slate-500">إحصائيات الرحلات المنجزة، الدخل المحقق، وتقييمات الركاب</p>
        </div>
        <button @click="fetchHistory()" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition">
            🔄 تحديث
        </button>
    </div>

    <!-- Offline Alert -->
    <div x-show="isOffline" x-cloak class="p-3 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 text-xs font-bold flex items-center gap-2">
        <span>⚠️</span>
        <span>أنت غير متصل بالإنترنت. يتم عرض سجل الأرباح والرحلات المحفوظ محلياً.</span>
    </div>

    <!-- Metrics Cards (Trips, Income & Ratings) -->
    <div class="grid grid-cols-3 gap-2 text-center">
        <div class="p-3 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col gap-1 shadow-sm">
            <span class="text-[10px] text-slate-400 font-bold">الرحلات المنجزة</span>
            <div class="flex items-baseline justify-center gap-0.5">
                <span class="text-xl font-black text-slate-800 dark:text-slate-100 font-mono" x-text="stats.total_completed_trips || 0"></span>
                <span class="text-[10px] text-slate-500">رحلة</span>
            </div>
        </div>

        <div class="p-3 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col gap-1 shadow-sm">
            <span class="text-[10px] text-slate-400 font-bold">إجمالي الدخل</span>
            <div class="flex items-baseline justify-center gap-0.5">
                <span class="text-xl font-black text-teal-600 dark:text-teal-400 font-mono" x-text="stats.total_earned_ils || 0"></span>
                <span class="text-[10px] font-bold text-teal-700 dark:text-teal-300">₪</span>
            </div>
        </div>

        <div class="p-3 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col gap-1 shadow-sm">
            <span class="text-[10px] text-slate-400 font-bold">متوسط التقييم</span>
            <div class="flex items-baseline justify-center gap-1">
                <span class="text-amber-500 text-xs">★</span>
                <span class="text-xl font-black text-slate-800 dark:text-slate-100 font-mono" x-text="stats.average_rating || '5.0'"></span>
            </div>
            <span class="text-[9px] text-slate-400" x-text="'(' + (stats.ratings_count || 0) + ' تقييم)'"></span>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading && trips.length === 0" class="p-12 text-center text-slate-400 text-xs">
        <span class="text-3xl block mb-2 animate-bounce">⏳</span>
        جاري تحميل سجل الرحلات والأرباح...
    </div>

    <!-- Empty State -->
    <div x-show="!loading && trips.length === 0" class="p-8 text-center bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 flex flex-col items-center gap-3">
        <span class="text-4xl">📭</span>
        <h3 class="font-bold text-sm text-slate-700 dark:text-slate-200">لا توجد رحلات مكتملة بعد</h3>
        <p class="text-xs text-slate-400 max-w-xs">ستظهر هنا تفاصيل أي رحلة تقوم بإتمامها وإيصال ركابها على خط السير أو في المشاوير الخاصة مع تقييمات الركاب.</p>
        <a href="{{ route('driver.dashboard') }}" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow-sm transition">
            الانتقال للوحة السائق
        </a>
    </div>

    <!-- Completed Trips List -->
    <div class="flex flex-col gap-4">
        <template x-for="trip in trips" :key="trip.trip_type + '_' + trip.id">
            <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col gap-3 shadow-sm">
                <!-- Trip Header: Route & Type -->
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-black text-sm text-slate-800 dark:text-slate-100" x-text="trip.origin"></span>
                            <span class="text-slate-400 text-xs">➔</span>
                            <span class="font-black text-sm text-slate-800 dark:text-slate-100" x-text="trip.destination"></span>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-slate-400 mt-0.5">
                            <span class="font-mono" x-text="trip.date_arabic"></span>
                            <span>•</span>
                            <span>الراكب: <b class="text-slate-600 dark:text-slate-300" x-text="trip.rider_name"></b></span>
                        </div>
                    </div>

                    <div class="text-left">
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold inline-block mb-1"
                            :class="trip.trip_type === 'special' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300'"
                            x-text="trip.type_arabic">
                        </span>
                        <div class="flex items-baseline justify-end gap-0.5 text-teal-600 dark:text-teal-400 font-black">
                            <span class="text-base font-mono" x-text="trip.fare_ils"></span>
                            <span class="text-xs">₪</span>
                        </div>
                    </div>
                </div>

                <!-- Rider's Rating & Review -->
                <template x-if="trip.rating">
                    <div class="p-3 rounded-2xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-800/40 flex flex-col gap-1 text-xs">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1 text-amber-500 text-sm">
                                <template x-for="star in 5" :key="star">
                                    <span :class="star <= trip.rating.rating ? 'text-amber-500' : 'text-slate-300 dark:text-slate-600'">★</span>
                                </template>
                                <span class="font-mono font-bold text-xs text-amber-700 dark:text-amber-300 ms-1" x-text="trip.rating.rating + '/5'"></span>
                            </div>
                            <span class="text-[10px] text-slate-400 font-mono" x-text="trip.rating.created_at ? new Date(trip.rating.created_at).toLocaleDateString('ar-EG') : ''"></span>
                        </div>
                        <template x-if="trip.rating.comment">
                            <p class="text-slate-600 dark:text-slate-300 italic text-[11px]" x-text="'تعليق الراكب: ' + trip.rating.comment"></p>
                        </template>
                    </div>
                </template>

                <template x-if="!trip.rating">
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-800 text-[11px] text-slate-400 text-center">
                        في انتظار تقييم الراكب لهذه الرحلة
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>

<script>
function driverHistoryState() {
    return {
        trips: [],
        stats: {
            total_completed_trips: 0,
            total_earned_ils: 0,
            average_rating: '5.0',
            ratings_count: 0,
        },
        loading: true,
        isOffline: !navigator.onLine,

        init() {
            // Load cached version first
            const cached = localStorage.getItem('meshwar_driver_history_cache');
            if (cached) {
                try {
                    const data = JSON.parse(cached);
                    this.trips = data.trips || [];
                    this.stats = data.stats || this.stats;
                    this.loading = false;
                } catch(e) {}
            }

            this.fetchHistory();

            window.addEventListener('online', () => {
                this.isOffline = false;
                this.fetchHistory();
            });

            window.addEventListener('offline', () => {
                this.isOffline = true;
            });
        },

        async fetchHistory() {
            try {
                const res = await fetch('{{ route('api.driver.history') }}');
                if (res.ok) {
                    const data = await res.json();
                    this.trips = data.trips || [];
                    this.stats = data.stats || this.stats;
                    localStorage.setItem('meshwar_driver_history_cache', JSON.stringify(data));
                }
            } catch (err) {
                console.warn('Driver viewing trip history offline', err);
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
@endsection
