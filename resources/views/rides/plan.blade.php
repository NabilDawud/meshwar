@extends('layouts.app')

@push('scripts')
<script src="/js/offline-planner.js"></script>
@endpush

@section('content')
<div class="flex-1 flex flex-col gap-4 p-4" x-data="tripPlannerView()">
    <!-- Header -->
    <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-700">
        <div>
            <h1 class="text-base font-black text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                <span>🧭</span>
                <span>مخطط الرحلات والتبديل (غزة)</span>
            </h1>
            <p class="text-[11px] text-slate-400">حساب أفضل مسار (مباشر أو بتبديل واحد)، الأجرة، ووقت الانتظار — يعمل بدون إنترنت</p>
        </div>
        <a href="{{ route('home') }}" class="text-xs font-bold text-teal-600 dark:text-teal-400">
            الخريطة الحية ←
        </a>
    </div>

    <!-- Search / Plan Form -->
    <div class="p-4 rounded-3xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 shadow-sm flex flex-col gap-3">
        <!-- Origin -->
        <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1">
                <span>🟢</span>
                <span>نقطة الانطلاق (من):</span>
            </label>
            <select 
                x-model="from" 
                class="h-11 px-3 rounded-2xl border-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
            >
                <option value="">-- اختر نقطة البداية --</option>
                <template x-for="item in landmarks" :key="item.id">
                    <option :value="item.name_ar" x-text="item.name_ar + ' (' + item.municipality + ')'"></option>
                </template>
            </select>
        </div>

        <!-- Destination -->
        <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1">
                <span>🏁</span>
                <span>وجهة الوصول (إلى):</span>
            </label>
            <select 
                x-model="to" 
                class="h-11 px-3 rounded-2xl border-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
            >
                <option value="">-- اختر وجهة الوصول --</option>
                <template x-for="item in landmarks" :key="item.id">
                    <option :value="item.name_ar" x-text="item.name_ar + ' (' + item.municipality + ')'"></option>
                </template>
            </select>
        </div>

        <!-- Plan Button -->
        <button 
            type="button" 
            @click="planTrip" 
            :disabled="!from || !to || isPlanning"
            class="mt-1 h-12 bg-teal-600 hover:bg-teal-700 active:scale-[0.98] disabled:opacity-40 text-white font-bold rounded-2xl text-xs sm:text-sm transition shadow-sm flex items-center justify-center gap-2"
        >
            <span x-show="!isPlanning">تخطيط المسار وعرض الخيارات</span>
            <span x-show="isPlanning">جاري البحث في شبكة الخطوط...</span>
        </button>
    </div>

    <!-- AI Advice Box (When available) -->
    <div x-show="planResult && planResult.ai_advice && planResult.ai_advice.tip" class="p-3.5 rounded-2xl bg-teal-50 dark:bg-teal-950/60 border border-teal-200 dark:border-teal-800 text-xs text-teal-800 dark:text-teal-200 flex items-start gap-2.5">
        <span class="text-lg">💡</span>
        <div class="flex-1 leading-relaxed">
            <span class="font-bold">نصيحة مسار مشوار:</span>
            <span x-text="planResult?.ai_advice?.tip"></span>
        </div>
    </div>

    <!-- Results Container -->
    <div x-show="planResult">
        <!-- Found Itineraries -->
        <div x-show="planResult && planResult.found && planResult.itineraries.length > 0" class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                    الخيارات المتاحة للرحلة (<span x-text="planResult?.itineraries?.length"></span>):
                </span>
                <span class="text-[10px] text-slate-400" x-show="planResult?.offline">محسوب محلياً بدون إنترنت ⚡</span>
            </div>

            <template x-for="(itin, idx) in planResult?.itineraries" :key="itin.id || idx">
                <div class="p-4 rounded-3xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 shadow-sm flex flex-col gap-3">
                    <!-- Itinerary Header -->
                    <div class="flex items-center justify-between">
                        <!-- Direct vs Transfer Badge -->
                        <span 
                            class="text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1"
                            :class="itin.transfers === 0 ? 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'"
                        >
                            <span x-text="itin.transfers === 0 ? '🟢 رحلة مباشرة' : '🔄 رحلة بمركبتين (تبديل واحد)'"></span>
                        </span>

                        <!-- Total Fare -->
                        <div class="flex items-center gap-1 font-mono font-black text-sm text-teal-600 dark:text-teal-400">
                            <span>إجمالي الأجرة:</span>
                            <span class="text-base" x-text="itin.total_fare_ils"></span>
                            <span>₪</span>
                        </div>
                    </div>

                    <!-- Direct Trip Details -->
                    <div x-show="itin.transfers === 0" class="flex flex-col gap-2 p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-xs">
                        <div class="flex items-center justify-between font-bold text-slate-800 dark:text-slate-100">
                            <span x-text="itin.route_name"></span>
                            <span class="text-slate-500 font-mono">⏰ انطلاق: <span x-text="itin.departure_time"></span></span>
                        </div>
                        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            <span>🚗 المركبة: <span class="font-bold" x-text="itin.vehicle?.type_arabic"></span></span>
                            <span>•</span>
                            <span>السائق: <span class="font-bold" x-text="itin.vehicle?.driver_name"></span></span>
                        </div>
                    </div>

                    <!-- Transfer Trip Details (2 Vehicles) -->
                    <div x-show="itin.transfers === 1" class="flex flex-col gap-2.5">
                        <!-- Transfer Junction Banner -->
                        <div class="p-2.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-amber-900 dark:text-amber-200">
                                <span>🔄</span>
                                <span>محطة التبديل: <strong x-text="itin.transfer_junction"></strong></span>
                            </div>
                            <span class="font-bold text-[11px] text-amber-700 dark:text-amber-300" x-text="itin.transfer_wait_arabic || ('انتظار ' + itin.transfer_wait_minutes + ' دقيقة')"></span>
                        </div>

                        <!-- Leg 1 -->
                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-xs flex flex-col gap-1">
                            <div class="flex items-center justify-between font-bold text-slate-800 dark:text-slate-100">
                                <span>1️⃣ المرحلة الأولى: <span x-text="itin.leg_1?.from + ' ← ' + itin.leg_1?.to"></span></span>
                                <span class="font-mono text-teal-600 dark:text-teal-400 font-bold" x-text="itin.leg_1?.fare_ils + ' ₪'"></span>
                            </div>
                            <span class="text-slate-500 text-[11px]" x-text="'عبر: ' + itin.leg_1?.route_name + ' (' + itin.leg_1?.vehicle?.type_arabic + ')'"></span>
                        </div>

                        <!-- Leg 2 -->
                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-xs flex flex-col gap-1">
                            <div class="flex items-center justify-between font-bold text-slate-800 dark:text-slate-100">
                                <span>2️⃣ المرحلة الثانية: <span x-text="itin.leg_2?.from + ' ← ' + itin.leg_2?.to"></span></span>
                                <span class="font-mono text-teal-600 dark:text-teal-400 font-bold" x-text="itin.leg_2?.fare_ils + ' ₪'"></span>
                            </div>
                            <span class="text-slate-500 text-[11px]" x-text="'عبر: ' + itin.leg_2?.route_name + ' (' + itin.leg_2?.vehicle?.type_arabic + ')'"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- No Route Found State -->
        <div x-show="planResult && !planResult.found" class="p-8 rounded-3xl border-2 border-dashed border-rose-200 dark:border-rose-900 bg-rose-50/50 dark:bg-rose-950/20 text-center flex flex-col items-center gap-2 text-xs">
            <span class="text-3xl">🚫</span>
            <span class="font-bold text-rose-800 dark:text-rose-300">لا يوجد مسار متاح حالياً</span>
            <p class="text-rose-600 dark:text-rose-400 text-[11px] leading-relaxed max-w-xs" x-text="planResult?.message"></p>
        </div>
    </div>
