@extends('layouts.app')

@section('title', 'حجوزاتي - مشوار')

@section('content')
<div class="p-4 flex flex-col gap-4 max-w-lg mx-auto" x-data="riderBookingsState({ initialBookings: @js($initialBookings ?? []) })" x-init="init()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-black text-slate-800 dark:text-slate-100">حجوزاتي ومتابعة الرحلة</h1>
            <p class="text-xs text-slate-500">حجوزات المقاعد المؤكدة والتواصل مع السائقين</p>
        </div>
        <button @click="fetchBookings()" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition">
            🔄 تحديث
        </button>
    </div>

    <!-- Offline Waiting to Send Banner -->
    <div x-show="outboxBooking" x-cloak class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 flex flex-col gap-2">
        <div class="flex items-center gap-2 text-amber-800 dark:text-amber-200 font-bold text-sm">
            <span class="animate-pulse">⏳</span>
            <span>الحجز في انتظار الإرسال (غير متصل بالإنترنت)</span>
        </div>
        <p class="text-xs text-amber-700 dark:text-amber-300 leading-relaxed">
            تم حفظ طلب الحجز على هاتفك محلياً. سيتم إرساله وتأكيد المقعد تلقائياً بمجرد عودة الاتصال بشبكة الإنترنت.
        </p>
        <div class="text-[11px] font-mono text-amber-600 dark:text-amber-400">
            الحالة: في انتظار الإرسال · المقاعد: <span x-text="outboxBooking ? outboxBooking.seats : 1"></span>
        </div>
    </div>

    <!-- Sync Toast Alert -->
    <div x-show="syncNotification" x-cloak class="p-4 rounded-2xl border transition-all"
        :class="syncNotification && syncNotification.ok ? 'bg-teal-50 dark:bg-teal-950/40 border-teal-300 dark:border-teal-700 text-teal-800 dark:text-teal-200' : 'bg-rose-50 dark:bg-rose-950/40 border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200'">
        <div class="flex items-center justify-between">
            <span class="font-bold text-sm" x-text="syncNotification ? syncNotification.message : ''"></span>
            <button @click="syncNotification = null" class="text-xs font-bold px-2 py-1">✕</button>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading && bookings.length === 0" class="p-8 text-center text-slate-400 text-xs">
        <span class="text-2xl block mb-2 animate-bounce">⏳</span>
        جاري تحميل الحجوزات...
    </div>

    <!-- Empty State -->
    <div x-show="!loading && bookings.length === 0 && !outboxBooking" class="p-8 text-center bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 flex flex-col items-center gap-3">
        <span class="text-4xl">🎟️</span>
        <h3 class="font-bold text-sm text-slate-700 dark:text-slate-200">لا توجد حجوزات نشطة حالياً</h3>
        <p class="text-xs text-slate-400 max-w-xs">يمكنك البحث عن مركبة على الخط وحجز مقعدك من الخريطة أو المخطط</p>
        <a href="{{ route('home') }}" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow-sm transition">
            استعراض المركبات المتاحة
        </a>
    </div>

    <!-- Bookings List -->
    <div class="flex flex-col gap-4">
        <template x-for="b in bookings" :key="b.id">
            <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col gap-4 shadow-sm">
                <!-- Header with Status Badge -->
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs text-slate-400 font-mono">حجز #<span x-text="b.id"></span></span>
                        <h3 class="font-black text-base text-slate-800 dark:text-slate-100" x-text="b.route ? b.route.name : 'مسار الرحلة'"></h3>
                    </div>
                    <span class="text-xs px-2.5 py-1 rounded-full font-bold"
                        :class="{
                            'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300': b.status === 'confirmed',
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': b.status === 'boarded',
                            'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': b.status === 'cancelled_by_driver' || b.status === 'cancelled_by_rider',
                            'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200': b.status === 'completed'
                        }"
                        x-text="b.status_arabic">
                    </span>
                </div>

                <!-- Boarded Notice -->
                <template x-if="b.status === 'boarded'">
                    <div class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 font-bold">
                            <span class="text-base">🚐</span>
                            <span>أنت الآن على متن المركبة — رحلة آمنة ومريحة!</span>
                        </div>
                        <span class="text-[11px] text-emerald-700 dark:text-emerald-300">الوجهة: <b x-text="b.dropoff_stop"></b></span>
                    </div>
                </template>

                <!-- Driver Cancellation Notice -->
                <template x-if="b.status === 'cancelled_by_driver'">
                    <div class="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 flex flex-col gap-1 text-xs">
                        <div class="font-bold flex items-center gap-1.5">
                            <span>⚠️</span>
                            <span>إشعار إلغاء من السائق:</span>
                        </div>
                        <p x-text="b.cancellation_reason || 'تم إلغاء حجزك من قِبل السائق لعدم الجاهزية عند بدء الرحلة'"></p>
                    </div>
                </template>

                <!-- Route Drift Alert Banner -->
                <template x-if="b.route && b.route.drifting && b.status === 'confirmed'">
                    <div class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-100 flex items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="text-lg animate-pulse">⚠️</span>
                            <div>
                                <span class="font-bold block">المركبة غيرت مسارها حالياً</span>
                                <span class="text-[11px] text-amber-700 dark:text-amber-300">السائق خارج خط السير المعتاد، اضغط للاتصال</span>
                            </div>
                        </div>
                        <template x-if="b.driver && b.driver.phone">
                            <a :href="'tel:' + b.driver.phone" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl flex items-center gap-1 text-xs whitespace-nowrap shadow-sm">
                                <span>📞</span>
                                <span>اتصال</span>
                            </a>
                        </template>
                    </div>
                </template>

                <!-- Ride Stops & Details -->
                <div class="grid grid-cols-2 gap-2 text-xs p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700">
                    <div>
                        <span class="text-slate-400 block text-[11px]">محطة الركوب:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200" x-text="b.pickup_stop || 'غير محدد'"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">محطة النزول:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200" x-text="b.dropoff_stop || 'حسب الاتفاق'"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">المقاعد المحجوزة:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200" x-text="b.seats + ' مقاعد'"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">نوع المركبة:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200" x-text="b.vehicle ? b.vehicle.type_arabic : 'مركبة'"></span>
                    </div>
                    <div class="col-span-2 pt-1 border-t border-slate-200/60 dark:border-slate-700/60 flex items-center justify-between">
                        <span class="text-slate-400 text-[11px]">الأجرة المحسوبة للمسار:</span>
                        <span class="font-black text-teal-600 dark:text-teal-400 font-mono text-xs" x-text="b.fare_ils ? b.fare_ils + ' ₪' : 'حسب الاتفاق'"></span>
                    </div>
                </div>

                <!-- Driver Contact Card (Privacy Enforced: Phone unlocked only when confirmed) -->
                <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center gap-2.5">
                        <span class="text-2xl">🚗</span>
                        <div>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-100 block" x-text="b.driver ? b.driver.name : 'السائق'"></span>
                            <span class="text-[11px] text-slate-400" x-text="b.route ? b.route.last_landmark + ' (' + b.route.position_age_arabic + ')' : ''"></span>
                        </div>
                    </div>

                    <!-- Direct Tap-to-Call Link (Enforced by Backend) -->
                    <template x-if="b.driver && b.driver.phone && b.status === 'confirmed'">
                        <a :href="'tel:' + b.driver.phone" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl flex items-center gap-1.5 shadow-sm transition active:scale-95">
                            <span>📞</span>
                            <span>اتصال بالسائق</span>
                        </a>
                    </template>
                </div>

                <!-- Live Location Sharing Section -->
                <!-- 1. Driver Requested Location Prompt -->
                <template x-if="b.location_share && b.location_share.status === 'requested'">
                    <div class="p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 flex flex-col gap-2.5">
                        <div class="flex items-center gap-2 text-indigo-900 dark:text-indigo-200 text-xs font-bold">
                            <span class="text-base">📍</span>
                            <span>طلب موقع من السائق لتسهيل اللقاء:</span>
                        </div>
                        <p class="text-xs text-indigo-800/80 dark:text-indigo-300/80 leading-relaxed">
                            هل توافق على مشاركة موقعك المباشر مع السائق حتى موعد انطلاق الرحلة (صلاحية 15 دقيقة فقط)؟
                        </p>
                        <div class="flex items-center gap-2 pt-1">
                            <button @click="respondLocationShare(b.location_share.id, 'accept')" class="flex-1 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition">
                                موافق (نعم)
                            </button>
                            <button @click="respondLocationShare(b.location_share.id, 'reject')" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition">
                                رفض (لا)
                            </button>
                        </div>
                    </div>
                </template>

                <!-- 2. Visible "You Are Sharing" Active State Banner -->
                <template x-if="b.location_share && b.location_share.is_active">
                    <div class="p-4 rounded-2xl bg-teal-50 dark:bg-teal-950/40 border border-teal-300 dark:border-teal-700 flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-teal-800 dark:text-teal-200 text-xs font-black">
                                <span class="w-2.5 h-2.5 rounded-full bg-teal-500 animate-ping"></span>
                                <span>أنت تشارك موقعك المباشر الآن مع السائق</span>
                            </div>
                            <button @click="endLocationShare(b.location_share.id)" class="px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white font-bold text-[11px] rounded-lg transition">
                                إنهاء المشاركة
                            </button>
                        </div>
                        <p class="text-[11px] text-teal-700 dark:text-teal-300">
                            يتم إرسال موقعك مباشرة لمساعدة السائق في الوصول إليك. تنتهي المشاركة تلقائياً بعد 15 دقيقة أو فور صعودك للمركبة.
                        </p>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>

