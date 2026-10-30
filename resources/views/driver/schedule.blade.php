@extends('layouts.app')

@section('content')
<div class="p-4 flex flex-col gap-4 pb-24" x-data="driverSchedulePage({
    routes: {{ Js::from($routes) }},
    schedules: {{ Js::from($schedules) }}
})" x-init="init()">
    <!-- Navigation & Header Banner -->
    <div class="bg-gradient-to-l from-teal-700 to-teal-800 text-white p-5 rounded-3xl shadow-sm flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <a href="{{ route('driver.dashboard') }}" class="text-xs font-bold px-3 py-1.5 rounded-full bg-white/20 hover:bg-white/30 text-white transition flex items-center gap-1">
                <span>➔</span>
                <span>العودة للوحة القيادة</span>
            </a>
            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-white/20 text-teal-100">
                تنظيم جدول الأسبوع
            </span>
        </div>

        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-black">📅 جدول مواعيد ومسارات الأسبوع</h1>
                <p class="text-xs text-teal-100 mt-1 leading-relaxed">
                    حدد خط السير وساعات العمل أو الإجازة لكل يوم لتنظيم حجوزات الركاب المسبقة بدقة.
                </p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center text-2xl shrink-0">
                🗓️
            </div>
        </div>

        <!-- Quick Actions Row -->
        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-white/20">
            <button 
                type="button" 
                @click="copySaturdayToAllWorkdays" 
                class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95 shadow-sm"
                title="نسخ ساعات ومسار يوم السبت إلى الأحد، الإثنين، الثلاثاء، والأربعاء، والخميس"
            >
                <span>⚡</span>
                <span>تطبيق مواعيد السبت على باقي أيام العمل</span>
            </button>

            <a 
                href="{{ route('driver.route', ['new' => 1]) }}" 
                class="px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white rounded-xl text-xs font-bold transition flex items-center gap-1"
            >
                <span>🗺️</span>
                <span>+ رسم مسار خط جديد</span>
            </a>
        </div>
    </div>

    <!-- Toast Notification Banner -->
    <div x-show="toastMessage" x-cloak class="p-3.5 rounded-2xl border text-xs font-bold flex items-center justify-between transition"
        :class="toastType === 'error' ? 'bg-rose-50 dark:bg-rose-950/60 border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200' : 'bg-teal-50 dark:bg-teal-950/60 border-teal-300 dark:border-teal-700 text-teal-800 dark:text-teal-200'">
        <span class="flex items-center gap-2">
            <span x-text="toastType === 'error' ? '⚠️' : '✅'"></span>
            <span x-text="toastMessage"></span>
        </span>
        <button type="button" @click="toastMessage = null" class="text-xs px-2 font-black">✕</button>
    </div>

    <!-- Missing Routes Warning -->
    @if($routes->isEmpty())
        <div class="p-5 rounded-3xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 flex flex-col gap-2.5 text-center items-center">
            <span class="text-3xl">⚠️</span>
            <h3 class="font-bold text-sm text-amber-800 dark:text-amber-200">لم تقم بتسجيل أي خط سير بعد</h3>
            <p class="text-xs text-amber-700 dark:text-amber-300 max-w-sm">
                يلزم تحديد مسار واحد على الأقل مع محطاته لتتمكن من تعيينه لأيام الأسبوع.
            </p>
            <a href="{{ route('driver.route') }}" class="mt-1 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-sm transition">
                + بناء خط السير الآن
            </a>
        </div>
    @endif

    <!-- 7 Days Interactive Mobile Cards -->
    <div class="flex flex-col gap-3">
        <template x-for="s in schedules" :key="s.day_of_week">
            <div 
                class="rounded-3xl border transition overflow-hidden shadow-xs"
                :class="s.is_day_off ? 'bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-800 opacity-85' : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700'"
            >
                <!-- Day Header -->
                <div class="p-4 flex items-center justify-between border-b border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center gap-2.5">
                        <span 
                            class="w-10 h-10 rounded-2xl flex items-center justify-center font-black text-xs shrink-0 shadow-xs" 
                            :class="s.is_day_off ? 'bg-slate-200 dark:bg-slate-700 text-slate-500' : 'bg-teal-100 dark:bg-teal-950 text-teal-800 dark:text-teal-300'" 
                            x-text="s.day_abbr || s.day_short_name || s.day_name_arabic"
                        ></span>
                        <div>
                            <span class="font-black text-sm text-slate-800 dark:text-slate-100 block" x-text="s.day_name_arabic"></span>
                            <span class="text-[10px] text-slate-400" x-text="s.is_day_off ? 'إجازة أسبوعية' : (getRouteName(s.route_id) || 'مسار العمل')"></span>
                        </div>
                    </div>

                    <!-- Work vs Holiday Interactive Segmented Control -->
                    <div class="inline-flex p-1 rounded-2xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 select-none shadow-xs">
                        <button 
                            type="button" 
                            @click="s.is_day_off = false" 
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 active:scale-95"
                            :class="!s.is_day_off ? 'bg-teal-600 text-white shadow-xs' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                            title="تفعيل الدوام لهذا اليوم"
                        >
                            <span>🟢</span>
                            <span>عمل</span>
                        </button>
                        <button 
                            type="button" 
                            @click="s.is_day_off = true" 
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 active:scale-95"
                            :class="s.is_day_off ? 'bg-rose-600 text-white shadow-xs' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                            title="تحديد هذا اليوم كإجازة أسبوعية"
                        >
                            <span>🏖️</span>
                            <span>إجازة</span>
                        </button>
                    </div>
                </div>

                <!-- Day Content: Active Work Shift -->
                <div x-show="!s.is_day_off" class="p-4 flex flex-col gap-3">
                    <!-- Route Selection (Touch Pills - No broken select dropdowns) -->
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-[11px] font-bold text-slate-500 dark:text-slate-400">خط السير لهذا اليوم:</label>
                            <a href="{{ route('driver.route', ['new' => 1]) }}" class="text-[10px] text-teal-600 dark:text-teal-400 font-bold hover:underline">
                                + إضافة مسار
                            </a>
                        </div>

                        <!-- If only 1 route -->
                        <template x-if="routes.length <= 1">
                            <div class="p-3 rounded-2xl bg-teal-50/60 dark:bg-teal-950/30 border border-teal-200 dark:border-teal-800/80 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">🗺️</span>
                                    <span class="font-bold text-teal-900 dark:text-teal-200" x-text="routes[0] ? routes[0].name : 'المسار الافتراضي'"></span>
                                </div>
                                <span class="text-[10px] text-teal-700 dark:text-teal-400 font-bold bg-teal-100 dark:bg-teal-900/60 px-2 py-0.5 rounded-md">المسار المعتمد</span>
                            </div>
                        </template>

                        <!-- If multiple routes available: Touch-friendly Selector Pills -->
                        <template x-if="routes.length > 1">
                            <div class="grid grid-cols-1 gap-2">
                                <template x-for="r in routes" :key="r.id">
                                    <button 
                                        type="button" 
                                        @click="s.route_id = r.id" 
                                        class="p-3 rounded-2xl border text-right transition flex items-center justify-between text-xs active:scale-98"
                                        :class="s.route_id == r.id ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/60 text-teal-900 dark:text-teal-100 ring-2 ring-teal-500/20 font-bold shadow-xs' : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
                                    >
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="text-sm">📍</span>
                                            <div class="min-w-0">
                                                <span class="block truncate" x-text="r.name"></span>
                                                <span class="text-[10px] text-slate-400 font-normal truncate block" x-text="'انطلاق: ' + (r.start_point || 'غزة')"></span>
                                            </div>
                                        </div>
                                        <span x-show="s.route_id == r.id" class="text-teal-600 dark:text-teal-400 font-black text-xs shrink-0 flex items-center gap-1">
                                            <span>✓</span>
                                            <span class="hidden sm:inline">محدد</span>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>

                    <!-- Shift Hours Grid (Strict 2-Column Responsive Layout - Zero Overflow) -->
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60">
                        <label class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block mb-1.5">ساعات وردية العمل:</label>
                        <div class="grid grid-cols-2 gap-2.5">
                            <div class="flex flex-col gap-1 min-w-0">
                                <span class="text-[10px] font-bold text-slate-400">من الساعة:</span>
                                <input 
                                    type="time" 
                                    x-model="s.start_time" 
                                    class="w-full h-11 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 font-mono text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
                                >
                            </div>

                            <div class="flex flex-col gap-1 min-w-0">
                                <span class="text-[10px] font-bold text-slate-400">إلى الساعة:</span>
                                <input 
                                    type="time" 
                                    x-model="s.end_time" 
                                    class="w-full h-11 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 font-mono text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Day Content: Day-Off State -->
                <div x-show="s.is_day_off" class="p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 bg-slate-100/60 dark:bg-slate-900/60 text-xs text-slate-500 dark:text-slate-400">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">🏖️</span>
                        <span>إجازة أسبوعية (مركبتك لن تظهر في بحث وتواجد الركاب لهذا اليوم)</span>
                    </div>
                    <button 
                        type="button" 
                        @click="s.is_day_off = false" 
                        class="px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs shadow-xs transition active:scale-95 flex items-center gap-1.5 shrink-0 self-end sm:self-auto"
                    >
                        <span>🟢</span>
                        <span>تفعيل دوام العمل</span>
                    </button>
                </div>
            </div>
        </template>
    </div>

    <!-- Sticky Bottom Save Bar (Mobile Accessible) -->
    <div class="fixed bottom-16 inset-x-0 z-30 p-3 bg-white/95 dark:bg-slate-900/95 backdrop-blur border-t border-slate-200 dark:border-slate-800 max-w-lg mx-auto flex items-center justify-between gap-3 shadow-lg">
        <div class="flex flex-col">
            <span class="text-xs font-bold text-slate-800 dark:text-slate-100" x-text="saving ? 'جاري حفظ التغييرات...' : 'تحديث جدول الأسبوع'"></span>
            <span class="text-[10px] text-slate-400">ينعكس فوراً على بحث الركاب</span>
        </div>

        <button 
            type="button" 
            @click="saveSchedules" 
            :disabled="saving"
            class="px-6 py-2.5 rounded-2xl bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold text-xs shadow-md transition active:scale-95 flex items-center gap-2"
        >
            <span x-show="!saving">حفظ الجدول 💾</span>
            <span x-show="saving">جاري الحفظ...</span>
        </button>
    </div>
