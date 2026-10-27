/**
 * Mishwar Gaza - Client-Side Offline Trip Planner
 * Runs deterministic corridor pathfinding in the browser without network access.
 */
class OfflineTripPlanner {
    constructor() {
        this.routes = [];
        this.init();
    }

    async init() {
        // Try fetching latest network graph; fall back to local offline cache
        try {
            const res = await fetch('/api/routes/graph');
            const data = await res.json();
            if (data.ok && data.routes) {
                this.routes = data.routes;
                localStorage.setItem('mishwar_route_graph', JSON.stringify(this.routes));
            }
        } catch (e) {
            const cached = localStorage.getItem('mishwar_route_graph');
            if (cached) {
                this.routes = JSON.parse(cached);
            }
        }
    }

    plan(from, to) {
        from = (from || '').trim().toLowerCase();
        to = (to || '').trim().toLowerCase();

        if (!from || !to || from === to) {
            return {
                ok: true,
                found: false,
                message: 'يرجى اختيار نقطتي انطلاق ووصول مختلفتين',
                itineraries: []
            };
        }

        const direct = this.findDirect(from, to);
        const transfer = this.findTransfer(from, to);
        const itineraries = [...direct, ...transfer];

        if (itineraries.length === 0) {
            return {
                ok: true,
                found: false,
                message: 'لا يوجد مسار متاح بين هاتين النقطتين حالياً في وضع عدم الاتصال',
                itineraries: []
            };
        }

        itineraries.sort((a, b) => a.transfers - b.transfers || a.total_fare_ils - b.total_fare_ils);

        return {
            ok: true,
            found: true,
            offline: true,
            count: itineraries.length,
            ai_advice: {
                enabled: false,
                provider: 'offline_engine',
                tip: itineraries[0].transfers > 0
                    ? `نصيحة مشوار: التبديل عند ${itineraries[0].transfer_junction}، يرجى تجهيز الأجرة نقداً مسبقاً.`
                    : `نصيحة مشوار: رحلة مباشرة على الخط، يفضل التواجد قبل موعد الرحلة بـ 10 دقائق.`
            },
            itineraries
        };
    }

    findDirect(from, to) {
        const results = [];
        for (const route of this.routes) {
            const stops = route.stops || [];
            const fromIdx = this.findIdx(stops, from);
            const toIdx = this.findIdx(stops, to);

            if (fromIdx !== -1 && toIdx !== -1 && fromIdx < toIdx) {
                const segment = stops.slice(fromIdx, toIdx + 1);
                const fare = this.calcFare(segment);

                results.push({
                    id: 'direct_' + route.id,
                    type: 'direct',
                    transfers: 0,
                    total_fare_ils: fare,
                    route_name: route.name,
                    departure_time: route.departure_time,
                    vehicle: route.vehicle,
                    from_stop: stops[fromIdx].name,
                    to_stop: stops[toIdx].name,
                    stops: segment
                });
            }
        }
        return results;
    }

    findTransfer(from, to) {
        const results = [];
        for (const r1 of this.routes) {
            const s1 = r1.stops || [];
            const fromIdx = this.findIdx(s1, from);
            if (fromIdx === -1) continue;

            for (const r2 of this.routes) {
                if (r1.id === r2.id) continue;
                const s2 = r2.stops || [];
                const toIdx = this.findIdx(s2, to);
                if (toIdx === -1) continue;

                for (let t1 = fromIdx + 1; t1 < s1.length; t1++) {
                    const candidate = s1[t1];
                    const t2 = this.findIdx(s2, candidate.name);

                    if (t2 !== -1 && t2 < toIdx) {
                        const leg1Stops = s1.slice(fromIdx, t1 + 1);
                        const leg2Stops = s2.slice(t2, toIdx + 1);
                        const fare1 = this.calcFare(leg1Stops);
                        const fare2 = this.calcFare(leg2Stops);

                        results.push({
                            id: `transfer_${r1.id}_${r2.id}`,
                            type: 'one_transfer',
                            transfers: 1,
                            total_fare_ils: fare1 + fare2,
                            transfer_junction: candidate.name,
                            transfer_wait_minutes: 15,
                            leg_1: {
                                route_name: r1.name,
                                departure_time: r1.departure_time,
                                from: s1[fromIdx].name,
                                to: candidate.name,
                                fare_ils: fare1,
                                vehicle: r1.vehicle
                            },
                            leg_2: {
                                route_name: r2.name,
                                departure_time: r2.departure_time,
                                from: candidate.name,
                                to: s2[toIdx].name,
                                fare_ils: fare2,
                                vehicle: r2.vehicle
                            }
                        });
                        break;
                    }
                }
            }
        }
        return results;
    }

    findIdx(stops, query) {
        const q = query.toLowerCase();
        for (let i = 0; i < stops.length; i++) {
            const name = (stops[i].name || '').toLowerCase();
            const id = (stops[i].id || '').toLowerCase();
            if (name === q || id === q || name.includes(q) || q.includes(name)) {
                return i;
            }
        }
        return -1;
    }

    calcFare(segment) {
        if (!segment || segment.length < 2) return 0;
        const last = Number(segment[segment.length - 1].fare_ils || 0);
        const first = Number(segment[0].fare_ils || 0);
        const diff = Math.abs(last - first);
        return diff > 0 ? diff : Math.max(5, last);
    }
}

window.offlinePlanner = new OfflineTripPlanner();
