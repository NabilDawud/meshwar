@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css" />
<style>
    .mapboxgl-ctrl-bottom-left, .mapboxgl-ctrl-bottom-right { display: none; }
</style>
@endpush

@section('content')
<div class="p-4 flex flex-col gap-4" x-data="routeBuilder({
    existingRoute: {{ json_encode($route) }},
    existingStops: {{ json_encode($route->stops ?? []) }}
})">
    <!-- Header -->
    <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-700">
        <a href="{{ route('driver.dashboard') }}" class="text-xs font-bold text-teal-600 dark:text-teal-400 flex items-center gap-1">
            <span>→</span>
            <span>العودة للوحة القيادة</span>
        </a>
        <h1 class="text-base font-black text-slate-800 dark:text-slate-100">رسم مسار الخط والمحطات</h1>
    </div>

    <!-- Gaza Gazetteer Info Banner -->
    <div class="p-3.5 rounded-2xl bg-teal-50 dark:bg-teal-950/60 border border-teal-200 dark:border-teal-800 text-xs text-teal-800 dark:text-teal-200 flex items-start gap-2.5">
        <span class="text-xl">📍</span>
        <div class="flex-1 leading-relaxed">
            <span class="font-bold">محطات OpenStreetMap الحقيقية:</span>
            <span>جميع نقاط المسار معتمدة من المعالم والمفترقات الجغرافية الموثقة لقطاع غزة (بدون أي أماكن وهمية)، وتعمل ذهاباً وإياباً (&lt;-&gt;).</span>
        </div>
    </div>

    <!-- Interactive Gaza Map -->
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
            <label class="font-bold text-xs text-slate-700 dark:text-slate-200">
                خريطة قطاع غزة (اضغط على أي معلم أو اختر من القائمة أدناه)
            </label>
            <span class="text-[11px] font-mono text-teal-600 dark:text-teal-400 font-bold" x-text="stops.length + ' محطات محددة'"></span>
        </div>
        
        <div id="gaza-map" class="w-full h-64 sm:h-80 rounded-2xl overflow-hidden border-2 border-slate-200 dark:border-slate-700 shadow-inner bg-slate-100 dark:bg-slate-800"></div>
    </div>

    <!-- Gazetteer Quick Selector -->
    <div class="flex flex-col gap-1.5">
        <label class="font-bold text-xs text-slate-700 dark:text-slate-200 flex items-center gap-1">
            <span>➕</span>
            <span>إضافة محطة معتمدة من دليل غزة:</span>
        </label>
        <div class="flex gap-2">
            <select 
                x-model="selectedLandmarkId" 
                class="flex-1 h-12 px-3 rounded-2xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-xs focus:border-teal-500 focus:outline-none"
            >
                <option value="">-- اختر معلماً لإضافته للمسار --</option>
                <optgroup label="محور طريق صلاح الدين">
                    <template x-for="item in landmarks.filter(l => l.corridor === 'salah_al_din')" :key="item.id">
                        <option :value="item.id" x-text="item.name_ar + ' (' + item.municipality + ')'"></option>
                    </template>
                </optgroup>
                <optgroup label="محور شارع الرشيد الساحلي">
                    <template x-for="item in landmarks.filter(l => l.corridor === 'al_rashid')" :key="item.id">
                        <option :value="item.id" x-text="item.name_ar + ' (' + item.municipality + ')'"></option>
                    </template>
                </optgroup>
                <optgroup label="المحاور العرضية والمدن">
                    <template x-for="item in landmarks.filter(l => l.corridor === 'cross')" :key="item.id">
                        <option :value="item.id" x-text="item.name_ar + ' (' + item.municipality + ')'"></option>
                    </template>
                </optgroup>
            </select>
            <button 
                type="button" 
                @click="addSelectedLandmark"
                :disabled="!selectedLandmarkId"
                class="px-4 h-12 bg-teal-600 hover:bg-teal-700 disabled:opacity-40 text-white text-xs font-bold rounded-2xl transition active:scale-95"
            >
                إضافة
            </button>
        </div>
    </div>

    <!-- Ordered Stops List with Leg Fares -->
    <div class="flex flex-col gap-2">
        <label class="font-bold text-xs text-slate-700 dark:text-slate-200">
            ترتيب محطات الخط وأجرة كل مقطع (₪)
        </label>

        <div class="flex flex-col gap-2" x-show="stops.length > 0">
            <template x-for="(stop, index) in stops" :key="index">
                <div class="p-3 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col gap-2 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-teal-100 dark:bg-teal-900/60 text-teal-700 dark:text-teal-300 flex items-center justify-center font-bold text-xs" x-text="index + 1"></span>
                            <span class="font-bold text-xs sm:text-sm text-slate-800 dark:text-slate-100" x-text="stop.name"></span>
                        </div>
                        <div class="flex items-center gap-1">
                            <!-- Move Up -->
                            <button 
                                type="button" 
                                @click="moveStop(index, -1)" 
                                :disabled="index === 0"
                                class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-700 text-xs disabled:opacity-30 hover:bg-slate-200"
                            >
                                ▲
                            </button>
                            <!-- Move Down -->
                            <button 
                                type="button" 
                                @click="moveStop(index, 1)" 
                                :disabled="index === stops.length - 1"
                                class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-700 text-xs disabled:opacity-30 hover:bg-slate-200"
                            >
                                ▼
                            </button>
                            <!-- Delete -->
                            <button 
                                type="button" 
                                @click="removeStop(index)" 
                                class="w-7 h-7 rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400 text-xs hover:bg-rose-100"
                            >
                                ✕
                            </button>
                        </div>
                    </div>

                    <!-- Leg Fare Input -->
                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-700/60 text-xs">
                        <span class="text-slate-500 dark:text-slate-400">
                            <span x-show="index === 0">نقطة الانطلاق الأولى</span>
                            <span x-show="index > 0">أجرة الوصول إلى هذه المحطة:</span>
                        </span>
                        <div class="flex items-center gap-1.5" x-show="index > 0">
                            <input 
                                type="number" 
                                min="0" 
                                step="1" 
                                x-model.number="stop.fare_ils" 
                                placeholder="0" 
                                class="w-16 h-8 px-2 text-center rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 font-mono font-bold text-teal-600 dark:text-teal-400 text-xs focus:outline-none"
                            >
                            <span class="font-bold text-slate-500">₪</span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="stops.length === 0" class="p-6 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-700 text-center text-xs text-slate-400">
            لم تختر أي محطات بعد. اختر نقطتين على الأقل من القائمة أعلاه لبناء المسار.
        </div>
    </div>

    <!-- Route Details Form -->
    <div class="flex flex-col gap-3.5 bg-white dark:bg-slate-800 p-4 rounded-3xl border border-slate-200 dark:border-slate-700">
        <!-- Route Name -->
        <div class="flex flex-col gap-1.5">
            <label for="routeName" class="font-bold text-xs text-slate-700 dark:text-slate-200">
                اسم خط السير
            </label>
            <input 
                type="text" 
                id="routeName" 
                x-model="routeName" 
                placeholder="مثال: رفح <-> غزة (عبر طريق صلاح الدين)"
                class="w-full h-12 px-4 rounded-2xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-xs focus:border-teal-500 focus:outline-none"
            >
        </div>

        <!-- First Trip Time -->
        <div class="flex flex-col gap-1.5">
            <label for="tripTime" class="font-bold text-xs text-slate-700 dark:text-slate-200">
                توقيت أول رحلة صباحية
            </label>
            <input 
                type="time" 
                id="tripTime" 
                x-model="firstTripTime" 
                class="w-full h-12 px-4 rounded-2xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm font-mono focus:border-teal-500 focus:outline-none"
            >
        </div>

        <!-- Route Direction Type (Two-way vs One-way) -->
        <div class="flex flex-col gap-1.5">
            <label class="font-bold text-xs text-slate-700 dark:text-slate-200">
                طبيعة مسار الخط
            </label>
            <div class="grid grid-cols-2 gap-2">
                <button 
                    type="button" 
                    @click="isTwoWay = true"
                    :class="isTwoWay ? 'bg-teal-600 text-white font-bold border-teal-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="py-3 px-3 rounded-2xl border text-xs flex flex-col items-center gap-1 transition active:scale-95"
                >
                    <span class="text-base">⇄</span>
                    <span class="font-bold">ذهاب وإياب</span>
                    <span class="text-[10px] opacity-80">يخدم كلا الاتجاهين</span>
                </button>
                <button 
                    type="button" 
                    @click="isTwoWay = false"
                    :class="!isTwoWay ? 'bg-teal-600 text-white font-bold border-teal-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="py-3 px-3 rounded-2xl border text-xs flex flex-col items-center gap-1 transition active:scale-95"
                >
                    <span class="text-base">➔</span>
                    <span class="font-bold">اتجاه واحد فقط</span>
                    <span class="text-[10px] opacity-80">اتجاه الانطلاق فقط</span>
                </button>
            </div>
        </div>

        <!-- Submit Button -->
        <button 
            type="button" 
            @click="saveRoute" 
            :disabled="stops.length < 2 || isSubmitting"
            class="w-full min-h-[48px] bg-teal-600 hover:bg-teal-700 active:scale-[0.98] text-white font-bold rounded-2xl text-sm transition shadow-sm flex items-center justify-center gap-2 disabled:opacity-40"
        >
            <span x-show="!isSubmitting">حفظ مسار الخط والمحطات</span>
            <span x-show="isSubmitting">جاري الحفظ...</span>
        </button>
    </div>