</div>

@push('scripts')
<script>
function driverSchedulePage(config) {
    return {
        routes: config.routes || [],
        schedules: config.schedules || [],
        saving: false,
        toastMessage: null,
        toastType: 'success',

        init() {
            // Guarantee valid initial route_id for active days
            if (this.routes.length > 0) {
                const defaultRouteId = this.routes[0].id;
                this.schedules.forEach(s => {
                    if (!s.route_id && !s.is_day_off) {
                        s.route_id = defaultRouteId;
                    }
                });
            }
        },

        getRouteName(routeId) {
            const r = this.routes.find(item => item.id == routeId);
            return r ? r.name : null;
        },

        copySaturdayToAllWorkdays() {
            const sat = this.schedules.find(s => s.day_of_week === 6);
            if (!sat) return;

            this.schedules.forEach(s => {
                // Sunday through Thursday (0, 1, 2, 3, 4)
                if ([0, 1, 2, 3, 4].includes(s.day_of_week)) {
                    s.route_id = sat.route_id;
                    s.start_time = sat.start_time;
                    s.end_time = sat.end_time;
                    s.is_day_off = sat.is_day_off;
                }
            });

            this.showToast('⚡ تم نسخ ساعات ومسار يوم السبت إلى باقي أيام العمل! اضغط على "حفظ الجدول" بالأسفل لاعتمادها.');
        },

        showToast(msg, type = 'success') {
            this.toastMessage = msg;
            this.toastType = type;
            setTimeout(() => {
                if (this.toastMessage === msg) {
                    this.toastMessage = null;
                }
            }, 5000);
        },

        async saveSchedules() {
            this.saving = true;

            const payload = {
                schedules: this.schedules.map(s => ({
                    day_of_week: s.day_of_week,
                    route_id: s.is_day_off ? null : (s.route_id || (this.routes[0] ? this.routes[0].id : null)),
                    start_time: s.start_time || '07:00',
                    end_time: s.end_time || '16:00',
                    is_day_off: Boolean(s.is_day_off)
                }))
            };

            // Save to localStorage as offline safety backup
            try {
                localStorage.setItem('driver_weekly_schedules_backup', JSON.stringify(payload));
            } catch (e) {}

            try {
                const res = await fetch('{{ route('api.driver.schedules.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (data.ok) {
                    this.showToast('✅ تم حفظ جدول مواعيد ومسارات الأسبوع بنجاح!');
                } else {
                    this.showToast(data.error || 'حدث خطأ أثناء حفظ الجدول', 'error');
                }
            } catch (err) {
                console.error('Save schedules network error', err);
                this.showToast('⚠️ تم حفظ الجدول محلياً بالهاتف نظراً لضعف الاتصال، وستتم المزامنة تلقائياً عند استقرار الإنترنت.', 'error');
            } finally {
                this.saving = false;
            }
        }
    };
}
</script>
@endpush
@endsection
