import { router } from 'expo-router';
import { useEffect, useState, useCallback } from 'react';
import { ScrollView, View, Text, Pressable, ActivityIndicator, Platform, RefreshControl } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuthStore } from '../src/state/auth';
import { getActivities } from '../src/api/client';
import Screen from '../src/ui/components/Screen';
import PullToRefresh from '../src/ui/components/PullToRefresh';
import { useTheme, spacing, radius, shadows } from '../src/ui/theme';

// Filter chips for activity categories
const FILTERS = [
    { key: 'all', label: 'All', icon: 'list' as const },
    { key: 'completed', label: 'Completed', icon: 'checkmark-circle' as const },
    { key: 'assigned', label: 'Assigned', icon: 'calendar' as const },
    { key: 'cancelled', label: 'Cancelled', icon: 'close-circle' as const },
    { key: 'dna', label: 'DNA', icon: 'alert-circle' as const },
    { key: 'timesheet', label: 'Timesheets', icon: 'document-text' as const },
    { key: 'quote', label: 'Quotes', icon: 'cash' as const },
];

export default function ActivitiesPage() {
    const token = useAuthStore((s: any) => s.token);
    const { colors } = useTheme();

    const [activities, setActivities] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [filter, setFilter] = useState('all');

    const loadActivities = useCallback(async (pageNum = 1, append = false) => {
        if (!token) return;
        if (pageNum === 1) setLoading(true);
        else setLoadingMore(true);

        try {
            const res = await getActivities({ page: pageNum, per_page: 20 });
            const data = res.data?.data;
            const items = data?.items || [];

            if (append) {
                setActivities(prev => [...prev, ...items]);
            } else {
                setActivities(items);
            }
            setPage(data?.current_page || 1);
            setLastPage(data?.last_page || 1);
            setTotal(data?.total || 0);
        } catch (e) {
            console.error('Failed to load activities', e);
        } finally {
            setLoading(false);
            setLoadingMore(false);
        }
    }, [token]);

    useEffect(() => {
        loadActivities(1);
    }, [token]);

    const handleRefresh = useCallback(async () => {
        await loadActivities(1);
    }, [loadActivities]);

    const handleLoadMore = useCallback(async () => {
        if (page < lastPage && !loadingMore) {
            await loadActivities(page + 1, true);
        }
    }, [page, lastPage, loadingMore, loadActivities]);

    // Filter activities client-side
    const filteredActivities = filter === 'all'
        ? activities
        : activities.filter((a: any) => a.category === filter);

    const handleJobPress = (act: any) => {
        if (act.job_id && act.job_type) {
            const typePrefix = act.job_type === 'interpreter-jobs' ? 'interpreter' : 'translator';
            router.push(`/(tabs)/jobs/${typePrefix}-${act.job_id}` as any);
        }
    };

    return (
        <Screen>
            {/* Header */}
            <View style={{
                flexDirection: 'row',
                alignItems: 'center',
                paddingHorizontal: spacing.lg,
                paddingTop: spacing.md,
                paddingBottom: spacing.sm,
            }}>
                <Pressable
                    onPress={() => router.back()}
                    style={{
                        width: 40, height: 40, borderRadius: 20,
                        borderWidth: 1, borderColor: colors.border,
                        alignItems: 'center', justifyContent: 'center',
                        backgroundColor: colors.surface, marginRight: spacing.md,
                    }}
                >
                    <Ionicons name="arrow-back" size={20} color={colors.text} />
                </Pressable>
                <View style={{ flex: 1 }}>
                    <Text style={{ fontSize: 22, fontWeight: '700', color: colors.text }}>Activities</Text>
                    <Text style={{ fontSize: 12, color: colors.subtext, marginTop: 1 }}>
                        {total} total activit{total === 1 ? 'y' : 'ies'}
                    </Text>
                </View>
            </View>

            {/* Filter Chips */}
            <View style={{ height: 52, marginBottom: 6 }}>
                <ScrollView
                    horizontal
                    showsHorizontalScrollIndicator={false}
                    contentContainerStyle={{ paddingHorizontal: spacing.lg, gap: 8, alignItems: 'center', height: 52 }}
                >
                    {FILTERS.map(f => {
                        const isActive = filter === f.key;
                        const chipColors: Record<string, { bg: string; text: string; activeBg: string }> = {
                            all: { bg: colors.primary + '18', text: colors.primary, activeBg: colors.primary },
                            completed: { bg: '#28A74518', text: '#28A745', activeBg: '#28A745' },
                            assigned: { bg: '#0D6EFD18', text: '#0D6EFD', activeBg: '#0D6EFD' },
                            cancelled: { bg: '#DC354518', text: '#DC3545', activeBg: '#DC3545' },
                            dna: { bg: '#85640418', text: '#856404', activeBg: '#F0AD4E' },
                            timesheet: { bg: '#6F42C118', text: '#6F42C1', activeBg: '#6F42C1' },
                            quote: { bg: '#17A2B818', text: '#17A2B8', activeBg: '#17A2B8' },
                        };
                        const cc = chipColors[f.key] || chipColors.all;
                        return (
                            <Pressable
                                key={f.key}
                                onPress={() => setFilter(f.key)}
                                style={{
                                    flexDirection: 'row',
                                    alignItems: 'center',
                                    gap: 6,
                                    paddingHorizontal: 14,
                                    paddingVertical: 8,
                                    borderRadius: 18,
                                    backgroundColor: isActive ? cc.activeBg : cc.bg,
                                }}
                            >
                                <Ionicons name={f.icon} size={14} color={isActive ? '#fff' : cc.text} />
                                <Text style={{
                                    fontSize: 13,
                                    fontWeight: '700',
                                    color: isActive ? '#fff' : cc.text,
                                }}>{f.label}</Text>
                            </Pressable>
                        );
                    })}
                </ScrollView>
            </View>

            {/* Activities List */}
            <PullToRefresh onRefresh={handleRefresh} refreshing={loading} tintColor={colors.primary}>
                <ScrollView
                    contentContainerStyle={{ paddingHorizontal: spacing.lg, paddingBottom: spacing.xl + 60 }}
                    refreshControl={Platform.OS !== 'web' ? <RefreshControl refreshing={loading} onRefresh={handleRefresh} /> : undefined}
                >
                    {loading && activities.length === 0 ? (
                        <View style={{ alignItems: 'center', paddingVertical: 60 }}>
                            <ActivityIndicator size="large" color={colors.primary} />
                            <Text style={{ color: colors.subtext, marginTop: spacing.md }}>Loading activities...</Text>
                        </View>
                    ) : filteredActivities.length === 0 ? (
                        <View style={{ alignItems: 'center', paddingVertical: 60 }}>
                            <Ionicons name="file-tray-outline" size={48} color={colors.subtext} />
                            <Text style={{ fontSize: 16, color: colors.subtext, marginTop: spacing.md }}>
                                {filter === 'all' ? 'No activities yet' : `No ${filter} activities`}
                            </Text>
                        </View>
                    ) : (
                        <View style={{ gap: 10, marginTop: spacing.sm }}>
                            {filteredActivities.map((act: any, i: number) => (
                                <Pressable
                                    key={act.id || i}
                                    onPress={() => handleJobPress(act)}
                                    style={{
                                        backgroundColor: colors.surface,
                                        borderRadius: radius.lg,
                                        padding: spacing.md,
                                        flexDirection: 'row',
                                        alignItems: 'center',
                                        gap: spacing.md,
                                        borderWidth: 1,
                                        borderColor: colors.border,
                                        ...shadows.sm,
                                    }}
                                >
                                    {/* Icon */}
                                    <View style={{
                                        width: 44, height: 44, borderRadius: 22,
                                        backgroundColor: act.icon_color || '#E7F1FF',
                                        alignItems: 'center', justifyContent: 'center',
                                    }}>
                                        <Ionicons
                                            name={(act.icon || 'information-circle') as any}
                                            size={22}
                                            color={act.icon_color_text || '#0D6EFD'}
                                        />
                                    </View>

                                    {/* Content */}
                                    <View style={{ flex: 1 }}>
                                        <Text style={{ fontSize: 14, fontWeight: '500', color: colors.text }} numberOfLines={2}>
                                            {act.text}
                                        </Text>
                                        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 4 }}>
                                            {act.time_ago ? (
                                                <Text style={{ fontSize: 11, color: colors.subtext }}>{act.time_ago}</Text>
                                            ) : null}
                                            {act.language ? (
                                                <View style={{
                                                    backgroundColor: colors.primary + '14',
                                                    borderRadius: 8, paddingHorizontal: 6, paddingVertical: 1,
                                                }}>
                                                    <Text style={{ fontSize: 10, color: colors.primary, fontWeight: '600' }}>{act.language}</Text>
                                                </View>
                                            ) : null}
                                            {act.category ? (
                                                <View style={{
                                                    backgroundColor: act.icon_color || '#E7F1FF',
                                                    borderRadius: 8, paddingHorizontal: 6, paddingVertical: 1,
                                                }}>
                                                    <Text style={{
                                                        fontSize: 10, fontWeight: '600',
                                                        color: act.icon_color_text || '#0D6EFD',
                                                        textTransform: 'capitalize',
                                                    }}>{act.category}</Text>
                                                </View>
                                            ) : null}
                                        </View>
                                    </View>

                                    {/* Arrow */}
                                    {act.job_id ? (
                                        <Ionicons name="chevron-forward" size={18} color={colors.subtext} />
                                    ) : null}
                                </Pressable>
                            ))}

                            {/* Load More */}
                            {page < lastPage && (
                                <Pressable
                                    onPress={handleLoadMore}
                                    disabled={loadingMore}
                                    style={{
                                        paddingVertical: 14,
                                        alignItems: 'center',
                                        backgroundColor: colors.surface,
                                        borderRadius: radius.lg,
                                        borderWidth: 1,
                                        borderColor: colors.border,
                                        marginTop: spacing.sm,
                                    }}
                                >
                                    {loadingMore ? (
                                        <ActivityIndicator size="small" color={colors.primary} />
                                    ) : (
                                        <Text style={{ fontSize: 14, fontWeight: '600', color: colors.primary }}>
                                            Load More Activities
                                        </Text>
                                    )}
                                </Pressable>
                            )}
                        </View>
                    )}
                </ScrollView>
            </PullToRefresh>
        </Screen>
    );
}
