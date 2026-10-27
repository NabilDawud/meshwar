@extends('layouts.app')

@section('title', 'مقارنة عروض السائقين - مشوار')

@section('content')
<div class="p-4 flex flex-col gap-4 max-w-lg mx-auto" x-data="requestDetailState({{ $id }})" x-init="init()">
    <!-- Navigation Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('rides.requests') }}" class="text-xs font-bold text-teal-600 dark:text-teal-400 flex items-center gap-1 hover:underline">
            <span>➔</span>
            <span>العودة لطلبات المشاوير</span>
        </a>
        <span class="text-xs text-slate-400 font-mono">طلب #{{ $id }}</span>
    </div>

    <!-- Offline Alert -->
    <div x-show="isOffline" x-cloak class="p-3 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 text-xs font-bold flex items-center gap-2">
        <span>⚠️</span>
        <span>أنت غير متصل بالإنترنت. يتم عرض بيانات العروض المحفوظة مسبقاً.</span>
    </div>

    <!-- Success / Action Alert -->
    <div x-show="actionMessage" x-cloak class="p-4 rounded-2xl bg-teal-50 dark:bg-teal-950/40 border border-teal-300 dark:border-teal-700 text-teal-800 dark:text-teal-200 text-xs font-bold flex items-center justify-between">
        <span x-text="actionMessage"></span>
        <button @click="actionMessage = null" class="px-2">✕</button>
    </div>

    <!-- Loading State -->
    <div x-show="loading && !requestData" class="p-12 text-center text-slate-400 text-xs">
        <span class="text-3xl block mb-2 animate-bounce">⏳</span>
        جاري تحميل تفاصيل الطلب وعروض السائقين...
    </div>

    <!-- Request Overview Card -->
    <template x-if="requestData">
        <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col gap-3 shadow-sm">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-xl">🚕</span>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-black text-base text-slate-800 dark:text-slate-100" x-text="requestData.origin_name"></span>
                            <span class="text-slate-400 text-xs">➔</span>
                            <span class="font-black text-base text-slate-800 dark:text-slate-100" x-text="requestData.destination_name"></span>
                        </div>
                        <span class="text-[11px] text-slate-400" x-text="'تاريخ النشر: ' + (requestData.created_at ? new Date(requestData.created_at).toLocaleDateString('ar-EG') : '')"></span>
                    </div>
                </div>

                <span class="text-xs px-2.5 py-1 rounded-full font-bold"
                    :class="{
                        'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300': requestData.status === 'open',
                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': requestData.status === 'accepted',
                        'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300': requestData.status === 'cancelled'
                    }"
                    x-text="requestData.status_arabic">
                </span>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-3 gap-2 text-xs p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-center">
                <div>
                    <span class="text-slate-400 block text-[10px]">تاريخ الانطلاق</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 font-mono" x-text="requestData.departure_date"></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px]">الوقت المفضل</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200" x-text="requestData.departure_window"></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px]">المقاعد المطلوبة</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200" x-text="requestData.seats_needed"></span>
                </div>
            </div>

            <template x-if="requestData.notes">
                <p class="text-xs text-slate-500 bg-slate-50 dark:bg-slate-900/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800" x-text="'ملاحظاتك: ' + requestData.notes"></p>
            </template>
        </div>
    </template>

    <!-- Side-by-Side Offers Header -->
    <div class="flex items-center justify-between pt-2">
        <h2 class="text-sm font-black text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
            <span>🏷️</span>
            <span>عروض السائقين المقدمة</span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300 font-bold" x-text="requestData ? requestData.offers.length : 0"></span>
        </h2>
        <span class="text-[11px] text-slate-400">قارن السعر والمركبة واختر الأنسب</span>
    </div>

    <!-- Empty Offers State -->
    <template x-if="requestData && requestData.offers.length === 0">
        <div class="p-8 text-center bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 flex flex-col items-center gap-2">
            <span class="text-3xl">⏳</span>
            <p class="font-bold text-xs text-slate-700 dark:text-slate-200">في انتظار عروض السائقين الأولى</p>
            <p class="text-[11px] text-slate-400 max-w-xs">يتم إشعار السائقين المتواجدين على خط السير حالياً، وستظهر عروضهم هنا فور تقديمها.</p>
        </div>
    </template>

    <!-- Side-by-Side Offers List -->
    <div class="flex flex-col gap-4" x-show="requestData && requestData.offers.length > 0">
        <template x-for="offer in requestData ? requestData.offers : []" :key="offer.id">
            <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border flex flex-col gap-3.5 shadow-sm transition"
                :class="{
                    'border-emerald-500 ring-2 ring-emerald-500/20 bg-emerald-50/20 dark:bg-emerald-950/20': offer.status === 'accepted',
                    'border-slate-200 dark:border-slate-700': offer.status === 'pending',
                    'opacity-60 border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50': offer.status === 'rejected'
                }">
                <!-- Top Header: Driver & Price -->
                <div class="flex items-start justify-between">
                    <!-- Driver Info -->
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-teal-100 dark:bg-teal-900/50 border border-teal-200 dark:border-teal-700 flex items-center justify-center text-xl font-black text-teal-700 dark:text-teal-300">
                            👨‍✈️
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-sm text-slate-800 dark:text-slate-100" x-text="offer.driver.name"></span>
                                <span class="text-[11px] text-amber-500 flex items-center gap-0.5">
                                    <span>★</span>
                                    <span class="font-mono font-bold" x-text="offer.driver.rating"></span>
                                </span>
                            </div>
                            <span class="text-[11px] text-slate-400 font-mono" x-text="'عرض مقدم: ' + (offer.created_at ? new Date(offer.created_at).toLocaleTimeString('ar-EG', {hour: '2-digit', minute:'2-digit'}) : '')"></span>
                        </div>
                    </div>

                    <!-- Price in ILS -->
                    <div class="text-left">
                        <div class="flex items-baseline gap-1 text-teal-600 dark:text-teal-400 font-black">
                            <span class="text-2xl font-mono" x-text="offer.price_ils"></span>
                            <span class="text-xs">₪</span>
                        </div>
                        <span class="text-[10px] text-slate-400 block">شامل كامل المشوار</span>
                    </div>
                </div>

                <!-- Vehicle Details & Photo -->
                <template x-if="offer.vehicle">
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
                        <div class="flex flex-col gap-1 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-700 dark:text-slate-300" x-text="offer.vehicle.type_arabic"></span>
                                <span class="text-[10px] text-slate-400">·</span>
                                <span class="text-slate-500" x-text="offer.vehicle.seat_count + ' مقاعد'"></span>
                            </div>
                            <span class="text-[11px] text-slate-400 font-mono" x-text="'لوحة: ' + offer.vehicle.license_plate"></span>
                        </div>

                        <!-- Recognition Photo -->
                        <template x-if="offer.vehicle.photo">
                            <div class="relative group cursor-pointer" @click="selectedPhoto = offer.vehicle.photo">
                                <img :src="offer.vehicle.photo" alt="صورة المركبة" class="w-16 h-12 object-cover rounded-xl border border-slate-200 dark:border-slate-700">
                                <span class="absolute bottom-0 right-0 bg-slate-900/70 text-[9px] text-white px-1 rounded-tl-md">معاينة</span>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Driver's Note -->
                <template x-if="offer.notes">
                    <p class="text-xs text-slate-600 dark:text-slate-300 italic bg-amber-50/50 dark:bg-amber-950/20 p-2.5 rounded-xl border border-amber-200/60 dark:border-amber-800/40" x-text="'ملاحظة السائق: ' + offer.notes"></p>
                </template>

                <!-- Status Badge (if accepted / rejected) -->
                <template x-if="offer.status === 'accepted'">
                    <div class="p-2.5 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-200 text-xs font-bold flex items-center justify-between">
                        <span class="flex items-center gap-1.5">
                            <span>✓</span>
                            <span>تم قبول هذا العرض وتأكيد المشوار</span>
                        </span>
                        <span class="text-[10px] text-emerald-700 dark:text-emerald-300">تمت مشاركة رقمك للتنسيق</span>
                    </div>
                </template>

                <template x-if="offer.status === 'rejected'">
                    <div class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 text-xs font-medium text-center">
                        تم اختيار عرض سائق آخر لهذا الطلب
                    </div>
                </template>

                <!-- Action Buttons: Call, Chat & Accept -->
                <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center gap-2">
                    <!-- Tap-to-Call Driver (Always available to rider on offers) -->
                    <template x-if="offer.driver.phone">
                        <a :href="'tel:' + offer.driver.phone" class="px-3 py-2 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 border border-emerald-300 dark:border-emerald-800 text-xs font-bold rounded-xl flex items-center gap-1.5 transition active:scale-95 shadow-sm">
                            <span>📞</span>
                            <span>اتصال</span>
                        </a>
                    </template>

                    <!-- Direct Chat Thread Link -->
                    <a :href="'/chat/' + offer.id" class="flex-1 px-3 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-100 text-xs font-bold rounded-xl flex items-center justify-center gap-1.5 transition active:scale-95">
                        <span>💬</span>
                        <span>محادثة السائق</span>
                        <template x-if="offer.messages_count > 0">
                            <span class="px-1.5 py-0.2 rounded-full bg-teal-600 text-white text-[10px]" x-text="offer.messages_count"></span>
                        </template>
                    </a>

                    <!-- Accept Offer Button (Shown only when request is still open) -->
                    <template x-if="requestData.status === 'open' && offer.status === 'pending'">
                        <button @click="acceptOffer(offer.id)" :disabled="submitting" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-black rounded-xl shadow-sm transition active:scale-95 flex items-center gap-1">
                            <span>✓</span>
                            <span>قبول العرض</span>
                        </button>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <!-- Vehicle Photo Modal -->
    <div x-show="selectedPhoto" x-cloak class="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="selectedPhoto = null" class="max-w-md w-full bg-white dark:bg-slate-800 rounded-3xl p-4 flex flex-col gap-3 shadow-2xl">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200">صورة التعرف على المركبة</span>
                <button @click="selectedPhoto = null" class="text-slate-400 font-bold p-1">✕</button>
            </div>
            <img :src="selectedPhoto" alt="صورة المركبة" class="w-full h-64 object-cover rounded-2xl border border-slate-200 dark:border-slate-700">
        </div>
    </div>
