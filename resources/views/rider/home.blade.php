@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css" />
<style>
    .mapboxgl-ctrl-bottom-left, .mapboxgl-ctrl-bottom-right { display: none; }
</style>
@endpush

@section('content')
<div class="flex-1 flex flex-col gap-3 p-3 sm:p-4" x-data="riderHome()">
    <!-- Top Search & Filter Bar -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-3 sm:p-4 border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col gap-2.5">
        <div class="flex items-center justify-between">
            <span class="text-xs font-black text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                <span>🔍</span>
                <span>بحث في رحلات ومركبات غزة النشطة</span>
            </span>
            <span class="text-[11px] font-bold text-teal-600 dark:text-teal-400" x-text="vehicles.length + ' مركبة نشطة'"></span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <!-- From Landmark -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-slate-500">نقطة الانطلاق (من):</label>
                <select 
                    x-model="searchFrom" 
                    @change="fetchVehicles"
                    class="h-10 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
                >
                    <option value="">جميع نقاط الانطلاق</option>
                    <template x-for="item in landmarks" :key="item.id">
                        <option :value="item.name_ar" x-text="item.name_ar"></option>
                    </template>
                </select>
            </div>

            <!-- To Landmark -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-slate-500">نقطة الوصول (إلى):</label>
                <select 
                    x-model="searchTo" 
                    @change="fetchVehicles"
                    class="h-10 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
                >
                    <option value="">جميع نقاط الوصول</option>
                    <template x-for="item in landmarks" :key="item.id">
                        <option :value="item.name_ar" x-text="item.name_ar"></option>
                    </template>
                </select>
            </div>

            <!-- Departure Time -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-slate-500">الوقت التقريبي:</label>
                <div class="flex gap-1.5">
                    <input 
                        type="time" 
                        x-model="searchTime" 
                        @change="fetchVehicles"
                        class="flex-1 h-10 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-mono text-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
                    >
                    <button 
                        type="button" 
                        @click="resetSearch" 
                        x-show="searchFrom || searchTo || searchTime"
                        class="px-2.5 h-10 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300"
                        title="إعادة ضبط"
                    >
                        ✕
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Interactive Map of Gaza Strip -->
    <div class="relative w-full h-72 sm:h-96 rounded-3xl overflow-hidden border-2 border-slate-200 dark:border-slate-700 shadow-sm bg-slate-100 dark:bg-slate-800">
        <div id="rider-map" class="w-full h-full"></div>
        
        <!-- Live GPS & Cache Badge -->
        <div class="absolute top-3 end-3 bg-white/90 dark:bg-slate-900/90 backdrop-blur px-3 py-1.5 rounded-full border border-slate-200 dark:border-slate-700 text-[11px] font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5 shadow-sm pointer-events-none">
            <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
            <span>تحديث حي للمركبات</span>
        </div>
    </div>

    <!-- Vehicles List / Bottom Drawer -->
    <div class="flex flex-col gap-2.5">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-xs sm:text-sm text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                <span>🚐</span>
                <span>المركبات العاملة على الخطوط</span>
            </h2>
            <span class="text-[11px] text-slate-400">انقر على أي مركبة لتحديد موقعها بالخريطة</span>
        </div>

        <!-- Vehicle Cards Grid -->
        <div class="flex flex-col gap-3" x-show="vehicles.length > 0">
            <template x-for="vehicle in vehicles" :key="vehicle.vehicle_id">
                <div 
                    @click="zoomToVehicle(vehicle)"
                    class="p-4 rounded-3xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 hover:border-teal-500 transition shadow-sm cursor-pointer flex flex-col gap-3"
                    :class="selectedVehicleId === vehicle.vehicle_id ? 'border-teal-600 ring-2 ring-teal-500/20' : ''"
                >
                    <!-- Header Info -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <!-- Recognition Photo -->
                            <div class="w-14 h-14 rounded-2xl overflow-hidden bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 shrink-0">
                                <img :src="vehicle.photo" :alt="vehicle.vehicle_type_arabic" class="w-full h-full object-cover">
                            </div>

                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-black text-sm text-slate-800 dark:text-slate-100" x-text="vehicle.vehicle_type_arabic"></span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-teal-100 dark:bg-teal-950 text-teal-800 dark:text-teal-300" x-text="vehicle.free_seats + ' مقاعد شاغرة'"></span>
                                </div>
                                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 mt-0.5" x-text="vehicle.route_name"></p>
                                <span class="text-[11px] text-slate-400 font-mono" x-show="vehicle.license_plate" x-text="vehicle.license_plate"></span>
                            </div>
                        </div>

                        <!-- Schedule Badge -->
                        <span class="text-xs px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-mono font-bold shrink-0">
                            ⏰ <span x-text="vehicle.first_trip_time"></span>
                        </span>
                    </div>

                    <!-- Strictly Last Recorded Position + Age (NO Speculative ETAs) -->
                    <div class="p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-700 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5 text-slate-700 dark:text-slate-300">
                            <span class="text-base">📍</span>
                            <span>آخر موقع مسجل:</span>
                            <span class="font-bold text-teal-700 dark:text-teal-300" x-text="vehicle.last_landmark"></span>
                        </div>
                        <div class="flex items-center gap-1 text-[11px] text-slate-500 font-bold bg-white dark:bg-slate-800 px-2 py-0.5 rounded-lg border border-slate-200 dark:border-slate-700">
                            <span>⏱️</span>
                            <span x-text="vehicle.position_age_arabic"></span>
                        </div>
                    </div>

                    <!-- Stops overview -->
                    <div class="flex items-center gap-1 text-[11px] text-slate-400 overflow-x-auto pb-1">
                        <span class="font-bold shrink-0">المحطات:</span>
                        <template x-for="(stop, sIdx) in vehicle.stops" :key="sIdx">
                            <span class="shrink-0 flex items-center gap-1">
                                <span x-text="stop.name" class="text-slate-600 dark:text-slate-300"></span>
                                <span x-show="sIdx < vehicle.stops.length - 1">←</span>
                            </span>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="vehicles.length === 0" class="p-8 rounded-3xl border-2 border-dashed border-slate-200 dark:border-slate-700 text-center flex flex-col items-center gap-2 text-xs text-slate-400">
            <span class="text-3xl">🚐</span>
            <span class="font-bold text-slate-600 dark:text-slate-300">لا توجد مركبات مطابقة للبحث حالياً</span>
            <span>جرب تغيير نقطة الانطلاق أو الوصول، أو اضغط على إلغاء التصفية.</span>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<script>
