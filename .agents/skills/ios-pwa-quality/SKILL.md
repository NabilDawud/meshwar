---
name: ios-pwa-quality
description: Use when building, styling, or debugging mobile Progressive Web Apps (PWAs) targeting iPhone Safari and standalone mobile browsers. Covers viewport safe-areas, touch interactions, offline caching, service workers, and iOS manifest quirks.
---

# iOS Safari & Mobile PWA Engineering

iOS Safari enforces strict, idiosyncratic rules for standalone web apps and mobile views. Follow these standards to deliver a 60fps native feel.

## 1. Viewport & Safe Area Insets (The Notch & Home Indicator)
- Always include `viewport-fit=cover` in the viewport meta tag:
  ```html
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  ```
- Use CSS environment variables for header and bottom bar padding:
  ```css
  /* Header / Status Bar */
  padding-top: env(safe-area-inset-top, 20px);
  /* Bottom Navigation / Home Bar */
  padding-bottom: env(safe-area-inset-bottom, 20px);
  ```
- **Dynamic Viewport Height**: Always use `100dvh` (or `h-dvh` in modern Tailwind) rather than `100vh` to avoid layout jumps when the Safari address bar collapses/expands.

## 2. Touch & Scrolling Native Feel
- **Prevent Elastic Bounce on Body**:
  ```css
  html, body {
    overscroll-behavior-y: none;
    -webkit-overflow-scrolling: touch;
  }
  ```
- **Remove Tap Highlight & Double-Tap Delay**:
  ```css
  * {
    -webkit-tap-highlight-color: transparent;
  }
  button, a, input, select {
    touch-action: manipulation;
  }
  ```
- **Disable Callout Magnifier & Selection on UI Chrome**:
  ```css
  .ui-chrome, nav, header, button {
    -webkit-touch-callout: none;
    user-select: none;
  }
  ```
- **Form Auto-Zoom Prevention**: Ensure font size on all input fields is at least `16px` (`text-base`) on mobile to stop iOS Safari from auto-zooming into fields on focus.

## 3. iOS Standalone Manifest & Apple Meta Tags
Include all required iOS headers in `<head>`:
```html
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Meshwar">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon-180x180.png">
<link rel="manifest" href="/manifest.webmanifest">
```

## 4. Offline-First Architecture
- **App Shell Cache (Workbox)**: Cache core HTML shell, CSS, JS, and web fonts with a `StaleWhileRevalidate` strategy.
- **Client-Side Database**: Store user records, offline mutations, and cached state locally in **IndexedDB** (using Dexie.js or idb-keyval).
- **Background Sync Queue**: Capture offline actions (e.g., booking a trip, saving preferences) in IndexedDB, and synchronize with Laravel API endpoints via an optimistic queue once `navigator.onLine` fires.
