@extends('layouts.app')

@section('title', 'المحادثة المباشرة - مشوار')

@section('content')
<div class="p-3 sm:p-4 flex flex-col h-[calc(100dvh-7.5rem)] max-w-lg mx-auto w-full" x-data="chatThreadState({{ $offer_id }})" x-init="init()">
    <!-- Top Header Card -->
    <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between gap-2 shrink-0">
        <div class="flex items-center gap-2.5">
            <button @click="history.back()" class="p-1.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 transition text-xs font-bold">
                ➔
            </button>
            <div>
                <div class="flex items-center gap-1.5">
                    <span class="font-bold text-sm text-slate-800 dark:text-slate-100" x-text="chatData ? chatData.other_party.name : 'جاري التحميل...'"></span>
                    <template x-if="chatData">
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold"
                            :class="{
                                'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300': chatData.status === 'pending',
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': chatData.status === 'accepted',
                                'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300': chatData.status === 'rejected'
                            }"
                            x-text="chatData.status_arabic">
                        </span>
                    </template>
                </div>
                <template x-if="chatData">
                    <div class="flex items-center gap-2 text-[11px] text-slate-400">
                        <span x-text="chatData.request.origin + ' ➔ ' + chatData.request.destination"></span>
                        <span>•</span>
                        <span class="font-mono font-bold text-teal-600 dark:text-teal-400" x-text="chatData.price_ils + ' ₪'"></span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Tap-to-Call (Privacy protected: active only if permitted) -->
        <template x-if="chatData && chatData.other_party.can_call">
            <a :href="'tel:' + chatData.other_party.phone" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm transition active:scale-95 flex items-center gap-1">
                <span>📞</span>
                <span>اتصال</span>
            </a>
        </template>
    </div>

    <!-- Offline Alert -->
    <div x-show="isOffline" x-cloak class="mt-2 p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 text-xs font-bold flex items-center justify-between shrink-0">
        <span class="flex items-center gap-1">
            <span>⏳</span>
            <span>أنت غير متصل بالإنترنت. الرسائل ستُحفظ وتُرسل فور عودة الاتصال.</span>
        </span>
    </div>

    <!-- Messages Container -->
    <div class="flex-1 overflow-y-auto my-3 flex flex-col gap-2.5 p-1" id="chat-messages-container">
        <!-- Empty / Loading State -->
        <template x-if="loading && messages.length === 0">
            <div class="p-8 text-center text-slate-400 text-xs my-auto">
                <span class="text-2xl block mb-2 animate-bounce">💬</span>
                جاري تحميل المحادثة...
            </div>
        </template>

        <template x-if="!loading && messages.length === 0">
            <div class="p-8 text-center bg-white/60 dark:bg-slate-800/60 rounded-3xl border border-slate-200 dark:border-slate-700 text-slate-400 text-xs my-auto flex flex-col items-center gap-2">
                <span class="text-3xl">👋</span>
                <p class="font-bold text-slate-700 dark:text-slate-300">لا توجد رسائل سابقة في هذا العرض</p>
                <p class="text-[11px] max-w-xs">يمكنك إرسال استفسار حول نقطة التجمع الدقيقة، وقت الانطلاق، أو الأمتعة.</p>
            </div>
        </template>

        <!-- Message Bubbles -->
        <template x-for="msg in messages" :key="msg.id || msg.client_mutation_id">
            <div class="flex flex-col" :class="msg.is_me ? 'items-start' : 'items-end'">
                <div class="max-w-[85%] rounded-2xl px-3.5 py-2.5 text-xs shadow-sm leading-relaxed"
                    :class="msg.is_me ? 
                        'bg-teal-600 text-white rounded-br-none' : 
                        'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 rounded-bl-none'">
                    <p class="break-words" x-text="msg.message"></p>
                </div>
                <div class="flex items-center gap-1.5 mt-1 px-1 text-[10px] text-slate-400">
                    <span x-text="msg.time_arabic || 'الآن'"></span>
                    <template x-if="msg.pending">
                        <span class="text-amber-500 font-bold">⏳ في انتظار الإرسال</span>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <!-- Message Input Bar -->
    <form @submit.prevent="sendMessage" class="p-2 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-lg flex items-center gap-2 shrink-0">
        <input 
            type="text" 
            x-model="newMessage" 
            placeholder="اكتب رسالتك هنا..." 
            class="flex-1 h-10 px-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-100 outline-none focus:ring-2 focus:ring-teal-500"
            maxlength="1000"
        >
        <button 
            type="submit" 
            :disabled="!newMessage.trim() || sending" 
            class="h-10 px-4 bg-teal-600 hover:bg-teal-700 disabled:opacity-40 text-white font-bold text-xs rounded-xl shadow-sm transition active:scale-95 flex items-center justify-center gap-1"
        >
            <span x-show="!sending">إرسال</span>
            <span x-show="sending" class="animate-spin">⏳</span>
        </button>
    </form>
</div>

