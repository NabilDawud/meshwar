---
name: offline-first-pwa
description: Use when implementing offline persistence, service worker caching, IndexedDB outbox queuing, low-bandwidth delta synchronization, photo download gating (Wi-Fi only), or booking conflict resolution for intermittent connectivity.
---

# Offline-First PWA

Operational standards for surviving extreme network instability, power cuts, and zero-connectivity environments in Gaza.

## Core Directives

1. **Local-First Writes (Outbox Pattern)**:
   - Every mutation (booking, message, ride request, route status) writes immediately to local IndexedDB.
   - UI reflects state optimistically with clear visual confirmation: *"تم الحفظ على الهاتف، سيتم الإرسال فور توفر الإنترنت"* (Saved on this phone, will send when online).
2. **App Shell & Data Survivability**:
   - Service worker must cache the full UI shell, styling, web fonts, and core map assets.
   - Booting with zero internet must always display the cached shell and last-known data without blank screens or generic network error blocks.
   - Always display data age prominently (e.g., *"آخر تحديث: منذ ساعتين"*).
3. **Delta Sync & Byte Budget**:
   - Synchronization payloads must exchange only incremental changes (deltas) since the last timestamp sync token.
   - Keep baseline sync payload under **10 KB per cycle** over metered cellular links.
4. **Photo Download & Upload Gating**:
   - Photos must **never** be bundled into the main data sync stream.
   - Cache downloaded images locally indefinitely.
   - Automatically defer image uploads/downloads to unmetered Wi-Fi connections where identifiable via `navigator.connection`. Default to manual trigger otherwise: provide a *"تنزيل الصور الآن"* button and a toggle for *"تنزيل عبر Wi-Fi فقط"*.
5. **Deterministic Conflict Resolution**:
   - For booking seats: **The server authoritative clock decides; first booking processed wins**.
   - If an offline booking collides, notify rider immediately with an explanatory prompt and alternative seat options.

See architecture details in [sync-protocol.md](./references/sync-protocol.md).
