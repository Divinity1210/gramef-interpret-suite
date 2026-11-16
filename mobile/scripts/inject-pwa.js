/**
 * Post-build script: injects PWA meta tags into the Expo-generated index.html
 * 
 * Expo's `+html.tsx` is not reliably picked up during `expo export`,
 * so we inject the required tags directly into the built HTML.
 */
const fs = require('fs');
const path = require('path');

const distDir = path.resolve(process.cwd(), 'dist');
const indexPath = path.join(distDir, 'index.html');

if (!fs.existsSync(indexPath)) {
  console.error('ERROR: dist/index.html not found');
  process.exit(1);
}

let html = fs.readFileSync(indexPath, 'utf8');

// PWA meta tags to inject into <head>
const pwaTags = `
    <!-- PWA: Viewport for standalone app -->
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />

    <!-- PWA: iOS standalone app support -->
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <meta name="apple-mobile-web-app-title" content="AtoZ" />
    <meta name="mobile-web-app-capable" content="yes" />

    <!-- PWA: App icons for iOS home screen -->
    <link rel="apple-touch-icon" href="/icon-192.png" />
    <link rel="apple-touch-icon" sizes="180x180" href="/icon-192.png" />
    <link rel="apple-touch-icon" sizes="152x152" href="/icon-152.png" />
    <link rel="apple-touch-icon" sizes="144x144" href="/icon-144.png" />
    <link rel="apple-touch-icon" sizes="120x120" href="/icon-128.png" />

    <!-- PWA: Standard favicons -->
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png" />
    <link rel="icon" type="image/png" sizes="96x96" href="/icon-96.png" />
    <link rel="icon" type="image/png" sizes="32x32" href="/icon-96.png" />

    <!-- PWA: Web manifest for Android install & standalone mode -->
    <link rel="manifest" href="/manifest.json" />

    <!-- PWA: Service worker registration (Disabled for rapid development) -->
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
          navigator.serviceWorker.getRegistrations().then(function(registrations) {
            for(let registration of registrations) {
              registration.unregister();
              console.log('SW unregistered to prevent caching during dev');
            }
          });
        });
      }
    </script>

    <!-- PWA: CSS for fullscreen native feel -->
    <style>
      /* CSS custom properties - updated at runtime by WebBodyThemeSync */
      :root {
        --pwa-bg: #121212;
        --pwa-surface: #1E1E1E;
      }

      /* 
       * NATIVE SAFARI SAFE AREA FIX
       * iOS Safari uses html background for the top overscroll/safe-area,
       * and body background for the bottom overscroll/safe-area.
       * We set these colors dynamically in WebBodyThemeSync!
       */
      html, body {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        height: 100% !important;
        overflow: hidden !important;
      }
      body {
        overscroll-behavior-y: contain;
        -webkit-overflow-scrolling: touch;
      }

      #root {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
        background-color: var(--pwa-bg); /* Prevents body color showing through */
        padding-top: env(safe-area-inset-top, 0px) !important;
        box-sizing: border-box !important;
      }
      /* Hide all scrollbars */
      * { scrollbar-width: none; -ms-overflow-style: none; }
      *::-webkit-scrollbar { display: none; width: 0; height: 0; }

      /* Prevent tap flash & text resizing */
      * { -webkit-tap-highlight-color: transparent; }
      html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }
      input, select, textarea { font-size: 16px !important; }
    </style>
`;

// Remove the default viewport meta tag (we'll replace it with ours)
html = html.replace(
  /<meta name="viewport"[^>]*>/i,
  ''
);

// Inject PWA tags right before </head>
if (html.includes('</head>')) {
  html = html.replace('</head>', pwaTags + '\n  </head>');
  console.log('✅ PWA tags injected into dist/index.html');
} else {
  console.error('ERROR: Could not find </head> in index.html');
  process.exit(1);
}

fs.writeFileSync(indexPath, html, 'utf8');
console.log('✅ dist/index.html updated successfully');
