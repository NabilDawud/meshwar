/**
 * gaza-geo-sync client helper for Mishwar
 * Low-bandwidth utilities for live position synchronization over 2G/EDGE and crowded Wi-Fi.
 * Based on https://github.com/AQaddora/gaza-geo-sync
 */
(function (global) {
    const GAZA_BOUNDS = {
        south: 31.2,
        west: 34.2,
        scale: 1e5 // 1e-5° ≈ 1.1m
    };

    const SEND_MIN_METERS = 15;
    const SEND_HEARTBEAT_MS = 60000;

    /**
     * Quantise coordinates to 5 decimal places (~1.1m precision),
     * stripping floating-point noise and saving ~30% wire bytes.
     */
    function quantize(val) {
        return Math.round(val * 1e5) / 1e5;
    }

    /**
     * Approximate equirectangular distance in meters between two lat/lon points.
     */
    function distanceMeters(a, b) {
        const dy = (b.lat - a.lat) * 111320;
        const dx = (b.lng - a.lng) * 111320 * Math.cos((((a.lat + b.lat) / 2) * Math.PI) / 180);
        return Math.hypot(dx, dy);
    }

    /**
     * Standing-still throttle (worthSending):
     * When parked or waiting for passengers, avoid sending repetitive GPS fixes every 15s.
     * Only send if moved at least 15m or 60s heartbeat elapsed.
     */
    function worthSending(last, next, now = Date.now()) {
        if (!last || (now - last.sentAt) >= SEND_HEARTBEAT_MS) {
            return true;
        }
        return distanceMeters(last, next) >= SEND_MIN_METERS;
    }

    global.GazaGeoSync = {
        GAZA_BOUNDS,
        quantize,
        distanceMeters,
        worthSending,
        SEND_MIN_METERS,
        SEND_HEARTBEAT_MS
    };
})(typeof window !== 'undefined' ? window : this);
