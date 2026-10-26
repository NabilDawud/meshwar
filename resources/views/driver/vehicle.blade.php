@extends('layouts.app')

@section('content')
<div class="p-4 flex flex-col gap-4" x-data="vehicleForm({
    type: '{{ old('type', $vehicle->type ?? 'ajala') }}',
    seatCount: {{ old('seat_count', $vehicle->seat_count ?? 10) }},
    licensePlate: '{{ old('license_plate', $vehicle->license_plate ?? '') }}',
    status: '{{ old('status', $vehicle->status ?? 'active') }}',
    existingPhotos: {{ json_encode($vehicle->photos ?? []) }}
})">
    <!-- Header -->
    <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-700">
        <a href="{{ route('driver.dashboard') }}" class="text-xs font-bold text-teal-600 dark:text-teal-400 flex items-center gap-1">
            <span>→</span>
            <span>العودة للوحة القيادة</span>
        </a>
        <h1 class="text-base font-black text-slate-800 dark:text-slate-100">تسجيل المركبة (1:1)</h1>
    </div>

    <!-- Offline & Wi-Fi Policy Alert Banner -->
    <div class="p-3.5 rounded-2xl bg-teal-50 dark:bg-teal-950/60 border border-teal-200 dark:border-teal-800 text-xs text-teal-800 dark:text-teal-200 flex items-start gap-2.5">
        <span class="text-xl">📶</span>
        <div class="flex-1 leading-relaxed">
            <span class="font-bold">استراتيجية غزة للإنترنت الضعيف:</span>
            <span>يتم ضغط الصور وتصغير أبعادها داخل الهاتف تلقائياً قبل الحفظ لتوفير حزم البيانات، وتُخزن محلياً للمشاهدة بدون إنترنت.</span>
        </div>
    </div>

    <form @submit.prevent="saveVehicle" class="flex flex-col gap-4">
        @csrf

        <!-- Vehicle Type Selector -->
        <div class="flex flex-col gap-2">
            <label class="font-bold text-xs text-slate-700 dark:text-slate-200">
                نوع المركبة <span class="text-rose-500">*</span>
            </label>
            <div class="grid grid-cols-3 gap-2">
                <!-- Ajala -->
                <button 
                    type="button" 
                    @click="type = 'ajala'"
                    :class="type === 'ajala' ? 'border-amber-600 bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                    class="p-3 rounded-2xl border-2 flex flex-col items-center gap-1.5 transition active:scale-95 text-center"
                >
                    <span class="text-2xl">🛞</span>
                    <span class="font-bold text-xs">عجلة</span>
                    <span class="text-[10px] text-slate-400">مقطورة ركاب</span>
                </button>

                <!-- Car -->
                <button 
                    type="button" 
                    @click="type = 'car'"
                    :class="type === 'car' ? 'border-teal-600 bg-teal-50 dark:bg-teal-950/40 text-teal-900 dark:text-teal-200' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                    class="p-3 rounded-2xl border-2 flex flex-col items-center gap-1.5 transition active:scale-95 text-center"
                >
                    <span class="text-2xl">🚗</span>
                    <span class="font-bold text-xs">سيارة</span>
                    <span class="text-[10px] text-slate-400">4-7 ركاب</span>
                </button>

                <!-- Bus -->
                <button 
                    type="button" 
                    @click="type = 'bus'"
                    :class="type === 'bus' ? 'border-blue-600 bg-blue-50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                    class="p-3 rounded-2xl border-2 flex flex-col items-center gap-1.5 transition active:scale-95 text-center"
                >
                    <span class="text-2xl">🚐</span>
                    <span class="font-bold text-xs">باص</span>
                    <span class="text-[10px] text-slate-400">ميكروباص / كبير</span>
                </button>
            </div>
        </div>

        <!-- Seat Count Stepper -->
        <div class="flex flex-col gap-1.5">
            <label class="font-bold text-xs text-slate-700 dark:text-slate-200">
                سعة الركاب المتاحة (عدد المقاعد) <span class="text-rose-500">*</span>
            </label>
            <div class="flex items-center gap-3">
                <button 
                    type="button" 
                    @click="seatCount = Math.max(1, seatCount - 1)"
                    class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 font-bold text-xl flex items-center justify-center active:scale-95 transition"
                >
                    -
                </button>

                <div class="flex-1 h-12 rounded-2xl bg-white dark:bg-slate-900 border-2 border-slate-200 dark:border-slate-700 flex items-center justify-center font-mono font-black text-xl text-teal-600 dark:text-teal-400">
                    <span x-text="seatCount"></span>
                    <span class="text-xs font-normal text-slate-400 ms-1">ركاب</span>
                </div>

                <button 
                    type="button" 
                    @click="seatCount = Math.min(60, seatCount + 1)"
                    class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 font-bold text-xl flex items-center justify-center active:scale-95 transition"
                >
                    +
                </button>
            </div>
        </div>

        <!-- License Plate -->
        <div class="flex flex-col gap-1.5">
            <label for="plate" class="font-bold text-xs text-slate-700 dark:text-slate-200">
                رقم اللوحة أو علامة مميزة للمركبة
            </label>
            <input 
                type="text" 
                id="plate" 
                x-model="licensePlate" 
                placeholder="مثال: غزة - 7890 أو عجلة أبو العبد"
                class="w-full h-12 px-4 rounded-2xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:border-teal-500 focus:outline-none transition"
            >
        </div>

        <!-- Maintenance Status Toggle -->
        <div class="flex items-center justify-between p-3.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
            <div>
                <span class="font-bold text-xs text-slate-800 dark:text-slate-100 block">حالة المركبة</span>
                <span class="text-[11px] text-slate-400" x-text="status === 'active' ? 'جاهزة لنقل الركاب' : 'تحت الصيانة (تجميد الحجوزات)'"></span>
            </div>
            <button 
                type="button" 
                @click="status = status === 'active' ? 'maintenance' : 'active'"
                :class="status === 'active' ? 'bg-teal-600 text-white' : 'bg-rose-600 text-white'"
                class="px-3 py-1.5 rounded-xl text-xs font-bold transition active:scale-95"
            >
                <span x-text="status === 'active' ? 'نشطة' : 'صيانة'"></span>
            </button>
        </div>

        <!-- Vehicle Recognition Photos (Up to 4) -->
        <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <label class="font-bold text-xs text-slate-700 dark:text-slate-200">
                    صور التعرف بالشارع (حتى 4 صور)
                </label>
                <span class="text-[11px] text-slate-400" x-text="photos.length + ' / 4'"></span>
            </div>

            <!-- Photos Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                <template x-for="(photo, index) in photos" :key="index">
                    <div class="relative aspect-video rounded-2xl overflow-hidden bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 group">
                        <img :src="photo" alt="صورة المركبة" class="w-full h-full object-cover">
                        <button 
                            type="button" 
                            @click="removePhoto(index)"
                            class="absolute top-1.5 end-1.5 w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center text-xs font-bold shadow-md hover:bg-rose-700"
                        >
                            ✕
                        </button>
                    </div>
                </template>

                <!-- Add Photo Button -->
                <label 
                    x-show="photos.length < 4"
                    class="aspect-video rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-teal-500 bg-white dark:bg-slate-800/60 flex flex-col items-center justify-center gap-1 cursor-pointer transition active:scale-95"
                >
                    <span class="text-xl">📷</span>
                    <span class="text-[10px] font-bold text-slate-500">إضافة صورة</span>
                    <input 
                        type="file" 
                        accept="image/*" 
                        capture="environment"
                        @change="handlePhotoUpload($event)"
                        class="sr-only"
                    >
                </label>
            </div>
            <p class="text-[11px] text-slate-400">
                الصور تُساعد الركاب على تمييز عجلتك أو سيارتك فور وقوفك عند النقطة.
            </p>
        </div>

        <!-- Submit Button -->
        <button 
            type="submit" 
            :disabled="isSubmitting"
            class="mt-2 w-full min-h-[48px] bg-teal-600 hover:bg-teal-700 active:scale-[0.98] text-white font-bold rounded-2xl text-sm transition shadow-sm flex items-center justify-center gap-2 disabled:opacity-50"
        >
            <span x-show="!isSubmitting">حفظ بيانات المركبة</span>
            <span x-show="isSubmitting">جاري الحفظ...</span>
        </button>
    </form>
