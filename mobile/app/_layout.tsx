import { Stack } from 'expo-router';
import { useEffect } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { useAuthStore } from '../src/state/auth';
import ErrorBoundary from '../src/ui/components/ErrorBoundary';
import { ThemeProvider } from '../src/ui/theme';
import { ToastProvider } from '../src/ui/components/Toast';
import { NotificationProvider } from '../src/providers/NotificationProvider';
import InstallPrompt from '../src/components/InstallPrompt';
import { NotificationToastContainer } from '../src/components/NotificationToast';
import { polyfillAlertForWeb } from '../src/utils/alert';
import { usePushNotifications } from '../src/hooks/usePushNotifications';
import WebBodyThemeSync from '../src/components/WebBodyThemeSync';

// Patch Alert.alert for web — makes ALL Alert.alert() calls
// throughout the app work on web via window.confirm/window.alert
polyfillAlertForWeb();

export default function Layout() {
  const isReady = useAuthStore((s: any) => s.isReady);
  const hydrate = useAuthStore((s: any) => s.hydrate);

  // Register push notifications at the root level so it fires
  // for every authenticated session, regardless of which tab renders first
  usePushNotifications();

  useEffect(() => {
    if (!isReady) {
      hydrate();
    }
  }, [hydrate, isReady]);

  if (!isReady) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <SafeAreaProvider>
      <ThemeProvider>
        <WebBodyThemeSync />
        <ToastProvider>
          <NotificationProvider>
            <ErrorBoundary>
              <Stack screenOptions={{ headerShown: false }}>
                <Stack.Screen name="index" />
                <Stack.Screen name="onboarding" />
                <Stack.Screen name="register" />
                <Stack.Screen name="(tabs)" />
                <Stack.Screen name="login" />
              </Stack>
              {/* PWA Install Prompt - shows on web when not installed */}
              <InstallPrompt />
              {/* In-app notification toasts */}
              <NotificationToastContainer />
            </ErrorBoundary>
          </NotificationProvider>
        </ToastProvider>
      </ThemeProvider>
    </SafeAreaProvider>
  );
}

