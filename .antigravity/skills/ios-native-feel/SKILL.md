---
name: ios-native-feel
description: Use when designing, building, or styling mobile UI components to ensure an authentic iOS native app feel, including standalone PWA configuration, safe-area insets, dynamic viewport heights, touch target sizing, double-tap zoom suppression, sheet modals, skeletons, and Palestinian Arabic RTL typography.
---

# iOS Native Feel

Design guidelines and mobile viewport engineering standards for Safari on iPhone and standalone mobile web apps.

## Key Directives

1. **Standalone PWA & Viewport Geometry**:
   - Set `<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">`.
   - Use `h-dvh` / `100dvh` (dynamic viewport height). Never use static `100vh`.
   - Pad headers and fixed bottom bars with `env(safe-area-inset-top)` and `env(safe-area-inset-bottom)`.
2. **Accidental Zoom Suppression**:
   - All input controls, textareas, and selects must have font size `16px` (`text-base` or greater) to prevent iOS auto-zoom on focus.
   - Apply `touch-action: manipulation` across all buttons and clickable elements to kill double-tap delay and accidental zoom.
   - Ensure maximum component width never exceeds `100vw`.
3. **Ergonomic Native Controls**:
   - Fixed bottom tab bar for primary sections (Rides, Daily Routes, Requests, Profile).
   - Bottom sheets for contextual interactions, filters, and confirmations; avoid floating centered dialog popups.
   - Minimum 44×44px touch targets. No hover-only triggers.
   - Skeletons for pending states; avoid central blocking spinners.
   - Zero layout shifts (CLS) when server data streams in.
4. **Palestinian Arabic RTL Typography**:
   - Universal RTL layout via CSS logical properties (`ms-*`, `me-*`, `ps-*`, `pe-*`, `start-*`, `end-*`).
   - Clean Arabic system font stack with comfortable `line-height` (1.6 to 1.75) to prevent clipped diacritics.
5. **Strict Design Tokens**:
   - Unified, minimal palette with light and dark mode variants.

See token specifications and CSS snippets in [design-tokens-and-ios-quirks.md](./references/design-tokens-and-ios-quirks.md).
