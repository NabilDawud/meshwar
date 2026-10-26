@extends('layouts.app')

@section('content')
<div class="flex-1 flex flex-col p-4 gap-4">
    <!-- Hero / Corridor Status Banner -->
    <div class="rounded-3xl bg-gradient-to-br from-teal-600 to-teal-800 text-white p-6 shadow-lg relative overflow-hidden">
        <div class="relative z-10">
            <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-white/20 backdrop-blur mb-2">
                🟢 شبكة النقل المباشرة في قطاع غزة
            </span>
            <h1 class="text-2xl font-extrabold mb-1">مشوارك أسهل وأوضح</h1>
            <p class="text-sm text-teal-100 mb-4 leading-relaxed">
                تتبع حركة المركبات والعربات (العجلات) لحظة بلحظة على شارع صلاح الدين وشارع الرشيد الساحلي بدون تخمينات.
            </p>

            <div class="flex items-center gap-2">
                <a href="#corridors" class="px-4 py-2 rounded-xl bg-white text-teal-800 font-bold text-xs shadow hover:bg-teal-50 transition">
                    استكشف الخطوط
                </a>
                @guest
                    <a href="{{ route('login') }}" class="px-4 py-2 rounded-xl bg-teal-900/60 text-white font-semibold text-xs border border-white/20 hover:bg-teal-900 transition">
                        تسجيل الدخول
                    </a>
                @endguest
            </div>
        </div>
        <div class="absolute -bottom-6 -start-6 w-32 h-32 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
    </div>

    <!-- Corridors Quick View -->
    <div id="corridors" class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-800 dark:text-slate-100">محاور الحركة الرئيسية</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400">ذهاب وإياب (&harr;)</span>
        </div>

        <!-- Salah al-Din Corridor Card -->
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 shadow-sm flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 flex items-center justify-center text-xl shrink-0">
                🛣️
            </div>
            <div class="flex-1">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-50">طريق صلاح الدين (العمود الفقري)</h3>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">نشط</span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    رفح &harr; خانيونس &harr; دير البلح &harr; النصيرات والبريج &harr; غزة
                </p>
                <div class="mt-3 flex items-center gap-2 text-xs text-teal-600 dark:text-teal-400 font-medium">
                    <span>المركبات العاملة: سيارات، باصات، وعربات عجلة</span>
                </div>
            </div>
        </div>

        <!-- Al-Rashid Coastal Corridor Card -->
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 shadow-sm flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 flex items-center justify-center text-xl shrink-0">
                🌊
            </div>
            <div class="flex-1">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-50">شارع الرشيد الساحلي</h3>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">نشط</span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    المواصي الساحلية &harr; ميناء دير البلح &harr; شاطئ غزة
                </p>
                <div class="mt-3 flex items-center gap-2 text-xs text-teal-600 dark:text-teal-400 font-medium">
                    <span>المركبات العاملة: مركبات ساحلية وعربات نقل</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Offline & Safety Notice -->
    <div class="mt-auto p-4 rounded-2xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-300 flex items-center gap-3">
        <span class="text-xl">📱</span>
        <div>
            <p class="font-bold">يعمل حتى عند ضعف الإنترنت</p>
            <p class="text-slate-500 dark:text-slate-400">يتم حفظ عملياتك تلقائياً على الهاتف ومزامنتها بمجرد توفر الاتصال.</p>
        </div>
    </div>
</div>
@endsection
