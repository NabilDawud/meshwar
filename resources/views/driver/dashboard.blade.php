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

    <!-- Quick Operational Status Bar (Active State Machine) -->
    @if($route)
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-4 border border-slate-200 dark:border-slate-700 flex flex-col gap-2.5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">حالة التشغيل الحالية:</span>
                <span class="text-xs font-black px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300" x-text="statusArabic"></span>
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
                    <h2 class="font-bold text-sm text-slate-800 dark:text-slate-100">مسار الخط اليومي (المحطات والأجور)</h2>
                    <p class="text-[11px] text-slate-400">محطات معتمدة من خريطة غزة OpenStreetMap</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('driver.route') }}" class="text-xs font-bold text-teal-600 dark:text-teal-400 hover:underline">
                    {{ $route ? 'تعديل المسار' : '+ رسم المسار' }}
                </a>
                @if($route)
                    <a href="{{ route('driver.route', ['new' => 1]) }}" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                        + مسار إضافي
                    </a>
                @endif
            </div>
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

    <!-- Weekly Schedule & Multi-Route Planner Card -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 border border-slate-200 dark:border-slate-700 flex flex-col gap-4 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center gap-2.5">
                <span class="text-2xl">📅</span>
                <div>
                    <h2 class="font-black text-sm sm:text-base text-slate-800 dark:text-slate-100">جدول مواعيد ومسارات الأسبوع</h2>
                    <p class="text-[11px] text-slate-400">حدد خط السير وساعات العمل أو الإجازة لكل يوم لتمكين الركاب من الحجز المسبق</p>
                </div>
            </div>
            <button 
                type="button" 
                @click="copySaturdayToAllWorkdays" 
                class="px-3 py-1.5 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 rounded-xl text-xs font-bold transition flex items-center gap-1.5 self-start sm:self-auto active:scale-95 shadow-sm"
                title="نسخ ساعات ومسار يوم السبت إلى الأحد، الإثنين، الثلاثاء، والأربعاء، والخميس"
            >
                <span>⚡</span>
                <span>تطبيق مواعيد السبت على باقي الأيام</span>
            </button>
        </div>

        <!-- Schedule Toast Notice -->
        <div x-show="scheduleToast" x-cloak class="p-3.5 rounded-2xl bg-teal-50 dark:bg-teal-950/40 border border-teal-300 dark:border-teal-700 text-teal-800 dark:text-teal-200 text-xs font-bold flex items-center justify-between">
            <span x-text="scheduleToast"></span>
            <button type="button" @click="scheduleToast = null" class="text-xs px-2">✕</button>
        </div>

        <!-- Loading Skeletons -->
        <div x-show="loadingSchedules" class="flex flex-col gap-2.5 py-4">
            <template x-for="i in 4" :key="i">
                <div class="h-16 rounded-2xl bg-slate-100 dark:bg-slate-700/50 animate-pulse"></div>
            </template>
        </div>

        <!-- 7-Day Matrix -->
        <div x-show="!loadingSchedules" class="flex flex-col gap-2.5">
            <template x-for="s in schedules" :key="s.day_of_week">
                <div 
                    class="p-3 sm:p-4 rounded-2xl border transition flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                    :class="s.is_day_off ? 'bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-800 opacity-80' : 'bg-white dark:bg-slate-800/80 border-slate-200 dark:border-slate-700 shadow-sm'"
                >
                    <!-- Day Name & Day-Off Toggle -->
                    <div class="flex items-center justify-between sm:justify-start gap-3 sm:w-48 shrink-0">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-xl flex items-center justify-center font-bold text-xs" :class="s.is_day_off ? 'bg-slate-200 dark:bg-slate-700 text-slate-500' : 'bg-teal-100 dark:bg-teal-950 text-teal-800 dark:text-teal-300'" x-text="s.day_name_arabic.slice(0, 2)"></span>
                            <span class="font-black text-sm text-slate-800 dark:text-slate-100" x-text="s.day_name_arabic"></span>
                        </div>

                        <!-- Toggle Day-Off -->
                        <label class="flex items-center gap-1.5 cursor-pointer text-xs select-none">
                            <input 
                                type="checkbox" 
                                x-model="s.is_day_off" 
                                class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500 border-slate-300 dark:border-slate-600"
                            >
                            <span :class="s.is_day_off ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-slate-400'">إجازة</span>
                        </label>
                    </div>

                    <!-- Work Shift Controls (Route & Hours) -->
                    <template x-if="!s.is_day_off">
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 flex-1">
                            <!-- Route Select -->
                            <div class="flex-1">
                                <select 
                                    x-model="s.route_id" 
                                    class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
                                >
                                    <template x-for="r in driverRoutes" :key="r.id">
                                        <option :value="r.id" x-text="r.name" :selected="s.route_id == r.id"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Shift Hours -->
                            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                <span class="text-[11px] font-bold">من:</span>
                                <input 
                                    type="time" 
                                    x-model="s.start_time" 
                                    class="h-10 px-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 font-mono text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
                                >
                                <span class="text-[11px] font-bold">إلى:</span>
                                <input 
                                    type="time" 
                                    x-model="s.end_time" 
                                    class="h-10 px-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 font-mono text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
                                >
                            </div>
                        </div>
                    </template>

                    <!-- Day-Off Badge -->
                    <template x-if="s.is_day_off">
                        <div class="flex-1 flex items-center justify-end text-xs text-slate-400 italic gap-1.5 py-1">
                            <span>🏖️</span>
                            <span>إجازة أسبوعية (لا تظهر المركبة في بحث الركاب لهذا اليوم)</span>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <!-- Save Schedule Button -->
        <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-700">
            <span class="text-[11px] text-slate-400">تحديث المواعيد ينعكس فوراً على خريطة وتطبيق الركاب</span>
            <button 
                type="button" 
                @click="saveSchedules" 
                :disabled="savingSchedules || loadingSchedules" 
                class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold text-xs rounded-xl shadow-sm transition active:scale-95 flex items-center gap-1.5"
            >
                <span x-show="!savingSchedules">حفظ جدول الأسبوع 💾</span>
                <span x-show="savingSchedules">جاري الحفظ...</span>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script src="/js/gaza-geo-sync.js"></script>