</div>

<script>
function requestDetailState(requestId) {
    return {
        requestId: requestId,
        requestData: null,
        loading: true,
        submitting: false,
        isOffline: !navigator.onLine,
        actionMessage: null,
        selectedPhoto: null,

        init() {
            // Load cached version first for instant offline access
            const cached = localStorage.getItem('meshwar_req_detail_' + this.requestId);
            if (cached) {
                try {
                    this.requestData = JSON.parse(cached);
                    this.loading = false;
                } catch (e) {
                    console.error('Error parsing cached request detail', e);
                }
            }

            this.fetchDetails();

            window.addEventListener('online', () => {
                this.isOffline = false;
                this.fetchDetails();
            });

            window.addEventListener('offline', () => {
                this.isOffline = true;
            });
        },

        async fetchDetails() {
            try {
                const res = await fetch('/api/requests/' + this.requestId);
                if (res.ok) {
                    const data = await res.json();
                    this.requestData = data.request;
                    localStorage.setItem('meshwar_req_detail_' + this.requestId, JSON.stringify(data.request));
                }
            } catch (err) {
                console.warn('Viewing request detail offline', err);
            } finally {
                this.loading = false;
            }
        },

        async acceptOffer(offerId) {
            if (!confirm('هل أنت متأكد من قبول هذا العرض وتأكيد المشوار الخاص؟ سيتم إشعار السائق ومشاركة رقم هاتفك معه للتنسيق.')) {
                return;
            }

            this.submitting = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch(`/api/requests/${this.requestId}/offers/${offerId}/accept`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                    },
                });

                const data = await res.json();
                if (res.ok && data.ok) {
                    this.requestData = data.request;
                    localStorage.setItem('meshwar_req_detail_' + this.requestId, JSON.stringify(data.request));
                    this.actionMessage = '🎉 تم قبول العرض وتأكيد المشوار بنجاح! تم فتح بيانات التواصل مع السائق.';
                } else {
                    alert(data.error || 'حدث خطأ أثناء قبول العرض');
                }
            } catch (err) {
                alert('أنت غير متصل بالإنترنت حالياً. يلزم الاتصال لتأكيد حجز العرض وإشعار السائق.');
            } finally {
                this.submitting = false;
            }
        }
    };
}
</script>
@endsection
