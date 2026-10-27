@extends('layouts.app')

@section('title', 'فرص وطلبات المشاوير الخاصة - مشوار')

@section('content')
<div class="p-4 flex flex-col gap-4 max-w-lg mx-auto" x-data="driverOffersState()" x-init="init()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span>💼</span>
                <span>فرص المشاوير الخاصة</span>
            </h1>
            <p class="text-xs text-slate-500">طلبات مشاوير خاصة من الركاب يمكنك تقديم عروض أسعار عليها</p>
        </div>
        <button @click="fetchRequests()" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition">
            🔄 تحديث
        </button>
    </div>

    <!-- Prominent Notification Banner for Accepted Offers -->
    <template x-if="acceptedJobsCount > 0">
        <div class="p-4 rounded-3xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-md flex flex-col gap-2.5 transition">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-2xl animate-bounce">🎉</span>
                    <span class="font-black text-sm">تم قبول عرضك لمشوار خاص!</span>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-white/20 text-xs font-bold font-mono" x-text="acceptedJobsCount + ' مشوار مؤكد'"></span>
            </div>
            <p class="text-xs text-emerald-50 leading-relaxed">
                وافق الراكب على عرض السعر المقدم من طرفك. تم فك قفل رقم هاتف الراكب للتنسيق المباشر والبدء في تنفيذ المشوار.
            </p>
            <div class="flex items-center gap-2 pt-1">
                <button @click="activeTab = 'accepted'" class="px-3.5 py-1.5 bg-white text-emerald-800 font-bold text-xs rounded-xl hover:bg-emerald-50 transition shadow-sm flex items-center gap-1">
                    <span>عرض المشاوير المقبولة للتنفيذ</span>
                    <span>➔</span>
                </button>
            </div>
        </div>
    </template>

    <!-- Outbox Banner (Offline Offers Waiting to Sync) -->
    <div x-show="outboxOffers.length > 0" x-cloak class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 flex flex-col gap-2">
        <div class="flex items-center gap-2 text-amber-800 dark:text-amber-200 font-bold text-sm">
            <span class="animate-pulse">⏳</span>
            <span>عروض أسعار في انتظار الإرسال (<span x-text="outboxOffers.length"></span>)</span>
        </div>
        <p class="text-xs text-amber-700 dark:text-amber-300 leading-relaxed">
            تم حفظ عروض أسعارك محلياً على الهاتف لعدم وجود اتصال. ستُرسل تلقائياً فور عودة الإنترنت.
        </p>
    </div>

    <!-- Notification Toast -->
    <div x-show="notification" x-cloak class="p-4 rounded-2xl bg-teal-50 dark:bg-teal-950/40 border border-teal-300 dark:border-teal-700 text-teal-800 dark:text-teal-200 text-xs font-bold flex items-center justify-between">
        <span x-text="notification"></span>
        <button @click="notification = null" class="px-2">✕</button>
    </div>

    <!-- Tab Filters (All, Accepted, Open) -->
    <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-800/80 rounded-2xl text-xs font-bold">
        <button @click="activeTab = 'all'" :class="activeTab === 'all' ? 'bg-white dark:bg-slate-700 text-teal-600 dark:text-teal-400 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="flex-1 py-2 rounded-xl transition text-center">
            جميع الطلبات (<span x-text="requests.length"></span>)
        </button>
        <button @click="activeTab = 'accepted'" :class="activeTab === 'accepted' ? 'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1">
            <span>المقبولة للتنفيذ</span>
            <template x-if="acceptedJobsCount > 0">
                <span class="px-1.5 py-0.2 rounded-full bg-emerald-600 text-white text-[10px]" x-text="acceptedJobsCount"></span>
            </template>
        </button>
        <button @click="activeTab = 'open'" :class="activeTab === 'open' ? 'bg-white dark:bg-slate-700 text-teal-600 dark:text-teal-400 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="flex-1 py-2 rounded-xl transition text-center">
            فرص متاحة (<span x-text="openOpportunitiesCount"></span>)
        </button>
    </div>

    <!-- Loading State -->
    <div x-show="loading && requests.length === 0" class="p-12 text-center text-slate-400 text-xs">
        <span class="text-3xl block mb-2 animate-bounce">⏳</span>
        جاري تحميل طلبات المشاوير الخاصة...
    </div>

    <!-- Empty State -->
    <div x-show="!loading && filteredRequests.length === 0" class="p-8 text-center bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 flex flex-col items-center gap-3">
        <span class="text-4xl">📭</span>
        <h3 class="font-bold text-sm text-slate-700 dark:text-slate-200" x-text="activeTab === 'accepted' ? 'لا توجد مشاوير مقبولة حالياً' : 'لا توجد طلبات مشاوير خاصة مفتوحة حالياً'"></h3>
        <p class="text-xs text-slate-400 max-w-xs">ستظهر هنا أي طلبات ينشرها الركاب للتنقل بين مدن ومناطق قطاع غزة فور نشرها.</p>
    </div>

    <!-- Requests List -->
    <div class="flex flex-col gap-4">
        <template x-for="r in filteredRequests" :key="r.id">
            <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border flex flex-col gap-3.5 shadow-sm transition"
                :class="{
                    'border-emerald-500 ring-2 ring-emerald-500/20 bg-emerald-50/10 dark:bg-emerald-950/10': r.is_accepted,
                    'border-slate-200 dark:border-slate-700': !r.is_accepted
                }">
                <!-- Header: Route & Status -->
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-black text-sm text-slate-800 dark:text-slate-100" x-text="r.origin_name"></span>
                            <span class="text-slate-400 text-xs">➔</span>
                            <span class="font-black text-sm text-slate-800 dark:text-slate-100" x-text="r.destination_name"></span>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-slate-400 mt-1">
                            <span>الراكب: <b class="text-slate-600 dark:text-slate-300" x-text="r.rider_name"></b></span>
                            <span>•</span>
                            <span x-text="r.offers_count + ' عروض مقدمة'"></span>
                        </div>
                    </div>

                    <!-- Badge -->
                    <template x-if="r.my_offer">
                        <span class="text-xs px-2.5 py-1 rounded-full font-bold"
                            :class="{
                                'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300': r.my_offer.status === 'pending',
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 ring-2 ring-emerald-500': r.my_offer.status === 'accepted' || r.is_accepted,
                                'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300': r.my_offer.status === 'rejected'
                            }"
                            x-text="r.is_accepted ? 'تم قبول عرضك ✓' : r.my_offer.status_arabic">
                        </span>
                    </template>
                    <template x-if="!r.my_offer">
                        <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                            فرصة جديدة
                        </span>
                    </template>
                </div>

                <!-- Schedule & Seats Details -->
                <div class="grid grid-cols-3 gap-2 text-xs p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-center">
                    <div>
                        <span class="text-slate-400 block text-[10px]">تاريخ المشوار</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300 font-mono" x-text="r.departure_date"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px]">الوقت المفضل</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300" x-text="r.departure_window"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px]">المقاعد المطلوبة</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300" x-text="r.seats_needed + ' ركاب'"></span>
                    </div>
                </div>

                <template x-if="r.notes">
                    <p class="text-xs text-slate-500 italic bg-slate-50 dark:bg-slate-900/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800" x-text="'ملاحظة الراكب: ' + r.notes"></p>
                </template>

                <!-- If My Offer Accepted: Reveal Rider Phone & Call Button & Complete Action -->
                <template x-if="r.is_accepted && r.status === 'accepted'">
                    <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-300 dark:border-emerald-700 flex flex-col gap-2.5">
                        <div class="flex items-center justify-between">
                            <span class="font-black text-xs text-emerald-800 dark:text-emerald-200 flex items-center gap-1.5">
                                <span>🎉</span>
                                <span>تم قبول عرضك وتأكيد هذا المشوار!</span>
                            </span>
                            <span class="font-mono font-black text-sm text-emerald-700 dark:text-emerald-300" x-text="(r.my_offer ? r.my_offer.price_ils : '') + ' ₪'"></span>
                        </div>
                        <p class="text-[11px] text-emerald-700 dark:text-emerald-300">
                            تم فك قفل هاتف الراكب للتنسيق المباشر قبل التحرك:
                        </p>
                        <div class="flex items-center justify-between pt-1">
                            <span class="font-mono font-bold text-sm text-slate-800 dark:text-slate-100" x-text="r.rider_phone"></span>
                            <a :href="'tel:' + r.rider_phone" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm transition active:scale-95 flex items-center gap-1.5">
                                <span>📞</span>
                                <span>اتصال بالراكب</span>
                            </a>
                        </div>
                        <div class="pt-2 border-t border-emerald-200 dark:border-emerald-800/60 flex items-center justify-end">
                            <button @click="completeTrip(r.id)" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition active:scale-95 flex items-center gap-1">
                                <span>🏁</span>
                                <span>إنهاء المشوار بعد الوصول</span>
                            </button>
                        </div>
                    </div>
                </template>

                <template x-if="r.status === 'completed'">
                    <div class="p-3 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold text-center">
                        ✓ اكتمل هذا المشوار بنجاح
                    </div>
                </template>

                <!-- Actions Bar -->
                <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-2">
                    <template x-if="r.my_offer">
                        <div class="flex items-center gap-2 w-full">
                            <div class="flex-1 flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                                <span>عرضك:</span>
                                <span class="font-mono font-black text-sm text-teal-600 dark:text-teal-400" x-text="r.my_offer.price_ils + ' ₪'"></span>
                            </div>

                            <!-- Chat Link -->
                            <a :href="'/chat/' + r.my_offer.id" class="px-3 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-100 text-xs font-bold rounded-xl flex items-center gap-1.5 transition active:scale-95">
                                <span>💬</span>
                                <span>محادثة الراكب</span>
                            </a>

                            <!-- Update Offer if Pending -->
                            <template x-if="r.my_offer.status === 'pending' && r.status === 'open'">
                                <button @click="openOfferModal(r)" class="px-3 py-2 bg-teal-50 dark:bg-teal-950/60 text-teal-700 dark:text-teal-300 hover:bg-teal-100 border border-teal-200 dark:border-teal-800 text-xs font-bold rounded-xl transition active:scale-95">
                                    تعديل العرض
                                </button>
                            </template>
                        </div>
                    </template>

                    <template x-if="!r.my_offer && r.status === 'open'">
                        <div class="flex items-center justify-end w-full">
                            <button @click="openOfferModal(r)" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-95 flex items-center gap-1.5">
                                <span>🏷️</span>
                                <span>تقديم عرض سعر</span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <!-- Submit / Edit Offer Modal -->
    <div x-show="showOfferModal" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4">
        <div @click.away="showOfferModal = false" class="w-full max-w-lg bg-white dark:bg-slate-800 rounded-t-3xl sm:rounded-3xl p-6 border border-slate-200 dark:border-slate-700 flex flex-col gap-4 shadow-2xl">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700">
                <div class="flex items-center gap-2">
                    <span class="text-xl">🏷️</span>
                    <h2 class="text-base font-black text-slate-800 dark:text-slate-100">تقديم عرض سعر للمشوار</h2>
                </div>
                <button @click="showOfferModal = false" class="text-slate-400 font-bold p-1">✕</button>
            </div>

            <template x-if="activeRequest">
                <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-xs flex items-center justify-between">
                    <div>
                        <span class="font-bold text-slate-700 dark:text-slate-300" x-text="activeRequest.origin_name + ' ➔ ' + activeRequest.destination_name"></span>
                        <span class="text-[11px] text-slate-400 block" x-text="'المقاعد: ' + activeRequest.seats_needed + ' · التاريخ: ' + activeRequest.departure_date"></span>
                    </div>
                </div>
            </template>

            <form @submit.prevent="submitOffer" class="flex flex-col gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">عرض السعر المطلوب (شيكل - ₪)</label>
                    <div class="relative">
                        <input type="number" x-model.number="offerForm.price_ils" min="10" max="2000" step="1" required placeholder="مثال: 70" class="w-full h-11 px-3 pe-10 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-base font-mono font-bold focus:ring-2 focus:ring-teal-500 outline-none">
                        <span class="absolute end-3 top-3 text-xs font-bold text-slate-400">₪</span>
                    </div>
                    <span class="text-[10px] text-slate-400 mt-1 block">السعر شامل كامل المشوار لجميع المقاعد المطلوبة</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات العرض (اختياري)</label>
                    <textarea x-model="offerForm.notes" rows="2" placeholder="مثال: سيارة خاصة مكيفة وجاهز للتحرك في الوقت المحدد مع حقائبكم" class="w-full p-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-xs focus:ring-2 focus:ring-teal-500 outline-none"></textarea>
                </div>

                <div class="pt-2 flex items-center gap-2">
                    <button type="button" @click="showOfferModal = false" class="flex-1 h-11 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs rounded-xl hover:bg-slate-200 transition">
                        إلغاء
                    </button>
                    <button type="submit" :disabled="submitting" class="flex-1 h-11 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow-sm transition active:scale-95 flex items-center justify-center gap-1.5">
                        <span x-show="!submitting">إرسال العرض للراكب</span>
                        <span x-show="submitting" class="animate-spin">⏳</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function driverOffersState() {
    return {
        requests: [],
        activeTab: 'all',
        acceptedJobsCount: 0,
        loading: true,
        submitting: false,
        notification: null,
        outboxOffers: [],
        showOfferModal: false,
        activeRequest: null,
        pollInterval: null,
        offerForm: {
            price_ils: 70,
            notes: ''
        },

        get openOpportunitiesCount() {
            return this.requests.filter(r => r.status === 'open').length;
        },

        get filteredRequests() {
            if (this.activeTab === 'accepted') {
                return this.requests.filter(r => r.is_accepted);
            }
            if (this.activeTab === 'open') {
                return this.requests.filter(r => r.status === 'open');
            }
            return this.requests;
        },

        init() {
            this.loadOutbox();

            // Load cached requests
            const cached = localStorage.getItem('meshwar_driver_requests_cache');
            if (cached) {
                try {
                    this.requests = JSON.parse(cached);
                    this.recalcAcceptedCount();
                    this.loading = false;
                } catch(e) {}
            }

            this.fetchRequests();

            // Poll every 5 seconds to catch accepted offers and notifications in real time
            this.pollInterval = setInterval(() => {
                if (navigator.onLine && !this.submitting && !this.showOfferModal) {
                    this.fetchRequests();
                }
            }, 5000);

            window.addEventListener('online', () => {
                this.syncOutbox();
                this.fetchRequests();
            });
        },

        recalcAcceptedCount() {
            this.acceptedJobsCount = this.requests.filter(r => r.is_accepted && r.status === 'accepted').length;
        },

        loadOutbox() {
            const raw = localStorage.getItem('meshwar_driver_offers_outbox');
            this.outboxOffers = raw ? JSON.parse(raw) : [];
        },

        saveOutbox() {
            localStorage.setItem('meshwar_driver_offers_outbox', JSON.stringify(this.outboxOffers));
        },

        async fetchRequests() {
            try {
                const res = await fetch('{{ route('api.requests.index') }}');
                if (res.ok) {
                    const data = await res.json();
                    const prevCount = this.acceptedJobsCount;
                    this.requests = data.requests;
                    this.acceptedJobsCount = data.accepted_jobs_count || this.requests.filter(r => r.is_accepted && r.status === 'accepted').length;

                    // If a new offer was accepted while driver was browsing
                    if (this.acceptedJobsCount > prevCount && prevCount >= 0) {
                        this.notification = '🎉 تنبيه جديد: وافق الراكب على عرض السعر المقدم من طرفك!';
                    }

                    localStorage.setItem('meshwar_driver_requests_cache', JSON.stringify(data.requests));
                }
            } catch (err) {
                console.warn('Driver viewing cached opportunities', err);
            } finally {
                this.loading = false;
            }
        },

        openOfferModal(request) {
            this.activeRequest = request;
            this.offerForm.price_ils = request.my_offer ? request.my_offer.price_ils : 70;
            this.offerForm.notes = request.my_offer ? (request.my_offer.notes || '') : '';
            this.showOfferModal = true;
        },

        async submitOffer() {
            if (!this.activeRequest) return;
            this.submitting = true;

            const mutationId = 'mut_off_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7);
            const payload = {
                price_ils: this.offerForm.price_ils,
                notes: this.offerForm.notes,
                client_mutation_id: mutationId,
            };

            const requestId = this.activeRequest.id;

            // If offline, store in outbox immediately
            if (!navigator.onLine) {
                this.outboxOffers.push({
                    requestId: requestId,
                    payload: payload,
                    createdAt: new Date().toISOString()
                });
                this.saveOutbox();

                // Optimistically update UI
                const req = this.requests.find(r => r.id === requestId);
                if (req) {
                    req.my_offer = {
                        id: 0,
                        price_ils: payload.price_ils,
                        notes: payload.notes,
                        status: 'pending',
                        status_arabic: 'في انتظار الإرسال (محلي)',
                    };
                }

                this.showOfferModal = false;
                this.submitting = false;
                this.notification = 'تم حفظ عرض السعر محلياً. سيتم إرساله للراكب فور الاتصال بالإنترنت.';
                return;
            }

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch(`/api/requests/${requestId}/offers`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok && data.ok) {
                    this.showOfferModal = false;
                    this.notification = '🎉 تم إرسال عرض السعر للراكب بنجاح!';
                    this.fetchRequests();
                } else {
                    alert(data.error || 'فشل إرسال عرض السعر');
                }
            } catch (err) {
                this.outboxOffers.push({
                    requestId: requestId,
                    payload: payload,
                    createdAt: new Date().toISOString()
                });
                this.saveOutbox();
                this.showOfferModal = false;
                this.notification = 'تم حفظ العرض في صندوق الانتظار وسيتم إرساله عند استقرار الاتصال.';
            } finally {
                this.submitting = false;
            }
        },

        async completeTrip(requestId) {
            if (!confirm('هل أنت متأكد من إنهاء هذا المشوار الخاص وتأكيد وصول الركاب؟')) return;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch(`/api/requests/${requestId}/complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                    }
                });

                const data = await res.json();
                if (res.ok && data.ok) {
                    this.notification = '🏁 تم إنهاء المشوار وتأكيد الوصول بنجاح! يمكنك مراجعة الأرباح في سجل الرحلات.';
                    this.fetchRequests();
                } else {
                    alert(data.error || 'فشل إنهاء المشوار');
                }
            } catch (err) {
                alert('حدث خطأ في الاتصال');
            }
        },

        async syncOutbox() {
            if (this.outboxOffers.length === 0) return;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const remaining = [];
            for (const item of this.outboxOffers) {
                try {
                    const res = await fetch(`/api/requests/${item.requestId}/offers`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken || '',
                        },
                        body: JSON.stringify(item.payload)
                    });
                    if (!res.ok) remaining.push(item);
                } catch(e) {
                    remaining.push(item);
                }
            }

            this.outboxOffers = remaining;
            this.saveOutbox();
            if (remaining.length === 0) {
                this.notification = '✓ تم إرسال جميع عروض الأسعار المعلقة بنجاح!';
                this.fetchRequests();
            }
        }
    };
}
</script>
@endsection
