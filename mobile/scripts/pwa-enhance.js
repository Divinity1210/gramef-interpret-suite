/**
 * Post-build script for PWA full-screen support.
 * 
 * Expo's web export generates index.html with a basic viewport meta tag.
 * This script modifies the built HTML to add:
 * - viewport-fit=cover for notched devices (iPhone X+)
 * - apple-mobile-web-app-capable for iOS standalone mode
 * - Safe area CSS for edge-to-edge rendering
 * - 100dvh height for full viewport coverage
 * 
 * Run after `expo export --platform web`
 */

const fs = require('fs');
const path = require('path');

const distHtml = path.join(__dirname, '..', 'dist', 'index.html');

if (!fs.existsSync(distHtml)) {
  console.log('⚠️  dist/index.html not found, skipping PWA enhancements');
  process.exit(0);
}

let html = fs.readFileSync(distHtml, 'utf8');

// 1. Replace viewport meta to add viewport-fit=cover
html = html.replace(
  /<meta name="viewport"[^>]*>/,
  '<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=5, user-scalable=no, viewport-fit=cover" />'
);

// 2. Add PWA meta tags after the viewport meta
const pwaMeta = `
    <!-- PWA Full-Screen Support -->
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <meta name="apple-mobile-web-app-title" content="AtoZ" />
    <meta name="mobile-web-app-capable" content="yes" />

    <!-- iOS Home Screen Icons -->
    <link rel="apple-touch-icon" href="/icon-192.png" />
    <link rel="apple-touch-icon" sizes="180x180" href="/icon-192.png" />
    <link rel="apple-touch-icon" sizes="152x152" href="/icon-152.png" />
    <link rel="apple-touch-icon" sizes="144x144" href="/icon-144.png" />
    <link rel="apple-touch-icon" sizes="128x128" href="/icon-128.png" />

    <!-- Standard favicons -->
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png" />
    <link rel="icon" type="image/png" sizes="96x96" href="/icon-96.png" />

    <!-- Web manifest for Android/Chrome PWA -->
    <link rel="manifest" href="/manifest.json" />`;

// Insert PWA meta after the theme-color meta tag (or after viewport if no theme-color)
if (html.includes('theme-color')) {
  html = html.replace(
    /(<meta name="theme-color"[^>]*>)/,
    `$1${pwaMeta}`
  );
} else {
  html = html.replace(
    /(viewport-fit=cover"[^>]*>)/,
    `$1${pwaMeta}`
  );
}

// 3. Add CSS for full viewport coverage and safe area insets
const fullScreenCSS = `
    <style id="pwa-fullscreen">
      /* Full viewport coverage with dynamic viewport height */
      html, body, #root {
        min-height: 100dvh;
        min-height: -webkit-fill-available;
        margin: 0;
        padding: 0;
        overflow: hidden;
      }
      
      /* Safe area insets for notched devices */
      body {
        padding-top: env(safe-area-inset-top);
        padding-bottom: env(safe-area-inset-bottom);
        padding-left: env(safe-area-inset-left);
        padding-right: env(safe-area-inset-right);
      }
      
      /* Disable pull-to-refresh in standalone PWA */
      body {
        overscroll-behavior-y: contain;
        -webkit-overflow-scrolling: touch;
      }
      
      /* Prevent tap highlight on mobile */
      * { -webkit-tap-highlight-color: transparent; }
      
      /* Prevent text size adjustment on orientation change */
      html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }
      
      /* Prevent iOS zoom on input focus (inputs must be >= 16px) */
      input, select, textarea { font-size: 16px !important; }
    </style>`;

// Insert before closing </head>
html = html.replace('</head>', `${fullScreenCSS}\n  </head>`);

fs.writeFileSync(distHtml, html, 'utf8');
console.log('✅ PWA full-screen enhancements applied to dist/index.html');
