@extends('layouts.app')

@section('content')
<div class="p-4 flex flex-col gap-4" x-data="driverDashboard({
    status: '{{ $route->status ?? 'on_route' }}',
    statusArabic: '{{ $route->status_arabic ?? 'عامل على الخط المعتاد' }}'
})">
    <!-- Header Banner -->
    <div class="bg-gradient-to-l from-amber-600 to-amber-700 text-white p-5 rounded-3xl shadow-sm flex items-center justify-between">
        <div>
            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-white/20 text-white inline-block mb-1.5">
                لوحة قيادة السائق
            </span>
            <h1 class="text-xl font-black">أهلاً بك، كابتن {{ Auth::user()->name }}</h1>
            <p class="text-xs text-amber-100 mt-0.5">هاتف التواصل: <span dir="ltr" class="font-mono font-semibold">{{ Auth::user()->phone ?? 'غير محدد' }}</span></p>
        </div>
        <div class="w-14 h-14 rounded-2xl bg-white/10 flex items-center justify-center text-3xl shrink-0">
            🚗
        </div>
    </div>

    <!-- Quick Operational Status Bar (Active State Machine) -->
    @if($route)
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-4 border border-slate-200 dark:border-slate-700 flex flex-col gap-2.5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">حالة التشغيل الحالية:</span>
                <span class="text-xs font-black px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300" x-text="statusArabic"></span>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button 
                    @click="updateStatus('on_route')"
                    :class="status === 'on_route' ? 'bg-teal-600 text-white font-bold border-teal-600' : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="py-2.5 px-3 rounded-2xl border text-xs flex flex-col items-center gap-1 transition active:scale-95"
                >
                    <span class="text-base">🟢</span>
                    <span>على الخط</span>
                </button>

                <button 
                    @click="updateStatus('temporary_route')"
                    :class="status === 'temporary_route' ? 'bg-amber-600 text-white font-bold border-amber-600' : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="py-2.5 px-3 rounded-2xl border text-xs flex flex-col items-center gap-1 transition active:scale-95"
                >
                    <span class="text-base">🔄</span>
                    <span>مسار مؤقت</span>
                </button>

                <button 
                    @click="updateStatus('off_route')"
                    :class="status === 'off_route' ? 'bg-rose-600 text-white font-bold border-rose-600' : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="py-2.5 px-3 rounded-2xl border text-xs flex flex-col items-center gap-1 transition active:scale-95"
                >
                    <span class="text-base">⏸️</span>
                    <span>خارج الخط</span>
                </button>

                <button 
                    @click="updateStatus('skip_work')"
                    :class="status === 'skip_work' ? 'bg-slate-600 text-white font-bold border-slate-600' : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="py-2.5 px-3 rounded-2xl border text-xs flex flex-col items-center gap-1 transition active:scale-95"
                >
                    <span class="text-base">🏖️</span>
                    <span>إجازة اليوم</span>
                </button>
            </div>
        </div>
    @endif

    <!-- Vehicle Card (Single-Vehicle 1:1 Rule) -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 border border-slate-200 dark:border-slate-700 flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-2xl">🛞</span>
                <div>
                    <h2 class="font-bold text-sm text-slate-800 dark:text-slate-100">المركبة المسجلة (1:1)</h2>
                    <p class="text-[11px] text-slate-400">مركبة واحدة لكل سائق مع صور التعرف بالشارع</p>
                </div>
            </div>
            <a href="{{ route('driver.vehicle') }}" class="text-xs font-bold text-teal-600 dark:text-teal-400 hover:underline">
                {{ $vehicle ? 'تعديل المركبة' : '+ تسجيل المركبة' }}
            </a>
        </div>

        @if($vehicle)
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-base font-black text-slate-800 dark:text-slate-100">
                            {{ $vehicle->type_arabic }}
                        </span>
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $vehicle->status === 'active' ? 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' }} font-bold ms-2">
                            {{ $vehicle->status === 'active' ? 'جاهزة للعمل' : 'تحت الصيانة' }}
                        </span>
                    </div>
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-300">
                        {{ $vehicle->seat_count }} ركاب
                    </span>
                </div>

                @if($vehicle->license_plate)
                    <div class="text-xs text-slate-500 font-mono">
                        رقم اللوحة: {{ $vehicle->license_plate }}
                    </div>
                @endif

                @if(!empty($vehicle->photos))
                    <div class="flex flex-col gap-1.5 pt-2 border-t border-slate-200 dark:border-slate-700">
                        <span class="text-[11px] font-bold text-slate-500">صور التعرف بالشارع (حتى 4 صور):</span>
                        <div class="grid grid-cols-4 gap-2">
                            @foreach($vehicle->photos as $photo)
                                <div class="aspect-video rounded-xl overflow-hidden bg-slate-200 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                    <img src="{{ $photo }}" alt="مركبة" class="w-full h-full object-cover">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="p-6 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-dashed border-amber-200 dark:border-amber-800 text-center flex flex-col items-center gap-2">
                <span class="text-3xl">🚐</span>
                <p class="text-xs font-bold text-amber-800 dark:text-amber-200">لم تقم بتسجيل مركبتك بعد</p>
                <p class="text-[11px] text-amber-700/80 dark:text-amber-300/80">سجل مركبتك (عجلة، سيارة، أو باص) مع عدد المقاعد والصور</p>
                <a href="{{ route('driver.vehicle') }}" class="mt-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                    تسجيل المركبة الآن
                </a>
            </div>
        @endif
    </div>

    <!-- Route Builder Card (Ordered Stops & Leg Fares) -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 border border-slate-200 dark:border-slate-700 flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-2xl">🗺️</span>
                <div>
                    <h2 class="font-bold text-sm text-slate-800 dark:text-slate-100">مسار الخط اليومي (المحطات والأجور)</h2>
                    <p class="text-[11px] text-slate-400">محطات معتمدة من خريطة غزة OpenStreetMap</p>
                </div>
            </div>
            <a href="{{ route('driver.route') }}" class="text-xs font-bold text-teal-600 dark:text-teal-400 hover:underline">
                {{ $route ? 'تعديل المسار' : '+ رسم المسار' }}
            </a>
        </div>

        @if($route)
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-black text-slate-800 dark:text-slate-100">
                        {{ $route->name }}
                    </span>
                    <span class="text-xs px-2.5 py-1 rounded-full bg-slate-200 dark:bg-slate-800 font-mono text-slate-700 dark:text-slate-300">
                        ⏰ أول رحلة: {{ $route->first_trip_time }}
                    </span>
                </div>

                <div class="text-xs text-slate-500">
                    <span class="font-bold">نقطة الانطلاق:</span> {{ $route->start_point }}
                </div>

                <!-- Ordered Stops -->
                <div class="flex flex-col gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <span class="text-[11px] font-bold text-slate-500">محطات التوقف وأجرة كل مقطع:</span>
                    <div class="flex flex-col gap-1.5">
                        @foreach($route->stops ?? [] as $stop)
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-teal-100 dark:bg-teal-900/60 text-teal-700 dark:text-teal-300 flex items-center justify-center font-bold text-[10px]">
                                        {{ $stop['order'] ?? $loop->iteration }}
                                    </span>
                                    <span class="font-bold text-slate-800 dark:text-slate-100">{{ $stop['name'] }}</span>
                                </div>
                                <span class="font-mono font-bold text-teal-600 dark:text-teal-400">
                                    {{ isset($stop['fare_ils']) && $stop['fare_ils'] > 0 ? $stop['fare_ils'] . ' ₪' : 'نقطة بداية' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="p-6 rounded-2xl bg-teal-50 dark:bg-teal-950/30 border border-dashed border-teal-200 dark:border-teal-800 text-center flex flex-col items-center gap-2">
                <span class="text-3xl">📍</span>
                <p class="text-xs font-bold text-teal-800 dark:text-teal-200">لم تحدد خط سيرك ومحطاتك اليومية</p>
                <p class="text-[11px] text-teal-700/80 dark:text-teal-300/80">اختر محطات الانطلاق والوصول على خريطة غزة وحدد أوقات الرحلات</p>
                <a href="{{ route('driver.route') }}" class="mt-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                    بناء خط السير الآن
                </a>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function driverDashboard(initialData) {
    return {
        status: initialData.status,
        statusArabic: initialData.statusArabic,
        async updateStatus(newStatus) {
            this.status = newStatus;
            try {
                const res = await fetch('{{ route('api.driver.route.status') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ status: newStatus })
                });
                const data = await res.json();
                if (data.ok) {
                    this.statusArabic = data.status_arabic;
                }
            } catch (err) {
                console.error('Failed to update status', err);
            }
        }
    };
}
</script>
@endpush
@endsection