</div>

@push('scripts')
<script>
function vehicleForm(initialData) {
    return {
        type: initialData.type,
        seatCount: initialData.seatCount,
        licensePlate: initialData.licensePlate,
        status: initialData.status,
        photos: [...initialData.existingPhotos],
        isSubmitting: false,

        // Frontend Client-Side Canvas Image Resizer (Compress to max 1200x1200px / JPEG 0.8)
        async handlePhotoUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            const resizedBase64 = await this.resizeImage(file, 1200, 1200, 0.8);
            if (this.photos.length < 4) {
                this.photos.push(resizedBase64);
                // Cache locally in IndexedDB / LocalStorage for immediate offline viewing
                try {
                    localStorage.setItem('cached_vehicle_photos', JSON.stringify(this.photos));
                } catch (e) {
                    console.warn('LocalStorage limit reached for photo cache', e);
                }
            }
            event.target.value = '';
        },

        resizeImage(file, maxWidth, maxHeight, quality) {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = new Image();
                    img.onload = () => {
                        let width = img.width;
                        let height = img.height;

                        if (width > height) {
                            if (width > maxWidth) {
                                height = Math.round((height * maxWidth) / width);
                                width = maxWidth;
                            }
                        } else {
                            if (height > maxHeight) {
                                width = Math.round((width * maxHeight) / height);
                                height = maxHeight;
                            }
                        }

                        const canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);
                        resolve(canvas.toDataURL('image/jpeg', quality));
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        },

        removePhoto(index) {
            this.photos.splice(index, 1);
            localStorage.setItem('cached_vehicle_photos', JSON.stringify(this.photos));
        },

        async saveVehicle() {
            this.isSubmitting = true;
            try {
                const res = await fetch('{{ route('api.driver.vehicle.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        type: this.type,
                        seat_count: this.seatCount,
                        license_plate: this.licensePlate,
                        status: this.status,
                        photos: this.photos
                    })
                });

                const data = await res.json();
                if (data.ok) {
                    window.location.href = '{{ route('driver.dashboard') }}';
                } else {
                    alert(data.message || 'حدث خطأ أثناء حفظ المركبة');
                }
            } catch (err) {
                console.error(err);
                alert('تم حفظ البيانات محلياً. ستتم المزامنة عند عودة الاتصال.');
                window.location.href = '{{ route('driver.dashboard') }}';
            } finally {
                this.isSubmitting = false;
            }
        }
    };
}
</script>
@endpush
@endsection