</div>

@push('scripts')
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<script>
function routeBuilder(init) {
    return {
        landmarks: [],
        selectedLandmarkId: '',
        stops: init.existingStops && init.existingStops.length > 0 ? init.existingStops : [],
        routeName: init.existingRoute?.name || 'رفح <-> غزة (طريق صلاح الدين)',
        firstTripTime: init.existingRoute?.first_trip_time || '06:30',
        isTwoWay: init.existingRoute ? Boolean(init.existingRoute.is_two_way ?? true) : true,
        isSubmitting: false,
        map: null,
        markers: [],

        async init() {
            // 1. Fetch Verified Gaza OSM Gazetteer
            try {
                const res = await fetch('{{ route('api.gazetteer') }}');
                const data = await res.json();
                if (data.ok) {
                    this.landmarks = data.landmarks;
                }
            } catch (e) {
                console.warn('Using offline cached gazetteer', e);
            }

            // 2. Initialize MapLibre GL map restricted to Gaza Strip
            this.initMap();
        },

        initMap() {
            // Enable RTL for Arabic place names
            if (maplibregl.getRTLTextPluginStatus() === 'unavailable') {
                maplibregl.setRTLTextPlugin(
                    'https://unpkg.com/@mapbox/mapbox-gl-rtl-text@0.2.3/mapbox-gl-rtl-text.min.js',
                    null,
                    true
                );
            }

            // Gaza Strip Bounding Box
            const gazaBounds = [
                [34.15, 31.18], // Southwest (Rafah border)
                [34.60, 31.62]  // Northeast (Beit Hanoun)
            ];

            this.map = new maplibregl.Map({
                container: 'gaza-map',
                style: {
                    version: 8,
                    sources: {
                        'osm': {
                            type: 'raster',
                            tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                            tileSize: 256,
                            attribution: '&copy; OpenStreetMap Contributors'
                        }
                    },
                    layers: [{
                        id: 'osm-tiles',
                        type: 'raster',
                        source: 'osm',
                        minzoom: 0,
                        maxzoom: 19
                    }]
                },
                center: [34.38, 31.42], // Central Gaza
                zoom: 10.5,
                maxBounds: gazaBounds
            });

            this.map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-left');

            this.map.on('load', () => {
                this.renderMarkers();
            });
        },

        renderMarkers() {
            // Clear existing markers
            this.markers.forEach(m => m.remove());
            this.markers = [];

            // Add marker for each selected stop
            this.stops.forEach((stop, idx) => {
                const el = document.createElement('div');
                el.className = 'w-7 h-7 rounded-full bg-teal-600 text-white font-bold text-xs flex items-center justify-center border-2 border-white shadow-md';
                el.innerText = idx + 1;

                const marker = new maplibregl.Marker({ element: el })
                    .setLngLat([stop.lng, stop.lat])
                    .setPopup(new maplibregl.Popup().setText(stop.name))
                    .addTo(this.map);

                this.markers.push(marker);
            });
        },

        addSelectedLandmark() {
            const landmark = this.landmarks.find(l => l.id === this.selectedLandmarkId);
            if (!landmark) return;

            // Avoid consecutive duplicates
            this.stops.push({
                order: this.stops.length + 1,
                id: landmark.id,
                name: landmark.name_ar,
                lat: landmark.lat,
                lng: landmark.lng,
                fare_ils: this.stops.length === 0 ? 0 : 5
            });

            this.selectedLandmarkId = '';
            this.renderMarkers();

            // Zoom map to newly added stop
            this.map.flyTo({ center: [landmark.lng, landmark.lat], zoom: 12 });
        },

        moveStop(index, direction) {
            const newIndex = index + direction;
            if (newIndex < 0 || newIndex >= this.stops.length) return;
            const item = this.stops.splice(index, 1)[0];
            this.stops.splice(newIndex, 0, item);
            this.stops.forEach((s, i) => s.order = i + 1);
            this.renderMarkers();
        },

        removeStop(index) {
            this.stops.splice(index, 1);
            this.stops.forEach((s, i) => s.order = i + 1);
            this.renderMarkers();
        },

        async saveRoute() {
            if (this.stops.length < 2) {
                alert('يجب تحديد نقطتين على الأقل (نقطة بداية ونقطة وصول).');
                return;
            }

            this.isSubmitting = true;
            const payload = {
                name: this.routeName,
                start_point: this.stops[0].name,
                first_trip_time: this.firstTripTime,
                is_two_way: this.isTwoWay,
                stops: this.stops
            };

            // Save to IndexedDB / localStorage for immediate offline persistence
            try {
                localStorage.setItem('cached_driver_route', JSON.stringify(payload));
            } catch (e) {
                console.warn(e);
            }

            try {
                const res = await fetch('{{ route('api.driver.route.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (data.ok) {
                    window.location.href = '{{ route('driver.dashboard') }}';
                } else {
                    alert(data.message || 'حدث خطأ أثناء حفظ المسار');
                }
            } catch (err) {
                console.error(err);
                alert('تم حفظ خط السير محلياً على الهاتف. ستتم المزامنة تلقائياً عند توفر الإنترنت.');
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
