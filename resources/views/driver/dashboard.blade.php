@extends('layouts.app')

@section('content')
<div class="p-4 flex flex-col gap-4" x-data="driverDashboard({
    status: '{{ $route->status ?? 'on_route' }}',
    statusArabic: '{{ $route->status_arabic ?? 'عامل على الخط المعتاد' }}'
})" x-init="init()">
    <!-- Header Banner -->
    <div class="bg-gradient-to-l from-amber-600 to-amber-700 text-white p-5 rounded-3xl shadow-sm flex items-center justify-between">
        <div>
            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-white/20 text-white inline-block mb-1.5">
                لوحة قيادة السائق
            </span>
            <h1 class="text-xl font-black">أهلاً بك، كابتن {{ Auth::user()->name }}</h1>
            <p class="text-xs text-amber-100 mt-0.5 flex items-center gap-1.5">
                <span>هاتف التواصل: <span dir="ltr" class="font-mono font-semibold">{{ Auth::user()->phone ?? 'غير محدد' }}</span></span>
                <a href="{{ route('profile.show') }}" class="text-[11px] font-bold bg-white/20 hover:bg-white/30 text-white px-2 py-0.5 rounded-lg transition" title="تعديل الاسم ورقم الجوال">✏️ تعديل</a>
            </p>
        </div>
        <div class="w-14 h-14 rounded-2xl bg-white/10 flex items-center justify-center text-3xl shrink-0">
            🚗
        </div>
    </div>

    <!-- Quick Driver Portal Navigation -->
    <div class="grid grid-cols-4 gap-2">
        <a href="{{ route('driver.manifest') }}" class="p-2.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-center flex flex-col items-center gap-1 text-xs hover:border-teal-500 transition active:scale-95 shadow-xs">
            <span class="text-lg">📋</span>
            <span class="font-bold text-[11px] truncate">الركاب</span>
        </a>

        <a href="{{ route('driver.schedule') }}" class="p-2.5 rounded-2xl bg-teal-50/50 dark:bg-teal-950/40 border border-teal-500/50 text-teal-800 dark:text-teal-200 text-center flex flex-col items-center gap-1 text-xs hover:border-teal-500 transition active:scale-95 shadow-xs">
            <span class="text-lg">📅</span>
            <span class="font-bold text-[11px] truncate">الجدول</span>
        </a>

        <a href="{{ route('driver.route') }}" class="p-2.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-center flex flex-col items-center gap-1 text-xs hover:border-teal-500 transition active:scale-95 shadow-xs">
            <span class="text-lg">🗺️</span>
            <span class="font-bold text-[11px] truncate">المسار</span>
        </a>

        <a href="{{ route('driver.vehicle') }}" class="p-2.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-center flex flex-col items-center gap-1 text-xs hover:border-teal-500 transition active:scale-95 shadow-xs">
            <span class="text-lg">🛞</span>
            <span class="font-bold text-[11px] truncate">المركبة</span>
        </a>
    </div>

    <!-- Dashboard Feedback Toast Notice -->
    <div x-show="dashboardToast" x-cloak class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/60 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 text-xs font-bold flex items-center justify-between transition shadow-xs">
        <span x-text="dashboardToast"></span>
        <button type="button" @click="dashboardToast = null" class="text-xs px-2 font-black">✕</button>
    </div>

    <!-- Quick Operational Status Bar (Active State Machine) -->
    @if($route)
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-4 border border-slate-200 dark:border-slate-700 flex flex-col gap-2.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">حالة التشغيل:</span>
                    <span class="text-xs font-black px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300" x-text="statusArabic"></span>
                </div>
                <div class="flex items-center text-[10px] font-bold">
                    <span x-show="syncState === 'syncing'" class="text-amber-600 dark:text-amber-400 animate-pulse">⚡ جاري المزامنة...</span>
                    <span x-show="syncState === 'synced'" class="text-teal-600 dark:text-teal-400">✓ متزامن</span>
                    <span x-show="syncState === 'offline_saved'" class="text-amber-600 dark:text-amber-400">📡 أوفلاين (محفوظ محلياً)</span>
                </div>
            </div>
            
            @php
                $initialStatus = $route->status ?? 'on_route';
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button 
                    type="button"
                    @click="updateStatus('on_route')"
                    :class="status === 'on_route' ? 'bg-teal-600 text-white font-bold border-teal-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="py-2.5 px-3 rounded-2xl border text-xs flex flex-col items-center gap-1 transition active:scale-95"
                >
                    <span class="text-base">🟢</span>
                    <span>على الخط</span>
                </button>

                <button 
                    type="button"
                    @click="updateStatus('temporary_route')"
                    :class="status === 'temporary_route' ? 'bg-amber-600 text-white font-bold border-amber-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="py-2.5 px-3 rounded-2xl border text-xs flex flex-col items-center gap-1 transition active:scale-95"
                >
                    <span class="text-base">🔄</span>
                    <span>مسار مؤقت</span>
                </button>

                <button 
                    type="button"
                    @click="updateStatus('off_route')"
                    :class="status === 'off_route' ? 'bg-rose-600 text-white font-bold border-rose-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="py-2.5 px-3 rounded-2xl border text-xs flex flex-col items-center gap-1 transition active:scale-95"
                >
                    <span class="text-base">⏸️</span>
                    <span>خارج الخط</span>
                </button>

                <button 
                    type="button"
                    @click="updateStatus('skip_work')"
                    :class="status === 'skip_work' ? 'bg-slate-600 text-white font-bold border-slate-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="py-2.5 px-3 rounded-2xl border text-xs flex flex-col items-center gap-1 transition active:scale-95"
                >
                    <span class="text-base">🏖️</span>
                    <span>إجازة اليوم</span>
                </button>
            </div>
        </div>

        <!-- Live Trip Direction Switcher & Corridor Nature (Outbound vs Return) -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-4 border border-slate-200 dark:border-slate-700 flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                    <span class="text-base">🧭</span>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">اتجاه الرحلة المباشر:</span>
                </div>
                <span 
                    class="text-xs font-black px-2.5 py-1 rounded-full"
                    :class="activeDirection === 'outbound' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300'"
                    x-text="activeDirectionArabic"
                ></span>
            </div>
            
            <div x-show="isTwoWay" class="grid grid-cols-2 gap-2">
                <button 
                    type="button"
                    @click="updateDirection('outbound')"
                    :class="activeDirection === 'outbound' ? 'bg-emerald-600 text-white font-bold border-emerald-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="py-2.5 px-3 rounded-2xl border text-xs flex items-center justify-center gap-2 transition active:scale-95"
                >
                    <span class="text-base">➡️</span>
                    <span>رحلة الذهاب</span>
                </button>

                <button 
                    type="button"
                    @click="updateDirection('return')"
                    :class="activeDirection === 'return' ? 'bg-blue-600 text-white font-bold border-blue-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="py-2.5 px-3 rounded-2xl border text-xs flex items-center justify-center gap-2 transition active:scale-95"
                >
                    <span class="text-base">⬅️</span>
                    <span>رحلة العودة</span>
                </button>
            </div>

            <div x-show="!isTwoWay" class="p-2.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-200 flex items-center gap-2">
                <span>ℹ️</span>
                <span>المسار حالياً "اتجاه واحد فقط" (ذهاب). يمكنك تفعيل الذهاب والإياب لخدمة كلا الاتجاهين.</span>
            </div>

            <!-- Corridor Nature (Round-Trip vs One-Way) 1-Tap Toggle -->
            <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-700/60 text-xs">
                <div class="flex items-center gap-1.5">
                    <span class="text-slate-400">طبيعة الخط:</span>
                    <span class="font-bold text-teal-700 dark:text-teal-300" x-text="isTwoWay ? 'ذهاب وإياب (⇄)' : 'اتجاه واحد فقط (➔)'"></span>
                </div>
                <button 
                    type="button" 
                    @click="toggleTwoWay()" 
                    class="text-[11px] font-bold px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition active:scale-95 flex items-center gap-1"
                >
                    <span x-text="isTwoWay ? 'تحويل لاتجاه واحد ➔' : 'تفعيل ذهاب وإياب ⇄'"></span>
                </button>
            </div>
        </div>

        <!-- Live GPS Position Broadcasting Card (Strictly during active trip & online) -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-4 border border-slate-200 dark:border-slate-700 flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-xl">📡</span>
                    <div>
                        <span class="text-xs font-black text-slate-800 dark:text-slate-100 block">بث الموقع الحي للرحلة</span>
                        <span class="text-[10px] text-slate-400">يرسل الهاتف الموقع فقط أثناء الرحلة النشطة وعند توفر الإنترنت</span>
                    </div>
                </div>
                <span 
                    class="text-xs font-bold px-2.5 py-1 rounded-full"
                    :class="isTripActive ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                    x-text="isTripActive ? 'بث نشط 🟢' : 'متوقف ⚪'"
                ></span>
            </div>

            <!-- Last Known Location Info -->
            <div class="p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex items-center justify-between text-xs">
                <div class="flex items-center gap-1.5">
                    <span>📍</span>
                    <span class="font-bold text-slate-700 dark:text-slate-200" x-text="lastLandmark || 'لم يتم البث بعد'"></span>
                </div>
                <span class="text-[11px] font-mono text-slate-400" x-text="lastPositionAge"></span>
            </div>

            <!-- GPS Warning Alert -->
            <div x-show="gpsWarning" x-cloak class="p-3 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 text-xs flex items-center justify-between">
                <span class="flex items-center gap-1.5 leading-relaxed">
                    <span>⚠️</span>
                    <span x-text="gpsWarning"></span>
                </span>
                <button type="button" @click="gpsWarning = null" class="text-xs px-1.5 font-bold">✕</button>
            </div>

            <!-- Pre-Trip Manifest Card -->
            <a href="{{ route('driver.manifest') }}" class="p-3.5 rounded-2xl bg-teal-50 dark:bg-teal-950/40 border border-teal-200 dark:border-teal-800 text-teal-800 dark:text-teal-200 flex items-center justify-between text-xs font-bold hover:bg-teal-100 transition">
                <div class="flex items-center gap-2">
                    <span class="text-lg">📋</span>
                    <div>
                        <span class="block">بيان الركاب وتأكيد الحجوزات</span>
                        <span class="text-[10px] text-teal-600 dark:text-teal-400 font-normal">تأكيد الركاب والاتصال الهاتفي قبل بدء الرحلة</span>
                    </div>
                </div>
                <span class="text-xs">استعراض ➔</span>
            </a>

            <!-- Start / Stop Trip Toggle & Manual Broadcast -->
            <div class="grid grid-cols-2 gap-2">
                <button 
                    type="button" 
                    @click="toggleTrip"
                    :class="isTripActive ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-emerald-600 hover:bg-emerald-700 text-white'"
                    class="h-11 rounded-2xl text-xs font-bold transition active:scale-95 flex items-center justify-center gap-1.5 shadow-sm"
                >
                    <span x-text="isTripActive ? '⏹️ إنهاء الرحلة' : '▶️ بدء الرحلة وبث الموقع'"></span>
                </button>

                <button 
                    type="button" 
                    @click="broadcastCurrentPosition"
                    :disabled="!isTripActive || isBroadcasting"
                    class="h-11 bg-teal-50 dark:bg-teal-950/60 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800 disabled:opacity-40 rounded-2xl text-xs font-bold transition active:scale-95 flex items-center justify-center gap-1"
                >
                    <span x-show="!isBroadcasting">📍 تحديث موقعي الآن</span>
                    <span x-show="isBroadcasting">جاري البث...</span>
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
                    <h2 class="font-bold text-sm text-slate-800 dark:text-slate-100">مسار خط اليوم (المحطات والأجور)</h2>
                    <p class="text-[11px] text-slate-400">يعرض مسار اليوم المحدد في جدولك الأسبوعي تلقائياً</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($route)
                    <a href="{{ route('driver.route', ['route_id' => $route->id]) }}" class="text-xs font-bold text-teal-600 dark:text-teal-400 hover:underline">
                        ✏️ تعديل المسار
                    </a>
                @else
                    <a href="{{ route('driver.route') }}" class="text-xs font-bold text-teal-600 dark:text-teal-400 hover:underline">
                        + بناء مسار
                    </a>
                @endif
                <a href="{{ route('driver.route', ['new' => 1]) }}" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                    + مسار إضافي
                </a>
            </div>
        </div>

        <!-- Multiple Routes Quick Switcher Pills -->
        @if(isset($driverRoutes) && $driverRoutes->count() > 1)
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none pt-1 border-t border-slate-100 dark:border-slate-700/60">
                <span class="text-[10px] text-slate-400 shrink-0 font-bold">مساراتك ({{ $driverRoutes->count() }}):</span>
                @foreach($driverRoutes as $r)
                    @php
                        $isCurrentRoute = ($route && $route->id === $r->id);
                    @endphp
                    <a 
                        href="{{ route('driver.route', ['route_id' => $r->id]) }}" 
                        class="px-2.5 py-1 rounded-xl text-[11px] font-bold border transition flex items-center gap-1 shrink-0 {{ $isCurrentRoute ? 'bg-teal-50 dark:bg-teal-950/60 border-teal-500 text-teal-800 dark:text-teal-200 ring-1 ring-teal-500/20' : 'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:border-slate-300' }}"
                        title="انقر لتعديل هذا المسار"
                    >
                        <span>📍</span>
                        <span class="truncate max-w-[120px]">{{ $r->name }}</span>
                        @if($isCurrentRoute)
                            <span class="text-[9px] bg-teal-200 dark:bg-teal-900 text-teal-900 dark:text-teal-200 px-1 rounded">مسار اليوم</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif

        @if($route)
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-black text-slate-800 dark:text-slate-100 block">
                            {{ $route->name }}
                        </span>
                        <span class="text-[10px] text-teal-700 dark:text-teal-300 font-bold">
                            @if($todaySchedule && !empty($todaySchedule['is_day_off']))
                                🏖️ اليوم إجازة أسبوعية بالجدول (المسار المعتمد)
                            @else
                                🟢 المسار المعتمد لدوام اليوم ({{ $currentDayName }})
                            @endif
                        </span>
                    </div>
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

                <a href="{{ route('driver.route', ['route_id' => $route->id]) }}" class="text-xs font-bold text-teal-600 dark:text-teal-400 hover:underline flex items-center justify-center py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl transition shadow-xs">
                    ✏️ تعديل محطات وأسعار هذا المسار
                </a>
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

    <!-- Weekly Schedule & Operating Hours Overview Card -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 border border-slate-200 dark:border-slate-700 flex flex-col gap-3.5 shadow-sm">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-2xl">📅</span>
                <div>
                    <h2 class="font-black text-sm text-slate-800 dark:text-slate-100">جدول ومواعيد العمل الأسبوعية</h2>
                    <p class="text-[11px] text-slate-400">تنظيم خطوط السير وأوقات الورديات للحجز المسبق</p>
                </div>
            </div>
            <a href="{{ route('driver.schedule') }}" class="text-xs font-black text-teal-600 dark:text-teal-400 hover:underline flex items-center gap-1">
                <span>تعديل الجدول</span>
                <span>➔</span>
            </a>
        </div>

        @php
            $dayNames = [
                6 => 'السبت',
                0 => 'الأحد',
                1 => 'الإثنين',
                2 => 'الثلاثاء',
                3 => 'الأربعاء',
                4 => 'الخميس',
                5 => 'الجمعة',
            ];
            $currentDayOfWeek = now()->dayOfWeek;
            $currentDayName = $dayNames[$currentDayOfWeek] ?? 'اليوم';
        @endphp

        <!-- Today's Operational Shift Status -->
        <div class="p-3.5 rounded-2xl border {{ ($todaySchedule && !empty($todaySchedule['is_day_off'])) ? 'bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700' : 'bg-teal-50/70 dark:bg-teal-950/40 border-teal-200 dark:border-teal-800' }} flex items-center justify-between text-xs">
            <div class="flex items-center gap-2.5">
                <span class="text-xl">{{ ($todaySchedule && !empty($todaySchedule['is_day_off'])) ? '🏖️' : '🟢' }}</span>
                <div>
                    <span class="font-black text-slate-800 dark:text-slate-100 block">
                        دوام اليوم ({{ $currentDayName }}):
                        @if($todaySchedule && !empty($todaySchedule['is_day_off']))
                            <span class="text-rose-600 dark:text-rose-400 font-bold">إجازة أسبوعية</span>
                        @else
                            <span class="text-teal-700 dark:text-teal-300 font-mono font-bold">{{ $todaySchedule['start_time'] ?? '07:00' }} - {{ $todaySchedule['end_time'] ?? '16:00' }}</span>
                        @endif
                    </span>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400">
                        @if($todaySchedule && !empty($todaySchedule['is_day_off']))
                            مركبتك لن تظهر في بحث وتواجد الركاب اليوم
                        @else
                            المسار: {{ $todaySchedule['route_name'] ?? ($route->name ?? 'خط السير المعتمد') }}
                        @endif
                    </span>
                </div>
            </div>
            <a href="{{ route('driver.schedule') }}" class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[11px] font-bold text-slate-700 dark:text-slate-200 shadow-xs hover:bg-slate-50 transition active:scale-95">
                تغيير
            </a>
        </div>

        <!-- 7-Day Mini Timeline Bar -->
        <div class="grid grid-cols-7 gap-1 pt-1 text-center">
            @foreach($schedules ?? [] as $item)
                @php
                    $isToday = ($item['day_of_week'] === $currentDayOfWeek);
                @endphp
                <a href="{{ route('driver.schedule') }}" title="{{ $item['day_name_arabic'] }} - {{ $item['is_day_off'] ? 'إجازة' : ($item['start_time'] . ' إلى ' . $item['end_time']) }}" class="p-1.5 rounded-xl border text-[10px] flex flex-col items-center gap-0.5 transition {{ $isToday ? 'border-teal-500 bg-teal-100/60 dark:bg-teal-950/80 ring-2 ring-teal-500/30 font-bold shadow-xs' : ($item['is_day_off'] ? 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40 text-slate-400' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300') }}">
                    <span class="font-bold text-[11px] block">{{ $item['day_abbr'] ?? $item['day_name_arabic'] }}</span>
                    <span>{{ $item['is_day_off'] ? '🏖️' : '🟢' }}</span>
                </a>
            @endforeach
        </div>

        <!-- Call to Action button -->
        <a href="{{ route('driver.schedule') }}" class="w-full py-2.5 px-4 rounded-2xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition active:scale-95">
            <span>📅 تخصيص جدول ومسارات الأسبوع بالتفصيل</span>
            <span>➔</span>
        </a>
    </div>
