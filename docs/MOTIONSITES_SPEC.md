# GRAMEF InterpretFlow · MotionSites Build Spec

## 0. Brief
Build a gallery-grade product showcase (`/showcase`), cinematic split login (`/login`), public no-password magic signature & remittance pages, and unified executive workspace shell for **GRAMEF InterpretFlow** — a white-label SaaS platform by GRAMEF Digital empowering interpreting & translation agencies (and their clients, interpreters, and finance teams).
- **Mood (3 words):** Precision, Executive, Luminous.
- **Signature idea:** Full-viewport luminous wave video canvas (`#07090E`) behind a floating liquid-glass command pill, `Plus Jakarta Sans` + `Instrument Serif` italic headline accent, and an interactive Role Launchpad + Live Agency ROI Calculator.

## 1. Stack & Files
- Blade + Vanilla CSS/JS (zero build step required for production Docker speed) + Chart-free interactive ROI sliders & Canvas Signature Pad.
- Files:
  - `public/css/gramef-motionsites.css`
  - `resources/views/showcase.blade.php`
  - `resources/views/layouts/login.blade.php` & `resources/views/auth/login.blade.php`
  - `resources/views/layouts/app.blade.php`

## 2. Fonts
- Display & UI: `Plus Jakarta Sans` (400, 500, 600, 700), tight tracking `-0.03em` on headings.
- Editorial Accent: `Instrument Serif` (400, italic) on 1–2 accent words inside display headlines.
- Telemetry / Currency / Indices: `JetBrains Mono` (400, 500, 600), `font-variant-numeric: tabular-nums`.
- Load URL: `https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap`

## 3. Colour Tokens
```css
:root {
  --bg-nocturnal: #07090E;
  --fg-nocturnal: #F8FAFC;
  --muted-nocturnal: #94A3B8;
  --bg-daylight: #F4F6FA;
  --sidebar-obsidian: #0B0F19;
  --card-surface: #FFFFFF;
  --card-border: #E2E8F0;
  --brand-primary: #2563EB;
  --brand-accent: #10B981;
  --amber-check: #F59E0B;
}
```

## 4. Assets
- **Hero Video (`media/MEDIA.md` #16)**:
  `https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260508_064122_c4750c0e-7476-4b44-94a2-a85a65c63bf2.mp4`
  Attributes: `autoplay muted loop playsinline preload="auto"`, `object-fit: cover; object-position: center`.
  Overlay scrim: `linear-gradient(180deg, rgba(7,9,14,0.72) 0%, rgba(7,9,14,0.28) 45%, rgba(7,9,14,0.95) 100%)`.

## 5. Motion & Reduced Motion
- Staggered blur-rise hero choreography (`0ms → 120ms → 240ms → 360ms`, `0.85s cubic-bezier(0.22, 1, 0.36, 1)`), with JS fallback forcing `.is-in` after 150ms so nothing can stay invisible.
- Full `@media (prefers-reduced-motion: reduce)` branch restoring immediate opacity 1 and halting transitions/animations.
