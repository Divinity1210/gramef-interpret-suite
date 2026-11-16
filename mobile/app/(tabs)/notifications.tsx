import React, { useState, useCallback, useEffect } from 'react';
import { View, Text, FlatList, Pressable, ActivityIndicator, RefreshControl, StyleSheet } from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { getNotifications, markNotificationRead, markAllNotificationsRead } from '../../src/api/client';
import Screen from '../../src/ui/components/Screen';
import { colors, spacing, radius, typography } from '../../src/ui/theme';

interface NotificationItem {
    id: string;
    type: string;
    title: string;
    body: string;
    url: string | null;
    icon: string | null;
    data: any;
    read: boolean;
    read_at: string | null;
    created_at: string;
}

export default function NotificationsScreen() {
    const [items, setItems] = useState<NotificationItem[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);

    const fetchNotifications = useCallback(async (pageNum = 1, append = false) => {
        try {
            const res = await getNotifications(pageNum, 20);
            const data = res?.data?.data || [];
            const meta = res?.data?.meta;
            if (append) {
                setItems(prev => [...prev, ...data]);
            } else {
                setItems(data);
            }
            setHasMore(meta ? meta.current_page < meta.last_page : false);
            setPage(pageNum);
        } catch {
            // silently ignore
        }
    }, []);

    useEffect(() => {
        setLoading(true);
        fetchNotifications(1).finally(() => setLoading(false));
    }, [fetchNotifications]);

    const onRefresh = useCallback(async () => {
        setRefreshing(true);
        await fetchNotifications(1);
        setRefreshing(false);
    }, [fetchNotifications]);

    const loadMore = useCallback(async () => {
        if (!hasMore || loadingMore) return;
        setLoadingMore(true);
        await fetchNotifications(page + 1, true);
        setLoadingMore(false);
    }, [hasMore, loadingMore, page, fetchNotifications]);

    const handleMarkAllRead = useCallback(async () => {
        try {
            await markAllNotificationsRead();
            setItems(prev => prev.map(n => ({ ...n, read: true })));
        } catch {
            // silently ignore
        }
    }, []);

    const handleNotificationPress = useCallback(async (item: NotificationItem) => {
        // Mark as read
        if (!item.read) {
            try {
                await markNotificationRead(item.id);
                setItems(prev => prev.map(n => n.id === item.id ? { ...n, read: true } : n));
            } catch {
                // ignore
            }
        }

        // Navigate based on notification type/url
        const extra = item.data;
        if (item.type === 'job_assigned' && extra?.jobId) {
            router.push(`/(tabs)/jobs/${extra.jobType || 'interpreter'}-${extra.jobId}` as any);
        } else if ((item.type === 'quote_accepted' || item.type === 'quote_rejected') && extra?.jobId) {
            router.push(`/(tabs)/jobs/interpreter-${extra.jobId}` as any);
        } else if (item.url) {
            router.push(item.url as any);
        }
    }, []);

    const formatTime = (dateStr: string) => {
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        const diffHrs = Math.floor(diffMins / 60);
        if (diffHrs < 24) return `${diffHrs}h ago`;
        const diffDays = Math.floor(diffHrs / 24);
        if (diffDays < 7) return `${diffDays}d ago`;
        return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
    };

    const getIcon = (type: string) => {
        switch (type) {
            case 'job_assigned': return { name: 'briefcase', color: '#3b82f6' };
            case 'quote_accepted': return { name: 'checkmark-circle', color: '#22c55e' };
            case 'quote_rejected': return { name: 'close-circle', color: '#ef4444' };
            default: return { name: 'notifications', color: colors.primary };
        }
    };

    const renderItem = ({ item }: { item: NotificationItem }) => {
        const icon = getIcon(item.type);
        return (
            <Pressable
                onPress={() => handleNotificationPress(item)}
                style={[
                    styles.notificationItem,
                    !item.read && styles.unreadItem,
                ]}
            >
                <View style={[styles.iconCircle, { backgroundColor: icon.color + '20' }]}>
                    <Ionicons name={icon.name as any} size={20} color={icon.color} />
                </View>
                <View style={{ flex: 1, marginLeft: 12 }}>
                    <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                        <Text style={[styles.notifTitle, !item.read && { fontWeight: '700' }]} numberOfLines={1}>
                            {item.title}
                        </Text>
                        <Text style={styles.notifTime}>{formatTime(item.created_at)}</Text>
                    </View>
                    <Text style={styles.notifBody} numberOfLines={2}>{item.body}</Text>
                </View>
                {!item.read && <View style={styles.unreadDot} />}
            </Pressable>
        );
    };

    if (loading) {
        return (
            <Screen>
                <View style={styles.headerRow}>
                    <Pressable onPress={() => router.back()} style={styles.backBtn}>
                        <Ionicons name="arrow-back" size={22} color={colors.text} />
                    </Pressable>
                    <Text style={typography.title}>Notifications</Text>
                    <View style={{ width: 36 }} />
                </View>
                <View style={styles.center}>
                    <ActivityIndicator size="large" color={colors.primary} />
                </View>
            </Screen>
        );
    }

    return (
        <Screen>
            <View style={styles.headerRow}>
                <Pressable onPress={() => router.back()} style={styles.backBtn}>
                    <Ionicons name="arrow-back" size={22} color={colors.text} />
                </Pressable>
                <Text style={typography.title}>Notifications</Text>
                {items.some(n => !n.read) ? (
                    <Pressable onPress={handleMarkAllRead} style={styles.markAllBtn}>
                        <Text style={{ color: colors.primary, fontSize: 13, fontWeight: '600' }}>Mark all read</Text>
                    </Pressable>
                ) : (
                    <View style={{ width: 36 }} />
                )}
            </View>

            {items.length === 0 ? (
                <View style={styles.center}>
                    <Ionicons name="notifications-off-outline" size={48} color={colors.subtext} />
                    <Text style={[styles.emptyText, { marginTop: 12 }]}>No notifications yet</Text>
                    <Text style={styles.emptySubtext}>You'll be notified when you receive new jobs, quote updates, and more.</Text>
                </View>
            ) : (
                <FlatList
                    data={items}
                    keyExtractor={(item) => item.id}
                    renderItem={renderItem}
                    contentContainerStyle={{ paddingHorizontal: spacing.lg, paddingBottom: 40 }}
                    ItemSeparatorComponent={() => <View style={{ height: 1, backgroundColor: colors.border, marginLeft: 52 }} />}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
                    onEndReached={loadMore}
                    onEndReachedThreshold={0.3}
                    ListFooterComponent={loadingMore ? <ActivityIndicator size="small" color={colors.primary} style={{ marginVertical: 16 }} /> : null}
                />
            )}
        </Screen>
    );
}

