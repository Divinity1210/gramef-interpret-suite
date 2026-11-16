import { useEffect } from 'react';
import { Platform } from 'react-native';
import { useTheme } from '../ui/theme';

/**
 * WebBodyThemeSync
 *
 * 1. NATIVE SAFARI SAFE AREA FIX:
 *    On iOS PWAs, the browser automatically paints the safe areas using the
 *    document's background colors.
 *    - The TOP safe area (status bar) uses the `<html>` background.
 *    - The BOTTOM safe area (home indicator) uses the `<body>` background.
 *
 * 2. Syncs the #root background to prevent the <body> color from showing
 *    through any transparent sections of the React Native app.
 */
export default function WebBodyThemeSync() {
  const { colors } = useTheme();

  useEffect(() => {
    if (Platform.OS !== 'web' || typeof document === 'undefined') return;

    const html = document.documentElement;
    const body = document.body;

    // TOP safe area (Status Bar) uses HTML background
    html.style.backgroundColor = colors.bg;

    // BOTTOM safe area (Home Indicator) uses BODY background
    body.style.backgroundColor = colors.surface;

    // CSS custom properties used by #root
    html.style.setProperty('--pwa-bg', colors.bg);
    html.style.setProperty('--pwa-surface', colors.surface);
  }, [colors.bg, colors.surface]);

  return null;
}

