import React, { useEffect, useRef } from 'react';
import { Platform } from 'react-native';
import { router } from 'expo-router';
import * as Notifications from 'expo-notifications';
import {
    registerForPushNotifications,
    addNotificationReceivedListener,
    addNotificationResponseListener,
    getLastNotificationResponse,
    clearBadge,
} from '../services/notifications';
import { useAuthStore } from '../state/auth';
import { showNotificationToast } from '../components/NotificationToast';
import { registerPushToken } from '../api/client';

// Web push imports (only used on web)
let webPush: typeof import('../services/webPush') | null = null;
if (Platform.OS === 'web') {
    try {
        webPush = require('../services/webPush');
    } catch { }
}

interface NotificationProviderProps {
    children: React.ReactNode;
}

/**
 * NotificationProvider handles:
 * 1. Native push notification registration & listeners (iOS/Android)
 * 2. Web push notification registration (PWA)
 * 3. In-app toast notifications when app is foregrounded
 * 4. Deep linking from notification taps
 */
export function NotificationProvider({ children }: NotificationProviderProps) {
    const token = useAuthStore((s: any) => s.token);
    const notificationListener = useRef<Notifications.Subscription>();
    const responseListener = useRef<Notifications.Subscription>();

    useEffect(() => {
        // Only set up notifications if user is logged in
        if (!token) return;

        // === NATIVE PUSH (iOS/Android) ===
        registerForPushNotifications().then((pushToken) => {
            if (pushToken) {
                console.log('Push token:', pushToken);
                // Send token to backend for server-side notifications
                const platform = Platform.OS === 'ios' ? 'ios' : 'android';
                registerPushToken(pushToken, platform).catch((err) => {
                    console.warn('Failed to register push token with server:', err.message);
                });
            }
        });

        // === WEB PUSH (PWA) ===
        if (Platform.OS === 'web' && webPush) {
            setupWebPush();
        }

        // Handle notifications received while app is foregrounded
        const notifListener = addNotificationReceivedListener((notification) => {
            console.log('Notification received (foreground):', notification);

            // Show in-app toast for foreground notifications
            const content = notification.request.content;
            const data = content.data as any;

            showNotificationToast({
                title: content.title || 'Notification',
                body: content.body || '',
                type: mapNotificationType(data?.type),
                onPress: () => {
                    // Navigate when toast is tapped
                    navigateFromNotification(data);
                },
                duration: 6000,
            });
        });
        if (notifListener) {
            notificationListener.current = notifListener;
        }

        // Handle notification taps (user interaction)
        const respListener = addNotificationResponseListener((response) => {
            handleNotificationResponse(response);
        });
        if (respListener) {
            responseListener.current = respListener;
        }

        // Check if app was opened from a notification
        getLastNotificationResponse().then((response) => {
            if (response) {
                handleNotificationResponse(response);
            }
        });

        // Clear badge when app is opened
        clearBadge();

        return () => {
            if (notificationListener.current) {
                Notifications.removeNotificationSubscription(notificationListener.current);
            }
            if (responseListener.current) {
                Notifications.removeNotificationSubscription(responseListener.current);
            }
        };
    }, [token]);

    /**
     * Set up web push notifications for PWA
     */
    async function setupWebPush() {
        if (!webPush) return;

        try {
            const success = await webPush.initializeWebPush();
            // Silently handle — push is optional
            if (success) {
                console.log('Web push notifications initialized successfully');
            }
        } catch (err) {
            // Silently ignore — push is optional
        }
    }

    /**
     * Map backend notification types to toast icon types
     */
    function mapNotificationType(type?: string): 'info' | 'success' | 'warning' | 'job' | 'message' | 'payment' {
        switch (type) {
            case 'new_job':
            case 'new_job_match':
            case 'job_update':
            case 'job_updated':
            case 'job_assigned':
            case 'job_cancelled':
                return 'job';
            case 'message':
            case 'new_message':
            case 'chat_message':
                return 'message';
            case 'payment':
            case 'invoice':
            case 'payment_received':
                return 'payment';
            case 'reminder':
            case 'job_reminder':
                return 'warning';
            case 'completed':
            case 'job_completed':
                return 'success';
            default:
                return 'info';
        }
    }

    /**
     * Navigate to the relevant screen based on notification data
     */
    function navigateFromNotification(data: any) {
        if (!data?.type) return;

        switch (data.type) {
            case 'new_job':
            case 'new_job_match':
            case 'job_update':
            case 'job_updated':
            case 'job_assigned':
            case 'job_completed':
            case 'job_cancelled':
                if (data.jobId || data.extra?.jobId) {
                    const jobId = data.jobId || data.extra?.jobId;
                    const jobType = data.jobType || data.extra?.jobType || 'interpreter';
                    router.push(`/(tabs)/jobs/${jobType}-${jobId}`);
                } else {
                    router.push('/(tabs)/jobs');
                }
                break;

            case 'message':
            case 'new_message':
            case 'chat_message':
                if (data.conversationId || data.conversation_id) {
                    router.push(`/chat/${data.conversationId || data.conversation_id}`);
                } else {
                    router.push('/(tabs)/messages');
                }
                break;

            case 'payment':
            case 'invoice':
            case 'payment_received':
                router.push('/finances/agent-invoices');
                break;

            case 'reminder':
            case 'job_reminder':
                if (data.jobId || data.extra?.jobId) {
                    const jobId = data.jobId || data.extra?.jobId;
                    const jobType = data.jobType || data.extra?.jobType || 'interpreter';
                    router.push(`/(tabs)/jobs/${jobType}-${jobId}`);
                }
                break;

            default:
                router.push('/(tabs)');
        }
    }

    /**
     * Handle notification tap – navigate to relevant screen
     */
    function handleNotificationResponse(response: Notifications.NotificationResponse) {
        const data = response.notification.request.content.data;
        navigateFromNotification(data);
    }

    return <>{children}</>;
}