function riderHome() {
    return {
        landmarks: [],
        vehicles: [],
        searchFrom: '',
        searchTo: '',
        searchTime: '',
        selectedVehicleId: null,
        map: null,
        markers: [],

        async init() {
            // 1. Fetch OSM Gazetteer
            try {
                const gRes = await fetch('{{ route('api.gazetteer') }}');
                const gData = await gRes.json();
                if (gData.ok) {
                    this.landmarks = gData.landmarks;
                }
            } catch (e) {
                console.warn('Could not load gazetteer', e);
            }

            // 2. Initialize Map
            this.initMap();

            // 3. Fetch Active Vehicles
            await this.fetchVehicles();

            // Refresh vehicles every 30 seconds
            setInterval(() => this.fetchVehicles(), 30000);
        },

        initMap() {
            if (maplibregl.getRTLTextPluginStatus() === 'unavailable') {
                maplibregl.setRTLTextPlugin(
                    'https://unpkg.com/@mapbox/mapbox-gl-rtl-text@0.2.3/mapbox-gl-rtl-text.min.js',
                    null,
                    true
                );
            }

            const gazaBounds = [
                [34.15, 31.18],
                [34.60, 31.62]
            ];

            this.map = new maplibregl.Map({
                container: 'rider-map',
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
                center: [34.36, 31.39],
                zoom: 10.5,
                maxBounds: gazaBounds
            });

            this.map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-left');

            this.map.on('load', () => {
                this.renderVehicleMarkers();
            });
        },

        async fetchVehicles() {
            try {
                const params = new URLSearchParams();
                if (this.searchFrom) params.append('from', this.searchFrom);
                if (this.searchTo) params.append('to', this.searchTo);
                if (this.searchTime) params.append('time', this.searchTime);

                const res = await fetch(`{{ route('api.rides.active') }}?${params.toString()}`);
                const data = await res.json();
                if (data.ok) {
                    this.vehicles = data.vehicles;
                    // Cache locally for offline viewing
                    try {
                        localStorage.setItem('cached_active_vehicles', JSON.stringify(this.vehicles));
                    } catch (e) {}
                    this.renderVehicleMarkers();
                }
            } catch (err) {
                console.warn('Network offline, restoring cached vehicles', err);
                const cached = localStorage.getItem('cached_active_vehicles');
                if (cached) {
                    this.vehicles = JSON.parse(cached);
                    this.renderVehicleMarkers();
                }
            }
        },

        renderVehicleMarkers() {
            if (!this.map) return;

            // Clear existing markers
            this.markers.forEach(m => m.remove());
            this.markers = [];

            this.vehicles.forEach(vehicle => {
                const el = document.createElement('div');
                const isAjala = vehicle.vehicle_type === 'ajala';
                el.className = 'w-9 h-9 rounded-2xl flex items-center justify-center text-lg shadow-lg cursor-pointer transition transform hover:scale-110 ' + 
                    (isAjala ? 'bg-amber-500 border-2 border-white' : 'bg-teal-600 border-2 border-white');
                el.innerText = isAjala ? '🛞' : '🚐';

                const popupHtml = `
                    <div style="direction: rtl; text-align: right; font-family: 'Cairo', sans-serif; padding: 4px;">
                        <b style="font-size: 13px;">${vehicle.vehicle_type_arabic} - ${vehicle.free_seats} مقاعد</b><br>
                        <span style="font-size: 11px; color: #475569;">${vehicle.route_name}</span><br>
                        <span style="font-size: 11px; color: #0d9488; font-weight: bold;">آخر موقع: ${vehicle.last_landmark}</span><br>
                        <span style="font-size: 10px; color: #64748b;">⏱️ ${vehicle.position_age_arabic}</span>
                    </div>
                `;

                const marker = new maplibregl.Marker({ element: el })
                    .setLngLat([vehicle.last_longitude, vehicle.last_latitude])
                    .setPopup(new maplibregl.Popup({ offset: 15 }).setHTML(popupHtml))
                    .addTo(this.map);

                el.addEventListener('click', () => {
                    this.selectedVehicleId = vehicle.vehicle_id;
                });

                this.markers.push(marker);
            });
        },

        zoomToVehicle(vehicle) {
            this.selectedVehicleId = vehicle.vehicle_id;
            if (this.map) {
                this.map.flyTo({
                    center: [vehicle.last_longitude, vehicle.last_latitude],
                    zoom: 13,
                    essential: true
                });
            }
        },

        resetSearch() {
            this.searchFrom = '';
            this.searchTo = '';
            this.searchTime = '';
            this.fetchVehicles();
        }
    };
}
</script>
@endpush
@endsection
