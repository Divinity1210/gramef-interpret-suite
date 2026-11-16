import { useEffect, useRef, useState, createContext, useContext, useCallback, ReactNode } from 'react';
import { View, Text, StyleSheet, Animated, Dimensions, Pressable, Platform } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useTheme } from '../theme';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

type ToastVariant = 'success' | 'error' | 'info' | 'warning';

interface ToastMessage {
    id: string;
    message: string;
    variant: ToastVariant;
    duration?: number;
}

interface ToastContextType {
    show: (message: string, variant?: ToastVariant, duration?: number) => void;
    success: (message: string) => void;
    error: (message: string) => void;
    info: (message: string) => void;
    warning: (message: string) => void;
}

const ToastContext = createContext<ToastContextType | undefined>(undefined);

export function useToast(): ToastContextType {
    const ctx = useContext(ToastContext);
    if (!ctx) throw new Error('useToast must be used within ToastProvider');
    return ctx;
}

function ToastItem({ toast, onDismiss }: { toast: ToastMessage; onDismiss: () => void }) {
    const { colors } = useTheme();
    const translateY = useRef(new Animated.Value(-100)).current;
    const opacity = useRef(new Animated.Value(0)).current;
    const scale = useRef(new Animated.Value(0.9)).current;

    const variantConfig = {
        success: { icon: 'checkmark-circle' as const, bg: '#059669', border: '#34D399' },
        error: { icon: 'alert-circle' as const, bg: '#DC2626', border: '#F87171' },
        info: { icon: 'information-circle' as const, bg: colors.primary, border: '#93C5FD' },
        warning: { icon: 'warning' as const, bg: '#D97706', border: '#FCD34D' },
    };

    const config = variantConfig[toast.variant];

    useEffect(() => {
        // Slide in
        Animated.parallel([
            Animated.spring(translateY, { toValue: 0, useNativeDriver: true, tension: 80, friction: 10 }),
            Animated.timing(opacity, { toValue: 1, duration: 200, useNativeDriver: true }),
            Animated.spring(scale, { toValue: 1, useNativeDriver: true, tension: 100, friction: 8 }),
        ]).start();

        // Auto dismiss
        const timer = setTimeout(() => {
            Animated.parallel([
                Animated.timing(translateY, { toValue: -100, duration: 250, useNativeDriver: true }),
                Animated.timing(opacity, { toValue: 0, duration: 200, useNativeDriver: true }),
            ]).start(() => onDismiss());
        }, toast.duration || 3000);

        return () => clearTimeout(timer);
    }, []);

    return (
        <Animated.View
            style={[
                styles.toastItem,
                {
                    transform: [{ translateY }, { scale }],
                    opacity,
                    backgroundColor: config.bg,
                    borderLeftColor: config.border,
                    shadowColor: config.bg,
                },
            ]}
        >
            <Pressable onPress={onDismiss} style={styles.toastContent}>
                <Ionicons name={config.icon} size={22} color="#fff" />
                <Text style={styles.toastText} numberOfLines={2}>{toast.message}</Text>
                <Ionicons name="close" size={16} color="rgba(255,255,255,0.6)" />
            </Pressable>
        </Animated.View>
    );
}

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<ToastMessage[]>([]);

    const dismiss = useCallback((id: string) => {
        setToasts(prev => prev.filter(t => t.id !== id));
    }, []);

    const show = useCallback((message: string, variant: ToastVariant = 'info', duration = 3000) => {
        const id = Date.now().toString() + Math.random().toString(36).slice(2);
        setToasts(prev => [...prev.slice(-2), { id, message, variant, duration }]);
    }, []);

    const ctx: ToastContextType = {
        show,
        success: (msg) => show(msg, 'success'),
        error: (msg) => show(msg, 'error', 4000),
        info: (msg) => show(msg, 'info'),
        warning: (msg) => show(msg, 'warning'),
    };

    return (
        <ToastContext.Provider value={ctx}>
            {children}
            <View style={styles.toastContainer} pointerEvents="box-none">
                {toasts.map(toast => (
                    <ToastItem key={toast.id} toast={toast} onDismiss={() => dismiss(toast.id)} />
                ))}
            </View>
        </ToastContext.Provider>
    );
}

const styles = StyleSheet.create({
    toastContainer: {
        position: 'absolute',
        top: Platform.OS === 'web' ? 16 : 50,
        left: 0,
        right: 0,
        alignItems: 'center',
        zIndex: 9999,
        pointerEvents: 'box-none',
    },
    toastItem: {
        width: SCREEN_WIDTH - 32,
        maxWidth: 440,
        borderRadius: 14,
        marginBottom: 8,
        borderLeftWidth: 4,
        shadowOffset: { width: 0, height: 8 },
        shadowOpacity: 0.3,
        shadowRadius: 12,
        elevation: 8,
    },
    toastContent: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingVertical: 14,
        gap: 12,
    },
    toastText: {
        flex: 1,
        color: '#fff',
        fontSize: 14,
        fontWeight: '600',
        lineHeight: 20,
    },
});