const styles = StyleSheet.create({
    headerRow: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingHorizontal: spacing.lg,
        paddingVertical: spacing.md,
        borderBottomWidth: 1,
        borderColor: colors.border,
        backgroundColor: colors.surface,
        borderRadius: 16,
        margin: spacing.lg,
        shadowColor: '#000',
        shadowOpacity: 0.06,
        shadowRadius: 10,
        shadowOffset: { width: 0, height: 4 },
        elevation: 2,
    },
    backBtn: {
        width: 36,
        height: 36,
        alignItems: 'center',
        justifyContent: 'center',
    },
    markAllBtn: {
        paddingHorizontal: 8,
        paddingVertical: 4,
    },
    center: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
        padding: spacing.xl,
    },
    notificationItem: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: 14,
        paddingRight: 8,
    },
    unreadItem: {
        backgroundColor: colors.primary + '08',
    },
    iconCircle: {
        width: 40,
        height: 40,
        borderRadius: 20,
        alignItems: 'center',
        justifyContent: 'center',
    },
    notifTitle: {
        fontSize: 14,
        fontWeight: '600',
        color: colors.text,
        flex: 1,
        marginRight: 8,
    },
    notifBody: {
        fontSize: 13,
        color: colors.subtext,
        marginTop: 2,
        lineHeight: 18,
    },
    notifTime: {
        fontSize: 11,
        color: colors.subtext,
        marginLeft: 8,
    },
    unreadDot: {
        width: 8,
        height: 8,
        borderRadius: 4,
        backgroundColor: colors.primary,
        marginLeft: 8,
    },
    emptyText: {
        fontSize: 16,
        fontWeight: '600',
        color: colors.text,
        textAlign: 'center',
    },
    emptySubtext: {
        fontSize: 13,
        color: colors.subtext,
        textAlign: 'center',
        marginTop: 8,
        lineHeight: 20,
        paddingHorizontal: 40,
    },
});
