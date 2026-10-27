@extends('layouts.app')

@section('title', 'طلبات المشاوير الخاصة (مراسلة) - مشوار')

@section('content')
<div class="p-4 flex flex-col gap-4 max-w-lg mx-auto" x-data="specialRequestsState()" x-init="init()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-black text-slate-800 dark:text-slate-100">المشاوير الخاصة (مراسلة)</h1>
            <p class="text-xs text-slate-500">انشر طلب مشوار خاص وقارن عروض أسعار السائقين</p>
        </div>
        <button @click="showCreateModal = true" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-95 flex items-center gap-1">
            <span>+</span>
            <span>طلب جديد</span>
        </button>
    </div>

    <!-- Offline Waiting to Send Banner -->
    <div x-show="outboxRequest" x-cloak class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 flex flex-col gap-2">
        <div class="flex items-center gap-2 text-amber-800 dark:text-amber-200 font-bold text-sm">
            <span class="animate-pulse">⏳</span>
            <span>الطلب في انتظار النشر (غير متصل بالإنترنت)</span>
        </div>
        <p class="text-xs text-amber-700 dark:text-amber-300 leading-relaxed">
            تم حفظ تفاصيل المشوار الخاص محلياً على هاتفك. سيتم نشر الطلب للسائقين فور استعادة الاتصال بالإنترنت.
        </p>
    </div>

    <!-- Sync Toast Alert -->
    <div x-show="syncNotification" x-cloak class="p-4 rounded-2xl bg-teal-50 dark:bg-teal-950/40 border border-teal-300 dark:border-teal-700 text-teal-800 dark:text-teal-200 text-xs font-bold flex items-center justify-between">
        <span x-text="syncNotification"></span>
        <button @click="syncNotification = null" class="px-2">✕</button>
    </div>

    <!-- Loading State -->
    <div x-show="loading && requests.length === 0" class="p-8 text-center text-slate-400 text-xs">
        <span class="text-2xl block mb-2 animate-bounce">⏳</span>
        جاري تحميل الطلبات...
    </div>

    <!-- Empty State -->
    <div x-show="!loading && requests.length === 0 && !outboxRequest" class="p-8 text-center bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 flex flex-col items-center gap-3">
        <span class="text-4xl">🚕</span>
        <h3 class="font-bold text-sm text-slate-700 dark:text-slate-200">لا توجد طلبات مشاوير خاصة منشورة</h3>
        <p class="text-xs text-slate-400 max-w-xs">إذا كنت بحاجة لمركبة خاصة أو مشوار لمجموعة في وقت محدد، انشر طلبك وسيقدم السائقون عروض أسعارهم</p>
        <button @click="showCreateModal = true" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow-sm transition">
            انشر طلب مشوار خاص الآن
        </button>
    </div>

    <!-- Requests List -->
    <div class="flex flex-col gap-4">
        <template x-for="r in requests" :key="r.id">
            <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col gap-3 shadow-sm">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-[11px] text-slate-400 font-mono">طلب #<span x-text="r.id"></span></span>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="font-black text-sm text-slate-800 dark:text-slate-100" x-text="r.origin_name"></span>
                            <span class="text-xs text-slate-400">➔</span>
                            <span class="font-black text-sm text-slate-800 dark:text-slate-100" x-text="r.destination_name"></span>
                        </div>
                    </div>
                    <span class="text-xs px-2.5 py-1 rounded-full font-bold"
                        :class="{
                            'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300': r.status === 'open',
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': r.status === 'accepted',
                            'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300': r.status === 'cancelled'
                        }"
                        x-text="r.status_arabic">
                    </span>
                </div>

                <!-- Schedule & Seats Details -->
                <div class="grid grid-cols-2 gap-2 text-xs p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700">
                    <div>
                        <span class="text-slate-400 block text-[11px]">تاريخ المشوار:</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300 font-mono" x-text="r.departure_date"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">الوقت المفضل:</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300" x-text="r.departure_window"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">المقاعد المطلوبة:</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300" x-text="r.seats_needed + ' ركاب'"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">عروض السائقين:</span>
                        <span class="font-bold text-teal-600 dark:text-teal-400" x-text="r.offers_count + ' عروض متوفرة'"></span>
                    </div>
                </div>

                <template x-if="r.notes">
                    <p class="text-xs text-slate-500 italic bg-slate-50 dark:bg-slate-900/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800" x-text="'ملاحظة: ' + r.notes"></p>
                </template>

                <!-- Action Button: Compare Offers -->
                <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                    <span class="text-xs text-slate-400 font-mono" x-text="r.created_at ? new Date(r.created_at).toLocaleDateString('ar-EG') : ''"></span>
                    <a :href="'/requests/' + r.id" class="px-4 py-2 bg-teal-50 dark:bg-teal-950/60 text-teal-700 dark:text-teal-300 hover:bg-teal-100 border border-teal-200 dark:border-teal-800 text-xs font-bold rounded-xl flex items-center gap-1.5 transition active:scale-95 shadow-sm">
                        <span>🔍 مقارنة عروض السائقين (<span x-text="r.offers_count"></span>)</span>
                        <span>➔</span>
                    </a>
                </div>
            </div>
        </template>
    </div>

    <!-- Create Special Request Modal (Bottom Slide-Up Sheet) -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4 transition-opacity">
        <div @click.away="showCreateModal = false" class="w-full max-w-lg bg-white dark:bg-slate-800 rounded-t-3xl sm:rounded-3xl p-6 border border-slate-200 dark:border-slate-700 max-h-[90dvh] overflow-y-auto flex flex-col gap-4 shadow-2xl">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700">
                <div class="flex items-center gap-2">
                    <span class="text-2xl">🚕</span>
                    <h2 class="text-base font-black text-slate-800 dark:text-slate-100">طلب مشوار خاص (مراسلة)</h2>
                </div>
                <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 font-bold p-1">✕</button>
            </div>

            <form @submit.prevent="submitRequest" class="flex flex-col gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">نقطة الانطلاق (من)</label>
                    <input type="text" x-model="form.origin_name" required placeholder="مثال: دوار العودة - وسط رفح" class="w-full h-11 px-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">الوجهة (إلى)</label>
                    <input type="text" x-model="form.destination_name" required placeholder="مثال: ميدان فلسطين - غزة" class="w-full h-11 px-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">تاريخ المشوار</label>
                        <input type="date" x-model="form.departure_date" required class="w-full h-11 px-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">الوقت المفضل</label>
                        <input type="text" x-model="form.departure_window" required placeholder="مثال: 08:00 - 09:00 صباحاً" class="w-full h-11 px-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">عدد المقاعد المطلوبة</label>
                    <input type="number" x-model="form.seats_needed" min="1" max="20" required class="w-full h-11 px-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات إضافية للسائق (اختياري)</label>
                    <textarea x-model="form.notes" rows="2" placeholder="مثال: حقائب سفر، يفضل سيارة مكيفة، إلخ." class="w-full p-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-teal-500 outline-none"></textarea>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <button type="submit" :disabled="submitting" class="flex-1 h-12 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold text-sm rounded-xl shadow-sm transition active:scale-95">
                        <span x-show="!submitting">نشر طلب المشوار ➔</span>
                        <span x-show="submitting">جاري الحفظ...</span>
                    </button>
                    <button type="button" @click="showCreateModal = false" class="px-5 h-12 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-sm rounded-xl transition">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function specialRequestsState() {
    return {
        requests: [],
        loading: false,
        showCreateModal: false,
        submitting: false,
        outboxRequest: null,
        syncNotification: null,
        form: {
            origin_name: '',
            destination_name: '',
            departure_date: new Date().toISOString().split('T')[0],
            departure_window: '08:00 - 09:00 صباحاً',
            seats_needed: 1,
            notes: ''
        },

        init() {
            this.checkOutbox();
            this.fetchRequests();

            window.addEventListener('online', () => {
                this.syncOutbox();
            });
        },

        checkOutbox() {
            try {
                const stored = localStorage.getItem('mishwar_request_outbox');
                if (stored) {
                    this.outboxRequest = JSON.parse(stored);
                    if (navigator.onLine) {
                        this.syncOutbox();
                    }
                }
            } catch (e) {}
        },

        async syncOutbox() {
            if (!this.outboxRequest || !navigator.onLine) return;

            try {
                const res = await fetch('{{ route('api.requests.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.outboxRequest)
                });

                const data = await res.json();
                if (res.ok && data.ok) {
                    localStorage.removeItem('mishwar_request_outbox');
                    this.outboxRequest = null;
                    this.syncNotification = 'تم نشر طلبك بنجاح بعد عودة الاتصال بالإنترنت';
                    this.fetchRequests();
                }
            } catch (err) {
                console.warn('Sync request outbox failed, will retry next time online', err);
            }
        },

        async fetchRequests() {
            this.loading = true;
            try {
                const res = await fetch('{{ route('api.requests.index') }}');
                const data = await res.json();
                if (data.ok) {
                    this.requests = data.requests;
                }
            } catch (err) {
                console.warn('Offline: viewing cached requests', err);
            } finally {
                this.loading = false;
            }
        },

        async submitRequest() {
            this.submitting = true;
            const mutationId = 'req_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            const payload = {
                ...this.form,
                client_mutation_id: mutationId
            };

            if (!navigator.onLine) {
                try {
                    localStorage.setItem('mishwar_request_outbox', JSON.stringify(payload));
                    this.outboxRequest = payload;
                    this.showCreateModal = false;
                    this.submitting = false;
                } catch (e) {}
                return;
            }

            try {
                const res = await fetch('{{ route('api.requests.store') }}', {
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
                    this.showCreateModal = false;
                    this.fetchRequests();
                } else {
                    alert(data.error || 'عذراً، حدث خطأ أثناء نشر الطلب');
                }
            } catch (err) {
                try {
                    localStorage.setItem('mishwar_request_outbox', JSON.stringify(payload));
                    this.outboxRequest = payload;
                    this.showCreateModal = false;
                } catch (e) {}
            } finally {
                this.submitting = false;
            }
        }
    };
}
</script>
@endpush
@endsection
