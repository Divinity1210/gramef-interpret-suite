import React, { useEffect, useRef, useState, useCallback } from 'react';
import {
    View,
    Text,
    Animated,
    Pressable,
    StyleSheet,
    Platform,
    Dimensions,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useTheme, spacing, radius } from '../ui/theme';

export type NotificationToastData = {
    id: string;
    title: string;
    body: string;
    type?: 'info' | 'success' | 'warning' | 'job' | 'message' | 'payment';
    onPress?: () => void;
    duration?: number; // ms, default 5000
};

type ToastItem = NotificationToastData & {
    animValue: Animated.Value;
    timer: ReturnType<typeof setTimeout> | null;
};

// Singleton event emitter for triggering toasts from anywhere
type ToastListener = (toast: NotificationToastData) => void;
const listeners: Set<ToastListener> = new Set();

export function showNotificationToast(toast: Omit<NotificationToastData, 'id'>) {
    const fullToast: NotificationToastData = {
        ...toast,
        id: `toast-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    };
    listeners.forEach((fn) => fn(fullToast));
}

const TOAST_ICON_MAP: Record<string, { name: any; color: string; bg: string }> = {
    info: { name: 'information-circle', color: '#3b82f6', bg: '#3b82f615' },
    success: { name: 'checkmark-circle', color: '#22c55e', bg: '#22c55e15' },
    warning: { name: 'warning', color: '#f59e0b', bg: '#f59e0b15' },
    job: { name: 'briefcase', color: '#8b5cf6', bg: '#8b5cf615' },
    message: { name: 'chatbubble-ellipses', color: '#06b6d4', bg: '#06b6d415' },
    payment: { name: 'card', color: '#10b981', bg: '#10b98115' },
};

/**
 * Renders floating notification toasts at the top of the screen.
 * Place this component at the root layout level.
 */
export function NotificationToastContainer() {
    const { colors } = useTheme();
    const [toasts, setToasts] = useState<ToastItem[]>([]);
    const { width: screenWidth } = Dimensions.get('window');

    const dismiss = useCallback((id: string) => {
        setToasts((prev) => {
            const toast = prev.find((t) => t.id === id);
            if (toast) {
                // Animate out
                Animated.timing(toast.animValue, {
                    toValue: 0,
                    duration: 250,
                    useNativeDriver: true,
                }).start(() => {
                    setToasts((p) => p.filter((t) => t.id !== id));
                });
                if (toast.timer) clearTimeout(toast.timer);
            }
            return prev;
        });
    }, []);

    const addToast = useCallback((data: NotificationToastData) => {
        const animValue = new Animated.Value(0);
        const duration = data.duration ?? 5000;
        const timer = setTimeout(() => dismiss(data.id), duration);

        const item: ToastItem = { ...data, animValue, timer };

        setToasts((prev) => {
            // Max 3 toasts visible
            const trimmed = prev.length >= 3 ? prev.slice(1) : prev;
            return [...trimmed, item];
        });

        // Animate in
        Animated.spring(animValue, {
            toValue: 1,
            friction: 8,
            tension: 40,
            useNativeDriver: true,
        }).start();
    }, [dismiss]);

    useEffect(() => {
        listeners.add(addToast);
        return () => { listeners.delete(addToast); };
    }, [addToast]);

    if (toasts.length === 0) return null;

    const styles = createStyles(colors, screenWidth);

    return (
        <View style={styles.container} pointerEvents="box-none">
            {toasts.map((toast, idx) => {
                const icon = TOAST_ICON_MAP[toast.type || 'info'] || TOAST_ICON_MAP.info;
                const translateY = toast.animValue.interpolate({
                    inputRange: [0, 1],
                    outputRange: [-100, 0],
                });
                const opacity = toast.animValue.interpolate({
                    inputRange: [0, 0.5, 1],
                    outputRange: [0, 0.8, 1],
                });

                return (
                    <Animated.View
                        key={toast.id}
                        style={[
                            styles.toast,
                            {
                                transform: [{ translateY }],
                                opacity,
                                marginTop: idx > 0 ? spacing.xs : 0,
                            },
                        ]}
                    >
                        <Pressable
                            style={styles.toastInner}
                            onPress={() => {
                                if (toast.onPress) toast.onPress();
                                dismiss(toast.id);
                            }}
                        >
                            {/* Icon */}
                            <View style={[styles.iconCircle, { backgroundColor: icon.bg }]}>
                                <Ionicons name={icon.name} size={20} color={icon.color} />
                            </View>

                            {/* Content */}
                            <View style={styles.textContainer}>
                                <Text style={styles.title} numberOfLines={1}>{toast.title}</Text>
                                <Text style={styles.body} numberOfLines={2}>{toast.body}</Text>
                            </View>

                            {/* Dismiss */}
                            <Pressable
                                style={styles.dismissButton}
                                onPress={() => dismiss(toast.id)}
                                hitSlop={8}
                            >
                                <Ionicons name="close" size={16} color={colors.subtext} />
                            </Pressable>
                        </Pressable>

                        {/* Progress bar */}
                        <Animated.View
                            style={[
                                styles.progressBar,
                                { backgroundColor: icon.color },
                            ]}
                        />
                    </Animated.View>
                );
            })}
        </View>
    );
}

const createStyles = (colors: any, screenWidth: number) => StyleSheet.create({
    container: {
        position: 'absolute',
        top: Platform.OS === 'ios' ? 50 : 24,
        left: 0,
        right: 0,
        alignItems: 'center',
        zIndex: 99999,
        paddingHorizontal: spacing.md,
    },
    toast: {
        width: Math.min(screenWidth - spacing.md * 2, 420),
        backgroundColor: colors.surface,
        borderRadius: radius.lg,
        borderWidth: 1,
        borderColor: colors.border,
        shadowColor: '#000',
        shadowOpacity: 0.15,
        shadowRadius: 20,
        shadowOffset: { width: 0, height: 8 },
        elevation: 8,
        overflow: 'hidden',
    },
    toastInner: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: spacing.md,
        gap: spacing.sm,
    },
    iconCircle: {
        width: 36,
        height: 36,
        borderRadius: 18,
        alignItems: 'center',
        justifyContent: 'center',
    },
    textContainer: {
        flex: 1,
    },
    title: {
        fontSize: 14,
        fontWeight: '700',
        color: colors.text,
    },
    body: {
        fontSize: 13,
        color: colors.subtext,
        marginTop: 2,
        lineHeight: 18,
    },
    dismissButton: {
        width: 28,
        height: 28,
        borderRadius: 14,
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: colors.bg,
    },
    progressBar: {
        height: 3,
        borderBottomLeftRadius: radius.lg,
        borderBottomRightRadius: radius.lg,
    },
});