<script>
function driverDashboard(initialData) {
    return {
        status: initialData.status,
        statusArabic: initialData.statusArabic,
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
        schedules: [],
        driverRoutes: [],
        loadingSchedules: false,
        savingSchedules: false,
        scheduleToast: null,

        init() {
            this.fetchSchedules();
        },

        async fetchSchedules() {
            this.loadingSchedules = true;
            try {
                const [routesRes, schedRes] = await Promise.all([
                    fetch('{{ route('api.driver.routes.index') }}'),
                    fetch('{{ route('api.driver.schedules.index') }}')
                ]);
                const routesData = await routesRes.json();
                const schedData = await schedRes.json();

                if (routesData.ok) {
                    this.driverRoutes = routesData.routes;
                }
                if (schedData.ok) {
                    this.schedules = schedData.schedules;
                }
            } catch (err) {
                console.error('Failed to load schedules', err);
            } finally {
                this.loadingSchedules = false;
            }
        },

        copySaturdayToAllWorkdays() {
            const sat = this.schedules.find(s => s.day_of_week === 6);
            if (!sat) return;

            this.schedules.forEach(s => {
                // Apply to Sunday through Thursday (0, 1, 2, 3, 4)
                if ([0, 1, 2, 3, 4].includes(s.day_of_week)) {
                    s.route_id = sat.route_id;
                    s.start_time = sat.start_time;
                    s.end_time = sat.end_time;
                    s.is_day_off = sat.is_day_off;
                }
            });
            this.scheduleToast = '⚡ تم نسخ مواعيد السبت إلى باقي أيام العمل بنجاح! يرجى الضغط على حفظ الجدول أدناه.';
            setTimeout(() => { this.scheduleToast = null; }, 5000);
        },

        async saveSchedules() {
            this.savingSchedules = true;
            try {
                const payload = {
                    schedules: this.schedules.map(s => ({
                        day_of_week: s.day_of_week,
                        route_id: s.is_day_off ? null : (s.route_id || null),
                        start_time: s.start_time || '07:00',
                        end_time: s.end_time || '16:00',
                        is_day_off: Boolean(s.is_day_off)
                    }))
                };

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
                    this.scheduleToast = '✅ تم حفظ جدول مواعيد ومسارات الأسبوع بنجاح!';
                    setTimeout(() => { this.scheduleToast = null; }, 4000);
                } else {
                    alert(data.error || 'حدث خطأ أثناء حفظ الجدول');
                }
            } catch (err) {
                console.error('Failed to save schedules', err);
                alert('فشل الاتصال بالخادم لحفظ الجدول');
            } finally {
                this.savingSchedules = false;
            }
        },

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
        },

        async updateDirection(newDirection) {
            if (this.activeDirection === newDirection) return;
            this.activeDirection = newDirection;
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
                }
            } catch (err) {
                console.error('Failed to update direction', err);
            }
        },

        async toggleTwoWay() {
            const nextVal = !this.isTwoWay;
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
                }
            } catch (err) {
                console.error('Failed to update route corridor type', err);
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
