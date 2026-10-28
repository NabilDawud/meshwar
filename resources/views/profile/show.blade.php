@extends('layouts.app')

@section('content')
<div class="p-4 sm:p-6 flex flex-col gap-6 max-w-lg mx-auto w-full">
    
    <!-- Top Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ $user->role === 'driver' ? route('driver.dashboard') : route('home') }}" class="p-2 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 transition shadow-sm">
                ➔
            </a>
            <div>
                <h1 class="text-xl font-black text-slate-800 dark:text-slate-100">الملف الشخصي</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">تعديل الاسم ورقم الجوال للتواصل</p>
            </div>
        </div>

        <span class="px-3 py-1 rounded-full text-xs font-bold {{ $user->role === 'driver' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300' : 'bg-teal-100 text-teal-800 dark:bg-teal-900/60 dark:text-teal-300' }}">
            {{ $user->role === 'driver' ? '🚗 سائق خط' : '👤 راكب' }}
        </span>
    </div>

    <!-- Success Feedback Notification -->
    @if(session('status'))
        <div class="p-4 rounded-2xl bg-teal-50 dark:bg-teal-950/40 border border-teal-300 dark:border-teal-700 text-teal-800 dark:text-teal-200 text-xs font-bold flex items-center gap-2">
            <span>✅</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Error Banner for in-progress trips or general errors -->
    @if($errors->has('profile'))
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200 text-xs font-bold flex items-center gap-2">
            <span>⚠️</span>
            <span>{{ $errors->first('profile') }}</span>
        </div>
    @endif

    <!-- Profile Overview Card -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm flex items-center gap-4">
        @if($user->avatar)
            <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="w-16 h-16 rounded-full border-2 border-teal-500 shadow-sm shrink-0">
        @else
            <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-teal-600 to-emerald-500 text-white font-black text-2xl flex items-center justify-center shadow-sm shrink-0">
                {{ mb_substr($user->name, 0, 1) }}
            </div>
        @endif

        <div class="flex-1 min-w-0">
            <h2 class="text-base font-black text-slate-800 dark:text-slate-100 truncate">{{ $user->name }}</h2>
            <p class="text-xs text-slate-400 dark:text-slate-500 font-mono truncate mt-0.5" dir="ltr">{{ $user->email }}</p>
            <div class="flex items-center gap-2 mt-2">
                <span class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1 font-mono">
                    <span>📱</span>
                    <span dir="ltr">{{ $user->phone ?? 'غير محدد' }}</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Edit Form Card -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 shadow-sm">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700 mb-5">
            <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span>✏️</span>
                <span>تعديل البيانات</span>
            </h3>
            <span class="text-[11px] text-slate-400">تحديث فوري</span>
        </div>

        <form action="{{ route('profile.update') }}" method="POST" class="flex flex-col gap-4">
            @csrf

            <!-- Name Field -->
            <div class="flex flex-col gap-1.5">
                <label for="name" class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center justify-between">
                    <span>الاسم الكامل المعروض:</span>
                    <span class="text-[10px] text-slate-400 font-normal">يظهر للطرف الآخر عند الحجز</span>
                </label>
                <div class="relative">
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        value="{{ old('name', $user->name) }}"
                        required
                        minlength="3"
                        maxlength="60"
                        class="w-full px-3.5 py-3 rounded-2xl border text-sm font-medium transition focus:outline-hidden focus:ring-2 focus:ring-teal-500 {{ $errors->has('name') ? 'border-rose-400 bg-rose-50/20 text-rose-900' : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100' }}"
                        placeholder="أدخل اسمك الكامل"
                    >
                </div>
                @error('name')
                    <span class="text-[11px] font-bold text-rose-500 mt-0.5">{{ $message }}</span>
                @enderror
            </div>

            <!-- Mobile Phone Number Field -->
            <div class="flex flex-col gap-1.5">
                <label for="phone" class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center justify-between">
                    <span>رقم الجوال للتواصل:</span>
                    <span class="text-[10px] text-amber-600 dark:text-amber-400 font-medium">مهم للاتصال المباشر</span>
                </label>
                <div class="relative flex items-center rounded-2xl border {{ $errors->has('phone') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900' }} overflow-hidden focus-within:ring-2 focus-within:ring-teal-500 transition">
                    <span class="px-3 text-slate-400 text-sm font-bold border-l border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800/60 py-3">
                        🇵🇸
                    </span>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        value="{{ old('phone', $user->phone) }}"
                        required
                        dir="ltr"
                        class="w-full px-3.5 py-3 text-sm font-mono font-medium bg-transparent text-slate-800 dark:text-slate-100 focus:outline-hidden"
                        placeholder="0599123456"
                    >
                </div>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">
                    أرقام جوال أو أوريدو (مثال: <span dir="ltr" class="font-mono">0599123456</span> أو <span dir="ltr" class="font-mono">0569123456</span>)
                </p>
                @error('phone')
                    <span class="text-[11px] font-bold text-rose-500 mt-0.5">{{ $message }}</span>
                @enderror
            </div>

            <!-- Account Email (Read-Only Google ID) -->
            <div class="flex flex-col gap-1.5 opacity-80 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                <label class="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center justify-between">
                    <span>البريد الإلكتروني الموثق (Google):</span>
                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">🔒 مرتبط بحسابك</span>
                </label>
                <input 
                    type="email" 
                    value="{{ $user->email }}" 
                    disabled 
                    dir="ltr"
                    class="w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-900/50 text-xs text-slate-500 font-mono cursor-not-allowed"
                >
            </div>

            <!-- Action Button -->
            <button 
                type="submit" 
                class="mt-3 w-full py-3.5 rounded-2xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-sm shadow-md transition active:scale-[0.98] flex items-center justify-center gap-2"
            >
                <span>💾</span>
                <span>حفظ التعديلات</span>
            </button>
        </form>
    </div>

    <!-- Guidance & Privacy Notice -->
    <div class="p-4 rounded-3xl bg-slate-100/80 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-700/60 text-xs text-slate-500 dark:text-slate-400 flex flex-col gap-2">
        <div class="flex items-center gap-2 font-bold text-slate-700 dark:text-slate-300">
            <span>🛡️</span>
            <span>خصوصية البيانات وأمان التواصل:</span>
        </div>
        <ul class="list-disc list-inside space-y-1 text-[11px] leading-relaxed">
            <li>رقم الجوال لا يُكشف إلا للطرف الآخر (السائق أو الراكب) بعد تأكيد الحجز أو قبول عرض المشوار الخاص.</li>
            <li>يمكن تعديل رقم الجوال حتى 3 مرات خلال 24 ساعة لمرونة تصحيح الأخطاء وتبديل الشرائح عند الحاجة.</li>
            <li>لأسباب تنظيمية وأمنية، لا يمكن تعديل الاسم أو رقم الجوال أثناء وجود رحلة نشطة جارية.</li>
        </ul>
    </div>
</div>
@endsection
