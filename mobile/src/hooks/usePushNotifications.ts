/**
 * usePushNotifications — registers push subscription on web PWA.
 * 
 * On mount:
 * 1. Registers the service worker
 * 2. Requests notification permission
 * 3. Subscribes to push via pushManager.subscribe()
 * 4. Sends the subscription to the backend API
 */
import { useEffect, useRef } from 'react';
import { Platform } from 'react-native';
import { useAuthStore } from '../state/auth';
import { registerPushSubscription } from '../api/client';

// VAPID public key — matches the server's VAPID_PUBLIC_KEY in .env
// This is the public key; safe to expose in frontend
const VAPID_PUBLIC_KEY = process.env.EXPO_PUBLIC_VAPID_PUBLIC_KEY || 'BJMg1dQdxaHsTUDo4zlfClMX7OxTViOwWnhJAfjAL8pmj8DzkO5ec0zwq35tkxqjd7DHoQVV2WIuGWe5RJcGL20';

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    const buffer = new ArrayBuffer(rawData.length);
    const outputArray = new Uint8Array(buffer);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

export function usePushNotifications() {
    const token = useAuthStore((s: any) => s.token);
    const registered = useRef(false);

    useEffect(() => {
        if (Platform.OS !== 'web' || !token || registered.current) return;

        async function registerPush() {
            try {
                // Check if service workers and push are supported
                if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                    console.log('Push notifications not supported in this browser');
                    return;
                }

                // Register service worker
                const registration = await navigator.serviceWorker.ready;

                // Check permission
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    console.log('Notification permission denied');
                    return;
                }

                // Check if already subscribed
                let subscription = await registration.pushManager.getSubscription();

                if (!subscription) {
                    // Subscribe with VAPID key
                    if (!VAPID_PUBLIC_KEY) {
                        console.log('VAPID_PUBLIC_KEY not configured — skipping push registration');
                        return;
                    }

                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY) as any,
                    });
                }

                // Send subscription to backend
                const subJson = subscription.toJSON();
                await registerPushSubscription({
                    endpoint: subJson.endpoint!,
                    p256dh_key: subJson.keys?.p256dh || '',
                    auth_key: subJson.keys?.auth || '',
                });

                registered.current = true;
                console.log('Push subscription registered successfully');
            } catch (err) {
                console.warn('Push registration failed:', err);
                // Don't break the app — push is optional
            }
        }

        registerPush();
    }, [token]);
}