@push('scripts')
<script src="/js/gaza-geo-sync.js"></script>
<script>
function riderBookingsState(config) {
    const serverBookings = (config && config.initialBookings) || [];
    let cachedBookings = [];
    try {
        const raw = localStorage.getItem('mishwar_cached_bookings');
        if (raw) cachedBookings = JSON.parse(raw);
    } catch (e) {}

    const startingBookings = serverBookings.length > 0 ? serverBookings : cachedBookings;

    return {
        bookings: startingBookings,
        loading: startingBookings.length === 0,
        outboxBooking: null,
        syncNotification: null,
        locationInterval: null,

        init() {
            this.checkOutbox();
            if (this.bookings.length === 0) {
                this.fetchBookings(true);
            } else {
                // Background sync silently without blocking UI
                setTimeout(() => {
                    if (navigator.onLine) this.fetchBookings(false);
                }, 1500);
            }

            // Listen for network reconnection to trigger outbox sync
            window.addEventListener('online', () => {
                this.syncOutbox();
                this.fetchBookings(false);
            });

            // Periodic background refresh
            setInterval(() => {
                if (navigator.onLine) {
                    this.fetchBookings(false);
                }
            }, 15000);
        },

        checkOutbox() {
            try {
                const stored = localStorage.getItem('mishwar_booking_outbox');
                if (stored) {
                    this.outboxBooking = JSON.parse(stored);
                    if (navigator.onLine) {
                        this.syncOutbox();
                    }
                }
            } catch (e) {}
        },

        async syncOutbox() {
            if (!this.outboxBooking || !navigator.onLine) return;

            try {
                const payload = {
                    route_id: this.outboxBooking.route_id,
                    seats: this.outboxBooking.seats || 1,
                    pickup_stop: this.outboxBooking.pickup_stop,
                    dropoff_stop: this.outboxBooking.dropoff_stop,
                    fare_ils: this.outboxBooking.fare_ils,
                    client_mutation_id: this.outboxBooking.client_mutation_id
                };

                const res = await fetch('{{ route('api.bookings.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok && data.ok) {
                    localStorage.removeItem('mishwar_booking_outbox');
                    this.outboxBooking = null;
                    this.syncNotification = {
                        ok: true,
                        message: 'تم تأكيد حجزك بنجاح بعد استعادة الاتصال بالإنترنت'
                    };
                    this.fetchBookings(false);
                } else {
                    this.syncNotification = {
                        ok: false,
                        message: data.error || 'عذراً، لم تتوفر مقاعد كافية على هذه الرحلة'
                    };
                    localStorage.removeItem('mishwar_booking_outbox');
                    this.outboxBooking = null;
                }
            } catch (err) {
                console.warn('Sync outbox failed, will retry next time online', err);
            }
        },

        async fetchBookings(showLoader = false) {
            if (showLoader && this.bookings.length === 0) {
                this.loading = true;
            }
            try {
                const res = await fetch('{{ route('api.rider.bookings') }}');
                const data = await res.json();
                if (data.ok) {
                    this.bookings = data.bookings;
                    try {
                        localStorage.setItem('mishwar_cached_bookings', JSON.stringify(data.bookings));
                    } catch (e) {}
                    this.manageLocationSharingLoop();
                }
            } catch (err) {
                console.warn('Offline: viewing cached bookings', err);
            } finally {
                this.loading = false;
            }
        },

        async respondLocationShare(shareId, action) {
            try {
                const res = await fetch(`/api/location-shares/${shareId}/respond`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ action: action })
                });

                const data = await res.json();
                if (data.ok) {
                    if (action === 'accept') {
                        // Transmit immediate initial coordinates so driver sees location right away
                        const lat = 31.3456;
                        const lng = 34.3182;
                        fetch(`/api/location-shares/${shareId}/update`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ lat: lat, lng: lng })
                        }).catch(() => {});
                    }
                    this.fetchBookings();
                }
            } catch (err) {
                console.error('Failed to respond to location share', err);
            }
        },

        async endLocationShare(shareId) {
            try {
                const res = await fetch(`/api/location-shares/${shareId}/end`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });

                const data = await res.json();
                if (data.ok) {
                    this.fetchBookings();
                }
            } catch (err) {
                console.error('Failed to end location share', err);
            }
        },

        manageLocationSharingLoop() {
            const activeShare = this.bookings.find(b => b.location_share && b.location_share.is_active);

            if (activeShare && !this.locationInterval) {
                let lastSent = null;
                this.locationInterval = setInterval(() => {
                    if (!navigator.onLine) return;

                    const sendUpdate = (lat, lng) => {
                        const qLat = window.GazaGeoSync ? window.GazaGeoSync.quantize(lat) : lat;
                        const qLng = window.GazaGeoSync ? window.GazaGeoSync.quantize(lng) : lng;

                        if (window.GazaGeoSync && !window.GazaGeoSync.worthSending(lastSent, { lat: qLat, lng: qLng })) {
                            return;
                        }

                        fetch(`/api/location-shares/${activeShare.location_share.id}/update`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ lat: qLat, lng: qLng })
                        }).then(() => {
                            lastSent = { lat: qLat, lng: qLng, sentAt: Date.now() };
                        }).catch(() => {});
                    };

                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            pos => sendUpdate(pos.coords.latitude, pos.coords.longitude),
                            err => {
                                console.warn('GPS location unavailable, sharing paused:', err.message);
                            },
                            { timeout: 10000, enableHighAccuracy: true, maximumAge: 10000 }
                        );
                    }
                }, 10000);
            } else if (!activeShare && this.locationInterval) {
                clearInterval(this.locationInterval);
                this.locationInterval = null;
            }
        }
    };
}
</script>
@endpush
@endsection
