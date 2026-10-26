@extends('layouts.app')

@section('content')
<div class="p-4 flex flex-col gap-4">
    <!-- Welcome Rider Banner -->
    <div class="bg-gradient-to-l from-teal-600 to-teal-700 text-white p-5 rounded-3xl shadow-sm flex items-center justify-between">
        <div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white/20 text-white inline-block mb-2">
                لوحة الراكب
            </span>
            <h1 class="text-xl font-black">أهلاً بك، {{ Auth::user()->name }}</h1>
            <p class="text-xs text-teal-100 mt-1">تصفح خطوط غزة وتابع المركبات الحية</p>
        </div>
        <div class="w-14 h-14 rounded-2xl bg-white/10 flex items-center justify-center text-3xl">
            📍
        </div>
    </div>

    <!-- Quick Actions Card -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 border border-slate-200 dark:border-slate-700 flex flex-col gap-3">
        <h2 class="font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center gap-2">
            <span>🗺️</span>
            <span>المركبات النشطة على المحاور الرئيسية</span>
        </h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
            محور صلاح الدين السريع، شارع الرشيد الساحلي، ومحاور الوصل العرضية. لا تخمينات، فقط آخر موقع مسجل ودقائق الحداثة.
        </p>
        
        <div class="p-4 bg-slate-50 dark:bg-slate-900/50 rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 text-center">
            <span class="text-2xl block mb-1">⏳</span>
            <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">
                سيتم تفعيل الخريطة الحية والتتبع في المرحلة الثانية (M2).
            </span>
        </div>
    </div>
</div>
@endsection
