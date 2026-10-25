# Design Tokens & iOS Safari Quirks

## 1. Design Tokens (Light & Dark)
Stick strictly to this core palette:
- **Surface**:
  - Light: Base `#F9FAFB` (`bg-gray-50`), Card `#FFFFFF` (`bg-white`), Border `#E5E7EB` (`border-gray-200`)
  - Dark: Base `#0F172A` (`bg-slate-900`), Card `#1E293B` (`bg-slate-800`), Border `#334155` (`border-slate-700`)
- **Brand Primary**:
  - `#0D9488` (`teal-600`) / `#14B8A6` (`teal-500` in dark)
- **Status & Badges**:
  - Online/Available: `#16A34A` (`green-600`)
  - Offline/Queued: `#D97706` (`amber-600`)
  - Cancelled/Error: `#DC2626` (`red-600`)
- **Radii**:
  - Buttons/Cards: `rounded-2xl` (`16px`)
  - Badges/Pills: `rounded-full`
  - Sheets: `rounded-t-3xl` (`24px`)

## 2. Arabic Font Stack
```css
font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "IBM Plex Sans Arabic", "Cairo", Tahoma, sans-serif;
line-height: 1.65;
letter-spacing: normal;
```

## 3. iOS Bottom Sheet Implementation Template
Always slide up from bottom with touch-dismiss drag handle:
```html
<div class="fixed inset-0 z-50 flex flex-col justify-end bg-black/50 backdrop-blur-sm">
  <div class="w-full rounded-t-3xl bg-white p-6 pb-[calc(1.5rem+env(safe-area-inset-bottom))] shadow-2xl dark:bg-slate-800">
    <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-gray-300 dark:bg-slate-600"></div>
    <!-- Sheet Content -->
  </div>
</div>
```

## 4. Rubber-Banding & Touch Kill
```css
html, body {
  overscroll-behavior-y: none;
  -webkit-overflow-scrolling: touch;
  -webkit-user-select: none;
  -webkit-tap-highlight-color: transparent;
}

input, textarea {
  -webkit-user-select: text;
}
```
