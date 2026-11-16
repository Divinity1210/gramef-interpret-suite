import { ScrollViewStyleReset } from 'expo-router/html';
import type { PropsWithChildren } from 'react';

/**
 * Custom HTML template for Expo Router web builds.
 * Adds PWA full-screen support with viewport-fit=cover for notched devices,
 * apple-mobile-web-app meta tags, and CSS for 100dvh full viewport coverage.
 */
export default function Root({ children }: PropsWithChildren) {
  return (
    <html lang="en">
      <head>
        <meta charSet="utf-8" />
        <meta httpEquiv="X-UA-Compatible" content="IE=edge" />

        {/* Full-screen PWA viewport — viewport-fit=cover fills notched device screens */}
        <meta
          name="viewport"
          content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover"
        />

        {/* iOS PWA full-screen support */}
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
        <meta name="apple-mobile-web-app-title" content="AtoZ" />

        {/* iOS home screen icon — iOS ignores manifest icons, uses apple-touch-icon */}
        <link rel="apple-touch-icon" href="/icon-192.png" />
        <link rel="apple-touch-icon" sizes="180x180" href="/icon-192.png" />
        <link rel="apple-touch-icon" sizes="152x152" href="/icon-152.png" />

        {/* Standard favicon */}
        <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png" />
        <link rel="icon" type="image/png" sizes="32x32" href="/icon-96.png" />

        {/* Web manifest */}
        <link rel="manifest" href="/manifest.json" />

        {/* Android PWA theme */}
        <meta name="theme-color" content="#0d6efd" />
        <meta name="mobile-web-app-capable" content="yes" />

        {/* Prevent text size adjustment on orientation change */}
        <style dangerouslySetInnerHTML={{
          __html: `
          /* Full viewport coverage */
          html, body, #root {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            min-height: 100dvh;
            overflow: hidden;
            background-color: #F8F9FA;
          }

          @media (prefers-color-scheme: dark) {
            html, body, #root {
              background-color: #121212;
            }
          }

          /* Safe area insets for notched devices (iPhone X+, modern Android) */
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
          * {
            -webkit-tap-highlight-color: transparent;
          }

          /* Prevent text size adjustment */
          html {
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
          }

          /* Ensure input fields don't zoom on iOS */
          input, select, textarea {
            font-size: 16px !important;
          }
        `}} />

        {/* Reset default scroll styles from Expo */}
        <ScrollViewStyleReset />
      </head>
      <body>{children}</body>
    </html>
  );
}