</div>

@push('scripts')
<script>
function tripPlannerView() {
    return {
        landmarks: [],
        from: '',
        to: '',
        isPlanning: false,
        planResult: null,

        async init() {
            try {
                const res = await fetch('{{ route('api.gazetteer') }}');
                const data = await res.json();
                if (data.ok) {
                    this.landmarks = data.landmarks;
                }
            } catch (e) {
                console.warn('Using cached gazetteer', e);
            }
        },

        async planTrip() {
            if (!this.from || !this.to) return;
            this.isPlanning = true;
            this.planResult = null;

            try {
                // Online search via backend
                const url = `{{ route('api.rides.plan') }}?from=${encodeURIComponent(this.from)}&to=${encodeURIComponent(this.to)}`;
                const res = await fetch(url);
                const data = await res.json();
                this.planResult = data;
            } catch (err) {
                console.warn('Network offline, using client-side graph pathfinding', err);
                if (window.offlinePlanner) {
                    this.planResult = window.offlinePlanner.plan(this.from, this.to);
                } else {
                    this.planResult = { ok: true, found: false, message: 'تعذر الاتصال بالخادم ولم يتم تحميل المخطط المحلي بعد' };
                }
            } finally {
                this.isPlanning = false;
            }
        }
    };
}
</script>
@endpush
@endsection
