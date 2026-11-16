/**
 * PullToRefresh — custom pull-to-refresh that works on web/PWA.
 * React Native Web's RefreshControl is a no-op on web, so this
 * component uses touch events and CSS transforms to implement
 * the pull-to-refresh gesture natively in the browser.
 */
import React, { useRef, useState, useCallback, type PropsWithChildren } from 'react';
import { View, Text, Animated, ActivityIndicator, StyleSheet, Platform } from 'react-native';

const THRESHOLD = 80; // px to pull before triggering refresh
const MAX_PULL = 120; // max pull distance
const INDICATOR_SIZE = 36;

interface PullToRefreshProps extends PropsWithChildren {
    onRefresh: () => Promise<void> | void;
    refreshing?: boolean;
    tintColor?: string;
    backgroundColor?: string;
}

export default function PullToRefresh({
    children,
    onRefresh,
    refreshing = false,
    tintColor = '#0d6efd',
    backgroundColor = 'transparent',
}: PullToRefreshProps) {
    // On native, just render children — RefreshControl handles it
    if (Platform.OS !== 'web') {
        return <>{children}</>;
    }

    const [pullDistance, setPullDistance] = useState(0);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const startY = useRef(0);
    const isPulling = useRef(false);
    const containerRef = useRef<View>(null);

    const handleTouchStart = useCallback((e: any) => {
        // Only start pull if scrolled to top
        const touch = e.nativeEvent?.touches?.[0] || e.touches?.[0];
        if (!touch) return;

        // Check if any scrollable parent is scrolled down
        let el = e.target as HTMLElement;
        while (el && el !== document.body) {
            if (el.scrollTop > 0) return; // Not at top — don't pull
            el = el.parentElement as HTMLElement;
        }

        startY.current = touch.clientY || touch.pageY;
        isPulling.current = true;
    }, []);

    const handleTouchMove = useCallback((e: any) => {
        if (!isPulling.current || isRefreshing) return;

        const touch = e.nativeEvent?.touches?.[0] || e.touches?.[0];
        if (!touch) return;

        const currentY = touch.clientY || touch.pageY;
        const diff = currentY - startY.current;

        if (diff > 0) {
            // Apply resistance curve
            const distance = Math.min(diff * 0.5, MAX_PULL);
            setPullDistance(distance);

            // Prevent page scroll while pulling
            if (diff > 10) {
                e.preventDefault?.();
            }
        } else {
            setPullDistance(0);
            isPulling.current = false;
        }
    }, [isRefreshing]);

    const handleTouchEnd = useCallback(async () => {
        if (!isPulling.current) return;
        isPulling.current = false;

        if (pullDistance >= THRESHOLD && !isRefreshing) {
            setIsRefreshing(true);
            setPullDistance(INDICATOR_SIZE + 12); // Keep indicator visible

            try {
                await onRefresh();
            } catch (e) {
                // silently handle
            }

            // Small delay for visual feedback
            await new Promise(resolve => setTimeout(resolve, 300));
            setIsRefreshing(false);
            setPullDistance(0);
        } else {
            setPullDistance(0);
        }
    }, [pullDistance, isRefreshing, onRefresh]);

    // Sync external refreshing prop
    React.useEffect(() => {
        if (!refreshing && isRefreshing) {
            // External refreshing ended
            setTimeout(() => {
                setIsRefreshing(false);
                setPullDistance(0);
            }, 300);
        }
    }, [refreshing]);

    const progress = Math.min(pullDistance / THRESHOLD, 1);
    const rotation = `${progress * 360}deg`;

    return (
        <View
            ref={containerRef}
            style={{ flex: 1, backgroundColor }}
            onTouchStart={handleTouchStart}
            onTouchMove={handleTouchMove}
            onTouchEnd={handleTouchEnd}
        >
            {/* Pull indicator */}
            <View
                style={[
                    styles.indicatorContainer,
                    {
                        height: pullDistance,
                        opacity: progress,
                    },
                ]}
            >
                {isRefreshing ? (
                    <ActivityIndicator size="small" color={tintColor} />
                ) : (
                    <View
                        style={[
                            styles.spinner,
                            {
                                borderColor: tintColor + '30',
                                borderTopColor: tintColor,
                                transform: [{ rotate: rotation }],
                            },
                        ]}
                    />
                )}
                {pullDistance > 10 && !isRefreshing && (
                    <Text style={[styles.hintText, { color: tintColor }]}>
                        {pullDistance >= THRESHOLD ? 'Release to refresh' : 'Pull to refresh'}
                    </Text>
                )}
            </View>

            {/* Content shifted down by pull distance */}
            <View style={{ flex: 1, transform: [{ translateY: 0 }] }}>
                {children}
            </View>
        </View>
    );
}

const styles = StyleSheet.create({
    indicatorContainer: {
        alignItems: 'center',
        justifyContent: 'center',
        overflow: 'hidden',
        gap: 6,
    },
    spinner: {
        width: 24,
        height: 24,
        borderRadius: 12,
        borderWidth: 2.5,
    },
    hintText: {
        fontSize: 11,
        fontWeight: '600',
    },
});
