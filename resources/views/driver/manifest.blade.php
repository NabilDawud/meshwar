@extends('layouts.app')

@section('title', 'بيان ركاب الرحلة - مشوار')

@section('content')
<div class="p-4 flex flex-col gap-4 max-w-lg mx-auto" x-data="driverManifestState()" x-init="init()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-black text-slate-800 dark:text-slate-100">بيان ركاب الرحلة</h1>
            <p class="text-xs text-slate-500">تأكيد الركاب قبل الانطلاق والاتصال الهاتفي المباشر</p>
        </div>
        <a href="{{ route('driver.dashboard') }}" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold rounded-xl hover:bg-slate-200 transition">
            ↩️ لوحة السائق
        </a>
    </div>

    <!-- Manifest Date Segment Tabs (Today vs Tomorrow) -->
    <div class="grid grid-cols-2 gap-2 p-1.5 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold select-none">
        <button 
            type="button" 
            @click="selectTab('today')"
            class="py-2.5 px-3 rounded-xl transition flex items-center justify-center gap-2"
            :class="activeTab === 'today' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
        >
            <span>🚐 ركاب اليوم</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono" :class="activeTab === 'today' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'" x-text="todayCount"></span>
        </button>

        <button 
            type="button" 
            @click="selectTab('tomorrow')"
            class="py-2.5 px-3 rounded-xl transition flex items-center justify-center gap-2"
            :class="activeTab === 'tomorrow' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
        >
            <span>📅 حجوزات الغد المسبقة</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono" :class="activeTab === 'tomorrow' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'" x-text="tomorrowCount"></span>
        </button>
    </div>

    <!-- Route & Capacity Overview Card -->
    <div class="p-4 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col gap-2 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-sm font-black text-slate-800 dark:text-slate-100" x-text="routeName || 'خط السير'"></span>
            <span class="text-xs px-2.5 py-1 rounded-full font-bold"
                :class="isTripActive ? 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'"
                x-text="isTripActive ? 'الرحلة جارية حالياً' : (activeTab === 'tomorrow' ? 'رحلة مجدولة للغد' : 'قبل الانطلاق')">
            </span>
        </div>
        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-700/60 text-xs">
            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60">
                <span class="text-slate-400 block text-[11px]">المقاعد المحجوزة:</span>
                <span class="font-bold text-slate-800 dark:text-slate-200 text-sm" x-text="bookedSeats + ' ركاب'"></span>
            </div>
            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60">
                <span class="text-slate-400 block text-[11px]">المقاعد الشاغرة:</span>
                <span class="font-bold text-teal-600 dark:text-teal-400 text-sm" x-text="freeSeats + ' مقاعد'"></span>
            </div>
        </div>
    </div>

    <!-- Alert / Toast Messages -->
    <div x-show="toastMessage" x-cloak class="p-3.5 rounded-2xl bg-teal-50 dark:bg-teal-950/40 border border-teal-300 dark:border-teal-700 text-teal-800 dark:text-teal-200 text-xs font-bold flex items-center justify-between">
        <span x-text="toastMessage"></span>
        <button @click="toastMessage = null" class="text-xs px-2">✕</button>
    </div>

    <!-- Loading State -->
    <div x-show="loading && riders.length === 0" class="p-8 text-center text-slate-400 text-xs">
        <span class="text-2xl block mb-2 animate-bounce">⏳</span>
        جاري تحميل بيان الركاب...
    </div>

    <!-- Empty State -->
    <div x-show="!loading && riders.length === 0" class="p-8 text-center bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 flex flex-col items-center gap-2">
        <span class="text-3xl">👥</span>
        <h3 class="font-bold text-sm text-slate-700 dark:text-slate-200" x-text="activeTab === 'tomorrow' ? 'لا توجد حجوزات مسبقة ليوم غد حتى الآن' : 'لا يوجد ركاب مسجلين على رحلة اليوم بعد'"></h3>
        <p class="text-xs text-slate-400" x-text="activeTab === 'tomorrow' ? 'ستظهر حجوزات ركاب الغد هنا فور قيامهم بالحجز المسبق' : 'ستظهر حجوزات الركاب هنا فور حجزهم للمقاعد على خط السير'"></p>
    </div>

    <!-- Riders Manifest List -->
    <div class="flex flex-col gap-3">
        <template x-for="r in riders" :key="r.id">
            <div class="p-4 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col gap-3 shadow-sm transition">
                <!-- Rider Header & Status -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-base">
                            👤
                        </span>
                        <div>
                            <span class="font-bold text-sm text-slate-800 dark:text-slate-100 block" x-text="r.rider_name"></span>
                            <span class="text-[11px] text-slate-400">
                                <span x-text="r.seats + ' مقعد'"></span> · الركوب: <span class="font-bold text-slate-700 dark:text-slate-300" x-text="r.pickup_stop"></span> · الأجرة: <span class="font-bold text-teal-600 dark:text-teal-400 font-mono" x-text="(r.fare_ils || 0) + ' ₪'"></span>
                            </span>
                        </div>
                    </div>
                    <span class="text-xs px-2.5 py-1 rounded-full font-bold"
                        :class="{
                            'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300': r.status === 'confirmed',
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': r.status === 'boarded',
                            'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': r.status === 'cancelled_by_driver' || r.status === 'cancelled_by_rider',
                            'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200': r.status === 'completed'
                        }"
                        x-text="r.status_arabic">
                    </span>
                </div>

                <!-- Privacy Gated Direct Tap-to-Call Link -->
                <div class="flex items-center gap-2 pt-1">
                    <template x-if="r.can_call && r.rider_phone">
                        <a :href="'tel:' + r.rider_phone" class="flex-1 py-2.5 px-3 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl flex items-center justify-center gap-2 shadow-sm transition active:scale-95">
                            <span>📞</span>
                            <span>اتصال بالراكب (<span x-text="r.rider_phone"></span>)</span>
                        </a>
                    </template>
                    <template x-if="!r.can_call">
                        <span class="text-xs text-slate-400 py-2 block italic">
                            رقم الهاتف غير متاح (الحجز ملغي أو غير مؤكد)
                        </span>
                    </template>
                </div>

                <!-- Pre-Trip Confirmation Controls (Confirm Boarding / Cancel Unready) -->
                <template x-if="r.status === 'confirmed'">
                    <div class="flex items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                        <button @click="confirmRider(r.id)" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm transition active:scale-95 flex items-center justify-center gap-1.5">
                            <span>✓</span>
                            <span>تأكيد الصعود للمركبة</span>
                        </button>
                        <button @click="cancelRider(r.id)" class="py-2.5 px-3 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 font-bold text-xs rounded-xl transition">
                            ✕ إلغاء لعدم الجاهزية
                        </button>
                    </div>
                </template>

                <!-- Boarded State Controls (Complete Trip) -->
                <template x-if="r.status === 'boarded'">
                    <div class="flex items-center justify-between p-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-700 text-xs">
                        <div class="flex items-center gap-1.5 text-emerald-800 dark:text-emerald-200 font-bold">
                            <span>✓</span>
                            <span>الراكب على متن المركبة</span>
                        </div>
                        <button @click="completeBooking(r)" class="px-3.5 py-1.5 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow-sm transition active:scale-95 flex items-center gap-1">
                            <span>🏁</span>
                            <span>تأكيد النزول والوصول</span>
                        </button>
                    </div>
                </template>

                <!-- Completed State -->
                <template x-if="r.status === 'completed'">
                    <div class="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 text-xs font-bold text-center">
                        ✓ اكتملت الرحلة وتم تأكيد الوصول بنجاح
                    </div>
                </template>

                <!-- Live Location Share Status & Actions (Only when confirmed and before boarding) -->
                <template x-if="r.status === 'confirmed'">
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5">
                            <span>📍</span>
                            <span class="font-bold">الموقع المباشر:</span>
                            <span class="text-slate-500" x-text="r.location_share ? r.location_share.status_arabic : 'غير مطلوب'"></span>
                        </div>

                        <!-- Request Location Button -->
                        <template x-if="!r.location_share || r.location_share.status === 'ended' || r.location_share.status === 'rejected'">
                            <button @click="requestLocation(r.id)" class="px-2.5 py-1 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 font-bold text-[11px] rounded-lg transition">
                                طلب موقع الراكب
                            </button>
                        </template>

                        <!-- Waiting for Approval -->
                        <template x-if="r.location_share && r.location_share.status === 'requested'">
                            <span class="text-amber-600 dark:text-amber-400 font-bold flex items-center gap-1 text-[11px]">
                                <span class="animate-pulse">⏳</span>
                                <span>بانتظار موافقة الراكب</span>
                            </span>
                        </template>

                        <!-- Active Location View -->
                        <template x-if="r.location_share && r.location_share.is_active">
                            <span class="text-teal-600 dark:text-teal-400 font-bold flex items-center gap-1.5 text-[11px]">
                                <span class="w-2.5 h-2.5 rounded-full bg-teal-500 animate-ping"></span>
                                <span x-text="r.location_share.last_latitude ? ('موقع الراكب متصل (' + r.location_share.last_latitude + ', ' + r.location_share.last_longitude + ')') : 'وافق الراكب على المشاركة 📍'"></span>
                            </span>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>

