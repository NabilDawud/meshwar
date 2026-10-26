@extends('layouts.app')

@section('content')
<div class="flex-1 flex flex-col justify-center items-center px-4 py-8">
    <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 flex flex-col">
        
        <div class="text-center mb-6">
            <span class="inline-block p-3 bg-teal-50 dark:bg-teal-950/60 rounded-2xl text-3xl mb-3 border border-teal-100 dark:border-teal-900">
                ✨
            </span>
            <h1 class="text-xl sm:text-2xl font-black text-slate-800 dark:text-slate-100 mb-1">
                مرحباً بك، {{ Auth::user()->name }}!
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                اختر نوع حسابك للمتابعة. هذا الاختيار يحدد واجهتك وخصائص التطبيق المخصصة لك.
            </p>
        </div>

        @if ($errors->any())
            <div class="mb-4 p-3 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 rounded-2xl text-xs text-rose-700 dark:text-rose-300">
                يرجى اختيار أحد الدورين للمتابعة.
            </div>
        @endif

        <form method="POST" action="{{ route('role.choice.store') }}" class="flex flex-col gap-3.5">
            @csrf

            <!-- Rider Option -->
            <label class="group relative flex items-center gap-4 p-4 rounded-2xl border-2 border-slate-200 dark:border-slate-700 hover:border-teal-500 dark:hover:border-teal-400 bg-white dark:bg-slate-900/60 cursor-pointer transition active:scale-[0.99] has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50/50 dark:has-[:checked]:bg-teal-950/30">
                <input type="radio" name="role" value="rider" class="peer sr-only" required>
                <div class="w-12 h-12 rounded-2xl bg-teal-100 dark:bg-teal-900/60 text-teal-700 dark:text-teal-300 flex items-center justify-center text-2xl shrink-0 group-hover:scale-105 transition">
                    📍
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-800 dark:text-slate-100 text-base">أنا راكب</span>
                        <span class="w-5 h-5 rounded-full border-2 border-slate-300 dark:border-slate-600 peer-checked:border-teal-600 peer-checked:bg-teal-600 group-has-[:checked]:border-teal-600 group-has-[:checked]:bg-teal-600 transition flex items-center justify-center">
                            <span class="w-2 h-2 rounded-full bg-white opacity-0 group-has-[:checked]:opacity-100 transition"></span>
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                        استعراض المركبات الحية، معرفة زمن آخر موقع، وحجز المقاعد على خطوط غزة
                    </p>
                </div>
            </label>

            <!-- Driver Option -->
            <label class="group relative flex items-center gap-4 p-4 rounded-2xl border-2 border-slate-200 dark:border-slate-700 hover:border-amber-500 dark:hover:border-amber-400 bg-white dark:bg-slate-900/60 cursor-pointer transition active:scale-[0.99] has-[:checked]:border-amber-600 has-[:checked]:bg-amber-50/50 dark:has-[:checked]:bg-amber-950/30">
                <input type="radio" name="role" value="driver" class="peer sr-only">
                <div class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 flex items-center justify-center text-2xl shrink-0 group-hover:scale-105 transition">
                    🚗
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-800 dark:text-slate-100 text-base">أنا سائق</span>
                        <span class="w-5 h-5 rounded-full border-2 border-slate-300 dark:border-slate-600 group-has-[:checked]:border-amber-600 group-has-[:checked]:bg-amber-600 transition flex items-center justify-center">
                            <span class="w-2 h-2 rounded-full bg-white opacity-0 group-has-[:checked]:opacity-100 transition"></span>
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                        تسجيل المركبة (عجلة أو سيارة أو باص)، بث خط السير، وتأكيد الحجوزات قبل الانطلاق
                    </p>
                </div>
            </label>

            <button type="submit" class="mt-4 w-full min-h-[48px] bg-teal-600 hover:bg-teal-700 active:scale-[0.98] text-white font-bold rounded-2xl text-sm transition shadow-sm flex items-center justify-center gap-2">
                <span>تأكيد المتابعة</span>
                <span>←</span>
            </button>
        </form>

        <p class="text-[11px] text-center text-slate-400 dark:text-slate-500 mt-4">
            يحفظ هذا الخيار بشكل دائم في حسابك في قاعدة البيانات.
        </p>
    </div>
</div>
@endsection
