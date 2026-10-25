---
name: arabic-rtl
description: Use when implementing, reviewing, or styling Arabic and Right-to-Left (RTL) interfaces. Covers CSS logical properties, typography, bidirectional text isolation, directional vs neutral iconography, and Arabic number formatting.
---

# Arabic & Right-to-Left (RTL) Guidelines

Designing for Arabic requires native bidirectional understanding rather than just flipping an English layout.

## 1. Document Level & HTML Setup
- Always declare Arabic language and RTL direction on the root HTML tag:
  ```html
  <html lang="ar" dir="rtl">
  ```
- Set `dir="auto"` or isolate user-generated input (e.g., search fields, chat inputs) with `<bdi>` tags to prevent punctuation flip when mixed with Latin letters or numbers.

## 2. CSS Logical Properties (Mandatory)
Never use physical directional properties (`left`, `right`, `margin-left`, `padding-right`). Always use logical CSS / modern Tailwind:

| Avoid (Physical) | Use (Logical / Tailwind) | Meaning |
| :--- | :--- | :--- |
| `mr-*`, `ml-*` | `me-*`, `ms-*` | Margin inline end / start |
| `pr-*`, `pl-*` | `pe-*`, `ps-*` | Padding inline end / start |
| `text-left` / `text-right` | `text-start` / `text-end` | Natural text alignment |
| `left-0`, `right-0` | `start-0`, `end-0` | Positioning start / end |
| `rounded-l-*`, `rounded-r-*` | `rounded-s-*`, `rounded-e-*` | Logical border radius |
| `border-l-*`, `border-r-*` | `border-s-*`, `border-e-*` | Logical borders |

## 3. Arabic Typography & Font Rendering
- **Font Selection**: Prefer fonts with excellent Arabic balance, such as **IBM Plex Sans Arabic**, **Cairo**, or **Tajawal** (or Apple system Arabic fallback: `-apple-system, BlinkMacSystemFont, "Segoe UI", Tahoma, Arial, sans-serif`).
- **Line Heights**: Arabic script has ascending and descending diacritics (harakat) and calligraphic curves. Set line-height to `1.6` - `1.75` (e.g., `leading-relaxed` or `leading-loose`) to prevent diacritic clipping.
- **Font Weight**: Arabic glyphs typically need slightly thicker weight for small body text (`font-medium` or `font-normal` with 500 weight) compared to Latin fonts.

## 4. Directional vs Non-Directional Iconography
- **Icons to Flip in RTL**:
  - Navigation arrows (back/forward, chevrons: `transform rtl:rotate-180` or use logical icons).
  - Progress bars & sliders (fill from right to left).
  - Breadcrumbs & paging arrows.
- **Icons to NEVER Flip**:
  - Search magnifying glass (handle stays at bottom-right or natural orientation).
  - Hardware & media icons (camera, smartphone, headphones, battery, volume).
  - Clock / time icons (clocks run clockwise regardless of script).
  - Branding logos & checkmarks.

## 5. Map Integration with RTL
- When using MapLibre GL JS, enable Arabic glyph shaping via `@mapbox/mapbox-gl-rtl-text`:
  ```javascript
  import maplibregl from 'maplibre-gl';
  maplibregl.setRTLTextPlugin(
    'https://unpkg.com/@mapbox/mapbox-gl-rtl-text@0.2.3/mapbox-gl-rtl-text.min.js',
    null,
    true // Lazy load
  );
  ```