@push('scripts')
<script>
function driverManifestState() {
    return {
        routeName: '',
        isTripActive: false,
        bookedSeats: 0,
        freeSeats: 0,
        riders: [],
        loading: false,
        toastMessage: null,
        pollInterval: null,
        todayDate: '{{ today()->toDateString() }}',
        tomorrowDate: '{{ today()->addDay()->toDateString() }}',
        activeTab: '{{ request()->query('date') === today()->addDay()->toDateString() ? 'tomorrow' : 'today' }}',
        selectedDate: '{{ request()->query('date', today()->toDateString()) }}',
        todayCount: 0,
        tomorrowCount: 0,

        init() {
            this.fetchManifest();

            // Poll every 5 seconds so rider responses and location updates reflect live
            this.pollInterval = setInterval(() => {
                if (navigator.onLine && !this.loading) {
                    this.fetchManifest(false);
                }
            }, 5000);
        },

        selectTab(tab) {
            this.activeTab = tab;
            this.selectedDate = (tab === 'tomorrow') ? this.tomorrowDate : this.todayDate;
            this.fetchManifest(true);
        },

        async fetchManifest(showLoader = true) {
            if (showLoader) this.loading = true;
            try {
                const res = await fetch(`{{ route('api.driver.manifest') }}?date=${this.selectedDate}`);
                const data = await res.json();
                if (data.ok) {
                    this.routeName = data.route_name;
                    this.isTripActive = data.is_trip_active;
                    this.bookedSeats = data.booked_seats;
                    this.freeSeats = data.free_seats;
                    this.riders = data.manifest;
                    this.todayCount = data.today_count ?? 0;
                    this.tomorrowCount = data.tomorrow_count ?? 0;
                }
            } catch (err) {
                console.error('Failed to load driver manifest', err);
            } finally {
                this.loading = false;
            }
        },

        async confirmRider(bookingId) {
            try {
                const res = await fetch(`/api/driver/bookings/${bookingId}/confirm`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.ok) {
                    this.toastMessage = '✓ تم تأكيد صعود الراكب للمركبة بنجاح!';
                    this.fetchManifest();
                }
            } catch (err) {
                console.error('Failed to confirm rider', err);
            }
        },

        async completeBooking(riderItem) {
            const currentFare = riderItem.fare_ils || '';
            const promptMsg = currentFare ? 
                `تأكيد وصول الراكب إلى وجهته (${riderItem.dropoff_stop || ''}).\nالأجرة المسجلة: ${currentFare} ₪.\nيمكنك تأكيد المبلغ أو تعديله:` : 
                `تأكيد وصول الراكب إلى وجهته (${riderItem.dropoff_stop || ''}).\nأدخل المبلغ المحصل من الراكب بالشيكل (₪):`;
            
            const inputAmount = prompt(promptMsg, currentFare || '10');
            if (inputAmount === null) return;

            const fareVal = parseFloat(inputAmount) || (parseFloat(currentFare) || 0);

            try {
                const res = await fetch(`/api/bookings/${riderItem.id}/complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        fare_ils: fareVal
                    })
                });
                const data = await res.json();
                if (data.ok) {
                    this.toastMessage = `🏁 تم تأكيد الوصول وتسجيل الأجرة (${fareVal} ₪) بنجاح!`;
                    this.fetchManifest();
                }
            } catch (err) {
                console.error('Failed to complete booking', err);
            }
        },

        async cancelRider(bookingId) {
            if (!confirm('هل أنت متأكد من إلغاء حجز هذا الراكب لعدم الجاهزية؟')) return;

            try {
                const res = await fetch(`/api/driver/bookings/${bookingId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        reason: 'تم إلغاء حجزك من قِبل السائق لعدم الجاهزية عند بدء الرحلة'
                    })
                });
                const data = await res.json();
                if (data.ok) {
                    this.toastMessage = data.message;
                    this.fetchManifest();
                }
            } catch (err) {
                console.error('Failed to cancel rider', err);
            }
        },

        async requestLocation(bookingId) {
            try {
                const res = await fetch('{{ route('api.location.request') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ booking_id: bookingId })
                });
                const data = await res.json();
                if (data.ok) {
                    this.toastMessage = '📍 تم إرسال طلب مشاركة الموقع للراكب بنجاح';
                    this.fetchManifest();
                }
            } catch (err) {
                console.error('Failed to request location share', err);
            }
        }
    };
}
</script>
@endpush
@endsection
