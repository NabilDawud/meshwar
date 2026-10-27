@extends('layouts.app')

@section('title', 'سجل رحلاتي - مشوار')

@section('content')
<div class="p-4 flex flex-col gap-4 max-w-lg mx-auto" x-data="riderHistoryState()" x-init="init()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span>📜</span>
                <span>سجل رحلاتي المكتملة</span>
            </h1>
            <p class="text-xs text-slate-500">تفاصيل رحلاتك السابقة وتقييم أداء السائقين</p>
        </div>
        <button @click="fetchHistory()" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition">
            🔄 تحديث
        </button>
    </div>

    <!-- Offline Alert -->
    <div x-show="isOffline" x-cloak class="p-3 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 text-xs font-bold flex items-center gap-2">
        <span>⚠️</span>
        <span>أنت غير متصل بالإنترنت. يتم عرض سجل الرحلات المحفوظ محلياً.</span>
    </div>

    <!-- Outbox Banner (Pending Ratings Waiting to Sync) -->
    <div x-show="outboxRatings.length > 0" x-cloak class="p-4 rounded-2xl bg-teal-50 dark:bg-teal-950/40 border border-teal-300 dark:border-teal-700 text-teal-800 dark:text-teal-200 text-xs flex flex-col gap-1">
        <span class="font-bold flex items-center gap-1.5">
            <span class="animate-pulse">⏳</span>
            <span>تقييمات في انتظار الإرسال (<span x-text="outboxRatings.length"></span>)</span>
        </span>
        <span class="text-[11px] text-teal-700 dark:text-teal-300">
            تم حفظ تقييمك محلياً على هاتفك. سيتم إرساله وتحديث متوسط تقييم السائق فور الاتصال.
        </span>
    </div>

    <!-- Success Toast -->
    <div x-show="successMessage" x-cloak class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200 text-xs font-bold flex items-center justify-between">
        <span x-text="successMessage"></span>
        <button @click="successMessage = null" class="px-2">✕</button>
    </div>

    <!-- Summary Stats Card -->
    <div class="grid grid-cols-2 gap-3">
        <div class="p-4 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col gap-1 shadow-sm">
            <span class="text-[11px] text-slate-400 font-bold">الرحلات المكتملة</span>
            <div class="flex items-baseline gap-1">
                <span class="text-2xl font-black text-slate-800 dark:text-slate-100 font-mono" x-text="totalTrips"></span>
                <span class="text-xs text-slate-500">رحلة</span>
            </div>
        </div>
        <div class="p-4 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col gap-1 shadow-sm">
            <span class="text-[11px] text-slate-400 font-bold">إجمالي المصروفات</span>
            <div class="flex items-baseline gap-1">
                <span class="text-2xl font-black text-teal-600 dark:text-teal-400 font-mono" x-text="totalSpent"></span>
                <span class="text-xs font-bold text-teal-700 dark:text-teal-300">₪</span>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading && trips.length === 0" class="p-12 text-center text-slate-400 text-xs">
        <span class="text-3xl block mb-2 animate-bounce">⏳</span>
        جاري تحميل سجل الرحلات...
    </div>

    <!-- Empty State -->
    <div x-show="!loading && trips.length === 0" class="p-8 text-center bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 flex flex-col items-center gap-3">
        <span class="text-4xl">🚕</span>
        <h3 class="font-bold text-sm text-slate-700 dark:text-slate-200">لا توجد رحلات مكتملة بعد</h3>
        <p class="text-xs text-slate-400 max-w-xs">ستظهر هنا تفاصيل أي رحلة تقوم بإتمامها سواء عبر خطوط السير العامة أو المشاوير الخاصة، لتتمكن من تقييم السائق.</p>
        <a href="{{ route('home') }}" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow-sm transition">
            استكشف الرحلات المتاحة
        </a>
    </div>

    <!-- Trips History List -->
    <div class="flex flex-col gap-4">
        <template x-for="trip in trips" :key="trip.trip_type + '_' + trip.id">
            <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col gap-3.5 shadow-sm">
                <!-- Trip Header: Route & Type -->
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-black text-sm text-slate-800 dark:text-slate-100" x-text="trip.origin"></span>
                            <span class="text-slate-400 text-xs">➔</span>
                            <span class="font-black text-sm text-slate-800 dark:text-slate-100" x-text="trip.destination"></span>
                        </div>
                        <span class="text-[11px] text-slate-400 mt-0.5 block font-mono" x-text="trip.date_arabic"></span>
                    </div>

                    <span class="text-[10px] px-2.5 py-1 rounded-full font-bold"
                        :class="trip.trip_type === 'special' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300'"
                        x-text="trip.type_arabic">
                    </span>
                </div>

                <!-- Driver & Vehicle Details -->
                <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="w-10 h-10 rounded-xl bg-teal-100 dark:bg-teal-900/50 flex items-center justify-center text-lg">
                            👨‍✈️
                        </span>
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-slate-800 dark:text-slate-200" x-text="trip.driver ? trip.driver.name : 'السائق'"></span>
                                <template x-if="trip.driver && trip.driver.rating">
                                    <span class="text-[10px] text-amber-500 font-mono font-bold">★ <span x-text="trip.driver.rating"></span></span>
                                </template>
                            </div>
                            <template x-if="trip.vehicle">
                                <span class="text-[11px] text-slate-400" x-text="trip.vehicle.type_arabic + ' · لوحة: ' + trip.vehicle.license_plate"></span>
                            </template>
                        </div>
                    </div>

                    <!-- Fare -->
                    <div class="text-left">
                        <div class="flex items-baseline gap-0.5 text-slate-800 dark:text-slate-100 font-black">
                            <span class="text-base font-mono" x-text="trip.fare_ils"></span>
                            <span class="text-xs">₪</span>
                        </div>
                        <span class="text-[10px] text-slate-400 block" x-text="trip.seats + ' مقاعد'"></span>
                    </div>
                </div>

                <!-- Rating Section -->
                <!-- Case A: Trip Already Rated -->
                <template x-if="trip.rating">
                    <div class="p-3 rounded-2xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-800/40 flex flex-col gap-1.5 text-xs">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1 text-amber-500 text-sm">
                                <template x-for="star in 5" :key="star">
                                    <span :class="star <= trip.rating.rating ? 'text-amber-500' : 'text-slate-300 dark:text-slate-600'">★</span>
                                </template>
                                <span class="font-mono font-bold text-xs text-amber-700 dark:text-amber-300 ms-1" x-text="trip.rating.rating + '/5'"></span>
                            </div>
                            <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">تم تقييم الرحلة ✓</span>
                        </div>
                        <template x-if="trip.rating.comment">
                            <p class="text-slate-600 dark:text-slate-300 italic text-[11px]" x-text="'رأيك: ' + trip.rating.comment"></p>
                        </template>
                    </div>
                </template>

                <!-- Case B: Unrated Completed Trip (Interactive Rating Selector) -->
                <template x-if="trip.can_rate">
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 flex flex-col gap-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1">
                                <span>⭐</span>
                                <span>كيف كانت تجربتك مع السائق؟</span>
                            </span>
                            <span class="text-[11px] text-teal-600 dark:text-teal-400 font-bold" x-text="getRatingLabel(getRatingValue(trip))"></span>
                        </div>

                        <!-- 1-5 Star Picker -->
                        <div class="flex items-center justify-center gap-2 py-1 text-2xl select-none">
                            <template x-for="star in 5" :key="star">
                                <button type="button" @click="setTripRating(trip, star)" class="transition transform hover:scale-125 focus:outline-none" :class="star <= getRatingValue(trip) ? 'text-amber-400 drop-shadow-sm' : 'text-slate-300 dark:text-slate-600'">
                                    ★
                                </button>
                            </template>
                        </div>

                        <!-- Optional Feedback Input -->
                        <input 
                            type="text" 
                            x-model="trip.newComment" 
                            placeholder="اكتب ملاحظة أو إشادة بالسائق (اختياري)..." 
                            class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-800 dark:text-slate-100 outline-none focus:ring-2 focus:ring-teal-500"
                            maxlength="500"
                        >

                        <button 
                            @click="submitRating(trip)" 
                            :disabled="getRatingValue(trip) === 0 || trip.submitting" 
                            class="w-full py-2 bg-teal-600 hover:bg-teal-700 disabled:opacity-40 text-white font-bold text-xs rounded-xl shadow-sm transition active:scale-95 flex items-center justify-center gap-1.5"
                        >
                            <span x-show="!trip.submitting">إرسال التقييم</span>
                            <span x-show="trip.submitting" class="animate-spin">⏳</span>
                        </button>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- Pagination Controls (Powered directly by Laravel Controller Pagination) -->
    <div x-show="pagination.last_page > 1" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3.5 bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm text-xs mt-1">
        <div class="text-slate-500 font-medium">
            عرض <span class="font-black text-slate-800 dark:text-slate-200 font-mono" x-text="pagination.from || 1"></span> إلى <span class="font-black text-slate-800 dark:text-slate-200 font-mono" x-text="pagination.to || trips.length"></span> من أصل <span class="font-black text-teal-600 dark:text-teal-400 font-mono" x-text="pagination.total"></span> رحلة
        </div>
        <div class="flex items-center gap-1.5">
            <button 
                @click="changePage(pagination.current_page - 1)" 
                :disabled="pagination.current_page <= 1"
                class="px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 font-bold hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition"
            >
                السابق
            </button>
            <span class="px-3 py-1 rounded-xl bg-slate-100 dark:bg-slate-700 font-bold font-mono text-teal-700 dark:text-teal-300">
                <span x-text="pagination.current_page"></span> / <span x-text="pagination.last_page"></span>
            </span>
            <button 
                @click="changePage(pagination.current_page + 1)" 
                :disabled="pagination.current_page >= pagination.last_page"
                class="px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 font-bold hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition"
            >
                التالي
            </button>
        </div>
    </div>
