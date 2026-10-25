# Meshwar Project Architecture Rules

## Core Stack
- **Backend**: Laravel 11.x, PHP 8.2+, MySQL
- **Frontend Architecture**: Mobile-first PWA (Blade + Tailwind CSS + Alpine.js, or Inertia.js)
- **Maps**: MapLibre GL JS with Vector Tiles
- **Auth**: Google OAuth (Laravel Socialite) + Standard Auth
- **Testing**: PHPUnit / Pest for backend tests, Chrome DevTools MCP / browser automation for E2E verification

## Non-Negotiable Standards
1. **Mobile Native Feel**:
   - Every view must look and act like a native iOS application.
   - Respect `env(safe-area-inset-top)` and `env(safe-area-inset-bottom)`.
   - Prevent pinch-zoom/elastic bounce on static chrome.
2. **Arabic & RTL by Default**:
   - All components must use CSS logical properties (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`).
   - Line height must accommodate Arabic diacritics (`leading-relaxed`).
3. **Offline Resilience**:
   - Critical read operations and forms must degrade gracefully without network.
   - Cache static assets via Service Worker (Workbox).
4. **Superpower TDD**:
   - Every feature must have an automated test before being marked complete.