</div>

@push('scripts')
<script src="/js/gaza-geo-sync.js"></script>
<script>
const STATUS_MAP = {
    on_route: 'عامل على الخط المعتاد',
    temporary_route: 'مسار مؤقت',
    off_route: 'خارج الخط مؤقتاً',
    skip_work: 'إجازة اليوم'
};

const DIRECTION_MAP = {
    outbound: 'رحلة الذهاب',
    return: 'رحلة العودة'
};

function driverDashboard(initialData) {
    return {
        status: initialData.status,
        statusArabic: initialData.statusArabic,
        syncState: 'idle', // 'idle' | 'syncing' | 'synced' | 'offline_saved'
        dashboardToast: null,
        activeDirection: '{{ $route->active_direction ?? 'outbound' }}',
        activeDirectionArabic: '{{ $route->active_direction_arabic ?? 'رحلة الذهاب' }}',
        isTwoWay: {{ ($route->is_two_way ?? true) ? 'true' : 'false' }},
        isTripActive: {{ ($route->is_trip_active ?? false) ? 'true' : 'false' }},
        lastLandmark: '{{ $route->last_landmark ?? '' }}',
        lastPositionAge: '{{ $route->position_age_arabic ?? 'غير محدد' }}',
        isBroadcasting: false,
        gpsWarning: null,
        lastSentPoint: @if($route->last_latitude && $route->last_longitude) { lat: {{ $route->last_latitude }}, lng: {{ $route->last_longitude }} } @else null @endif,
        tripTimer: null,

        init() {
            // Listen to browser network changes for low-bandwidth / offline environments (Gaza)
            window.addEventListener('online', () => {
                if (this.syncState === 'offline_saved') {
                    this.syncState = 'syncing';
                    this.syncStatusWithServer(this.status);
                }
            });
        },

        showToast(msg) {
            this.dashboardToast = msg;
            setTimeout(() => {
                if (this.dashboardToast === msg) {
                    this.dashboardToast = null;
                }
            }, 4500);
        },

        // Zero-Latency Optimistic UI Status Update
        async updateStatus(newStatus) {
            if (this.status === newStatus && this.syncState !== 'offline_saved') {
                return;
            }

            const prevStatus = this.status;
            const prevArabic = this.statusArabic;

            // 1. Instant local state switch (0ms delay)
            this.status = newStatus;
            this.statusArabic = STATUS_MAP[newStatus] || newStatus;
            this.syncState = 'syncing';

            try {
                localStorage.setItem('meshwar_driver_status', newStatus);
            } catch(e) {}

            // 2. Background Server Sync
            await this.syncStatusWithServer(newStatus, prevStatus, prevArabic);
        },

        async syncStatusWithServer(newStatus, prevStatus = null, prevArabic = null) {
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
                    this.syncState = 'synced';
                    setTimeout(() => {
                        if (this.syncState === 'synced') this.syncState = 'idle';
                    }, 2500);
                } else {
                    if (prevStatus) {
                        this.status = prevStatus;
                        this.statusArabic = prevArabic;
                    }
                    this.syncState = 'idle';
                    this.showToast('تعذر حفظ الحالة: ' + (data.error || 'خطأ في الخادم'));
                }
            } catch (err) {
                console.warn('Network offline / weak in Gaza, saved locally', err);
                this.syncState = 'offline_saved';
                this.showToast('⚠️ تم حفظ حالتك محلياً بالهاتف (ضعف في الإنترنت). ستتم المزامنة تلقائياً عند استقرار الاتصال.');
            }
        },

        // Zero-Latency Optimistic UI Direction Switch
        async updateDirection(newDirection) {
            if (this.activeDirection === newDirection) return;

            const prevDirection = this.activeDirection;
            const prevArabic = this.activeDirectionArabic;

            // 1. Instant local state switch (0ms delay)
            this.activeDirection = newDirection;
            this.activeDirectionArabic = DIRECTION_MAP[newDirection] || newDirection;
            this.syncState = 'syncing';

            // 2. Background Server Sync
            try {
                const res = await fetch('{{ route('api.driver.route.direction') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ direction: newDirection })
                });
                const data = await res.json();
                if (data.ok) {
                    this.activeDirectionArabic = data.active_direction_arabic;
                    this.syncState = 'synced';
                    setTimeout(() => {
                        if (this.syncState === 'synced') this.syncState = 'idle';
                    }, 2500);
                } else {
                    this.activeDirection = prevDirection;
                    this.activeDirectionArabic = prevArabic;
                    this.syncState = 'idle';
                }
            } catch (err) {
                console.warn('Direction update offline, state preserved locally', err);
                this.syncState = 'offline_saved';
            }
        },

        // Zero-Latency Optimistic UI Corridor Type Toggle (Round-Trip vs One-Way)
        async toggleTwoWay() {
            const nextVal = !this.isTwoWay;

            // 1. Instant local switch (0ms delay)
            this.isTwoWay = nextVal;
            this.syncState = 'syncing';

            // 2. Background Server Sync
            try {
                const res = await fetch('{{ route('api.driver.route.two_way') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ is_two_way: nextVal })
                });
                const data = await res.json();
                if (data.ok) {
                    this.isTwoWay = data.is_two_way;
                    this.syncState = 'synced';
                    setTimeout(() => {
                        if (this.syncState === 'synced') this.syncState = 'idle';
                    }, 2500);
                } else {
                    this.isTwoWay = !nextVal;
                    this.syncState = 'idle';
                }
            } catch (err) {
                console.warn('Two-way toggle offline, state preserved locally', err);
                this.syncState = 'offline_saved';
            }
        },

        async toggleTrip() {
            this.isTripActive = !this.isTripActive;
            if (this.isTripActive) {
                this.broadcastCurrentPosition();
                // Periodic GPS check every 15s using gaza-geo-sync standing-still throttle
                if (!this.tripTimer) {
                    this.tripTimer = setInterval(() => {
                        if (this.isTripActive && navigator.onLine) {
                            this.broadcastCurrentPosition();
                        }
                    }, 15000);
                }
            } else {
                if (this.tripTimer) {
                    clearInterval(this.tripTimer);
                    this.tripTimer = null;
                }
                if (this.lastSentPoint) {
                    try {
                        await fetch('{{ route('api.driver.position') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                lat: this.lastSentPoint.lat,
                                lng: this.lastSentPoint.lng,
                                is_trip_active: false
                            })
                        });
                    } catch (e) {}
                }
            }
        },

        async broadcastCurrentPosition() {
            const sendPos = async (rawLat, rawLng, landmarkName) => {
                const lat = window.GazaGeoSync ? window.GazaGeoSync.quantize(rawLat) : Math.round(rawLat * 1e5) / 1e5;
                const lng = window.GazaGeoSync ? window.GazaGeoSync.quantize(rawLng) : Math.round(rawLng * 1e5) / 1e5;

                // gaza-geo-sync standing-still throttle: don't re-upload if vehicle hasn't moved 15m unless 60s passed
                if (window.GazaGeoSync && !window.GazaGeoSync.worthSending(this.lastSentPoint, { lat, lng })) {
                    return;
                }

                this.isBroadcasting = true;

                try {
                    const res = await fetch('{{ route('api.driver.position') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            lat: lat,
                            lng: lng,
                            landmark: landmarkName || 'موقع حي على الخط',
                            is_trip_active: true
                        })
                    });
                    const data = await res.json();
                    if (data.ok) {
                        this.lastLandmark = data.position.landmark;
                        this.lastPositionAge = data.position.position_age_arabic;
                        this.lastSentPoint = { lat, lng, sentAt: Date.now() };
                        this.gpsWarning = null;
                    }
                } catch (err) {
                    console.warn('Offline: GPS position saved in outbox', err);
                } finally {
                    this.isBroadcasting = false;
                }
            };

            if ('geolocation' in navigator) {
                this.isBroadcasting = true;
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        this.gpsWarning = null;
                        sendPos(pos.coords.latitude, pos.coords.longitude, 'موقع حي GPS');
                    },
                    err => {
                        this.isBroadcasting = false;
                        if (err.code === 1) {
                            this.gpsWarning = 'تم رفض الإذن بالوصول للموقع، يرجى تفعيل إذن الموقع في إعدادات المتصفح';
                        } else if (err.code === 2) {
                            this.gpsWarning = 'إشارة GPS غير متوفرة حالياً، يرجى التأكد من تشغيل الموقع بالهاتف';
                        } else {
                            this.gpsWarning = 'استغرق تحديد موقع GPS وقتاً أطول من المعتاد، يرجى المحاولة في مكان مفتوح';
                        }
                    },
                    { timeout: 10000, enableHighAccuracy: true, maximumAge: 10000 }
                );
            } else {
                this.isBroadcasting = false;
                this.gpsWarning = 'المتصفح الحالي لا يدعم خدمة تحديد الموقع الجغرافي GPS';
            }
        }
    };
}
</script>
@endpush
@endsection