</div>

<script>
function riderHistoryState() {
    return {
        trips: [],
        totalTrips: 0,
        totalSpent: 0,
        loading: true,
        successMessage: null,
        isOffline: !navigator.onLine,
        outboxRatings: [],
        pagination: {
            current_page: 1,
            last_page: 1,
            total: 0,
            from: 1,
            to: 10
        },

        changePage(p) {
            if (p >= 1 && p <= this.pagination.last_page) {
                this.fetchHistory(p);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        init() {
            this.loadOutbox();

            // Load cached history first for instant offline access
            const cached = localStorage.getItem('meshwar_rider_history_cache');
            if (cached) {
                try {
                    const data = JSON.parse(cached);
                    this.trips = data.trips || [];
                    this.totalTrips = data.total_trips || 0;
                    this.totalSpent = data.total_spent_ils || 0;
                    this.loading = false;
                } catch(e) {}
            }

            this.fetchHistory();

            window.addEventListener('online', () => {
                this.isOffline = false;
                this.syncOutbox();
                this.fetchHistory();
            });

            window.addEventListener('offline', () => {
                this.isOffline = true;
            });
        },

        loadOutbox() {
            const raw = localStorage.getItem('meshwar_ratings_outbox');
            this.outboxRatings = raw ? JSON.parse(raw) : [];
        },

        saveOutbox() {
            localStorage.setItem('meshwar_ratings_outbox', JSON.stringify(this.outboxRatings));
        },

        getRatingValue(trip) {
            return trip.newRating || 5;
        },

        setTripRating(trip, star) {
            trip.newRating = star;
        },

        getRatingLabel(star) {
            switch(star) {
                case 1: return 'سيء جداً';
                case 2: return 'سيء';
                case 3: return 'مقبول';
                case 4: return 'جيد جداً';
                case 5: return 'ممتاز ومريح';
                default: return 'اختر التقييم';
            }
        },

        async fetchHistory(page = 1) {
            try {
                const res = await fetch(`{{ route('api.rider.history') }}?page=${page}`);
                if (res.ok) {
                    const data = await res.json();
                    this.trips = (data.trips || []).map(t => ({
                        ...t,
                        newRating: 5,
                        newComment: '',
                        submitting: false,
                    }));
                    this.totalTrips = data.total_trips;
                    this.totalSpent = data.total_spent_ils;
                    this.pagination = {
                        current_page: data.current_page || page,
                        last_page: data.last_page || 1,
                        total: data.total ?? data.total_trips,
                        from: data.from || 1,
                        to: data.to || (data.trips || []).length
                    };
                    localStorage.setItem('meshwar_rider_history_cache', JSON.stringify(data));
                }
            } catch (err) {
                console.warn('Viewing trip history offline', err);
            } finally {
                this.loading = false;
            }
        },

        async submitRating(trip) {
            trip.submitting = true;
            const ratingVal = trip.newRating || 5;
            const commentVal = trip.newComment || null;
            const mutationId = 'mut_rate_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7);

            const payload = {
                trip_type: trip.trip_type,
                trip_id: trip.id,
                rating: ratingVal,
                comment: commentVal,
                client_mutation_id: mutationId,
            };

            // If offline, save in outbox immediately
            if (!navigator.onLine) {
                this.outboxRatings.push(payload);
                this.saveOutbox();

                // Optimistically update trip card
                trip.rating = {
                    rating: ratingVal,
                    comment: commentVal,
                    created_at: new Date().toISOString()
                };
                trip.can_rate = false;
                trip.submitting = false;
                this.successMessage = 'تم حفظ التقييم محلياً. سيتم تسجيله فور الاتصال بالإنترنت.';
                return;
            }

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('{{ route('api.trips.rate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok && data.ok) {
                    trip.rating = data.rating;
                    trip.can_rate = false;
                    this.successMessage = '🎉 شكراً لك! تم تسجيل تقييمك للسائق بنجاح.';
                    this.fetchHistory();
                } else {
                    alert(data.error || 'حدث خطأ أثناء إرسال التقييم');
                }
            } catch (err) {
                this.outboxRatings.push(payload);
                this.saveOutbox();
                trip.rating = {
                    rating: ratingVal,
                    comment: commentVal,
                    created_at: new Date().toISOString()
                };
                trip.can_rate = false;
                this.successMessage = 'تم حفظ التقييم في صندوق الانتظار وسيتم إرساله عند استقرار الاتصال.';
            } finally {
                trip.submitting = false;
            }
        },

        async syncOutbox() {
            if (this.outboxRatings.length === 0) return;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const remaining = [];

            for (const item of this.outboxRatings) {
                try {
                    const res = await fetch('{{ route('api.trips.rate') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken || '',
                        },
                        body: JSON.stringify(item)
                    });
                    if (!res.ok) remaining.push(item);
                } catch(e) {
                    remaining.push(item);
                }
            }

            this.outboxRatings = remaining;
            this.saveOutbox();
            if (remaining.length === 0) {
                this.successMessage = '✓ تم إرسال جميع التقييمات المعلقة وتحديث بيانات السائقين!';
                this.fetchHistory();
            }
        }
    };
}
</script>
@endsection