<script>
function chatThreadState(offerId) {
    return {
        offerId: offerId,
        chatData: null,
        messages: [],
        newMessage: '',
        loading: true,
        sending: false,
        isOffline: !navigator.onLine,
        pollInterval: null,

        init() {
            // Load cached messages first
            const cached = localStorage.getItem('meshwar_chat_cache_' + this.offerId);
            if (cached) {
                try {
                    const data = JSON.parse(cached);
                    this.chatData = data;
                    this.messages = data.messages || [];
                    this.loading = false;
                } catch(e) {}
            }

            this.loadOutboxMessages();
            this.fetchMessages();

            // Set up polling every 4 seconds
            this.pollInterval = setInterval(() => {
                if (navigator.onLine && !this.sending) {
                    this.fetchMessages();
                }
            }, 4000);

            window.addEventListener('online', () => {
                this.isOffline = false;
                this.syncOutbox();
                this.fetchMessages();
            });

            window.addEventListener('offline', () => {
                this.isOffline = true;
            });
        },

        loadOutboxMessages() {
            const raw = localStorage.getItem('meshwar_chat_outbox_' + this.offerId);
            if (raw) {
                try {
                    const outbox = JSON.parse(raw);
                    // Append pending outbox items to message list if not already present
                    for (const item of outbox) {
                        if (!this.messages.some(m => m.client_mutation_id === item.client_mutation_id)) {
                            this.messages.push(item);
                        }
                    }
                } catch(e) {}
            }
        },

        async fetchMessages() {
            try {
                const res = await fetch(`/api/chat/${this.offerId}`);
                if (res.ok) {
                    const data = await res.json();
                    this.chatData = data;

                    // Merge server messages with pending outbox
                    const serverMessages = data.messages || [];
                    const rawOutbox = localStorage.getItem('meshwar_chat_outbox_' + this.offerId);
                    const outbox = rawOutbox ? JSON.parse(rawOutbox) : [];

                    // Filter outbox items already acknowledged on server
                    const pendingOutbox = outbox.filter(o => 
                        !serverMessages.some(s => s.message === o.message && s.is_me)
                    );
                    localStorage.setItem('meshwar_chat_outbox_' + this.offerId, JSON.stringify(pendingOutbox));

                    this.messages = [...serverMessages, ...pendingOutbox];
                    localStorage.setItem('meshwar_chat_cache_' + this.offerId, JSON.stringify(data));
                    this.scrollToBottom();
                }
            } catch (err) {
                console.warn('Chat running offline mode', err);
            } finally {
                this.loading = false;
            }
        },

        async sendMessage() {
            const text = this.newMessage.trim();
            if (!text) return;

            this.newMessage = '';
            const mutationId = 'mut_msg_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7);

            const pendingMsg = {
                client_mutation_id: mutationId,
                message: text,
                is_me: true,
                pending: true,
                time_arabic: new Date().toLocaleTimeString('ar-EG', {hour: '2-digit', minute:'2-digit'}),
            };

            this.messages.push(pendingMsg);
            this.scrollToBottom();

            // Store in outbox
            const raw = localStorage.getItem('meshwar_chat_outbox_' + this.offerId);
            const outbox = raw ? JSON.parse(raw) : [];
            outbox.push(pendingMsg);
            localStorage.setItem('meshwar_chat_outbox_' + this.offerId, JSON.stringify(outbox));

            if (!navigator.onLine) {
                return;
            }

            this.sending = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch(`/api/chat/${this.offerId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                    },
                    body: JSON.stringify({
                        message: text,
                        client_mutation_id: mutationId
                    })
                });

                if (res.ok) {
                    // Remove from outbox
                    const curOutbox = JSON.parse(localStorage.getItem('meshwar_chat_outbox_' + this.offerId) || '[]');
                    const filtered = curOutbox.filter(o => o.client_mutation_id !== mutationId);
                    localStorage.setItem('meshwar_chat_outbox_' + this.offerId, JSON.stringify(filtered));

                    pendingMsg.pending = false;
                    this.fetchMessages();
                }
            } catch(e) {
                console.warn('Message queued in outbox for retry', e);
            } finally {
                this.sending = false;
            }
        },

        async syncOutbox() {
            const raw = localStorage.getItem('meshwar_chat_outbox_' + this.offerId);
            if (!raw) return;
            const outbox = JSON.parse(raw);
            if (outbox.length === 0) return;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const remaining = [];

            for (const item of outbox) {
                try {
                    const res = await fetch(`/api/chat/${this.offerId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken || '',
                        },
                        body: JSON.stringify({
                            message: item.message,
                            client_mutation_id: item.client_mutation_id
                        })
                    });
                    if (!res.ok) remaining.push(item);
                } catch(e) {
                    remaining.push(item);
                }
            }

            localStorage.setItem('meshwar_chat_outbox_' + this.offerId, JSON.stringify(remaining));
            this.fetchMessages();
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const container = document.getElementById('chat-messages-container');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        }
    };
}
</script>
@endsection
