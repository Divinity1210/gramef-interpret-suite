import { View, Text, StyleSheet, FlatList, Pressable, RefreshControl, ActivityIndicator, Alert, Platform } from 'react-native';
import { useState, useEffect, useMemo } from 'react';
import { router } from 'expo-router';
import { useTheme, spacing, radius } from '../../../src/ui/theme';
import { exportTimesheets, getTimesheets } from '../../../src/api/client';
import { Ionicons } from '@expo/vector-icons';
import Input from '../../../src/ui/components/Input';
import * as FileSystem from 'expo-file-system';
import * as Sharing from 'expo-sharing';


type TabType = 'all' | 'pending' | 'signed' | 'completed';

interface Timesheet {
    id: number;
    job_id: number;
    status: string;
    agent_start_time?: string;
    agent_end_time?: string;
    duration_hours?: number;
    duration_minutes?: number;
    hours?: number;
    date?: string;
    project?: string;
    task?: string;
    description?: string;
    employee?: { id: number; name: string } | null;
    created_at: string;
    job?: {
        id: number;
        client_reference?: string;
        appointment_date?: string;
        to_language?: { name: string };
    };
    expenses_count?: number;
    expenses_total?: number;
}

export default function TimesheetsListScreen() {
    const { colors } = useTheme();
    const styles = useMemo(() => createStyles(colors), [colors]);
    const [activeTab, setActiveTab] = useState<TabType>('all');
    const [q, setQ] = useState('');
    const [clientName, setClientName] = useState('');
    const [employeeName, setEmployeeName] = useState('');
    const [ref, setRef] = useState('');
    const [timesheets, setTimesheets] = useState<Timesheet[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);
    const [exporting, setExporting] = useState<'csv' | 'pdf' | null>(null);
    useEffect(() => {
        const t = setTimeout(() => {
            loadTimesheets({ reset: true });
        }, 350);
        return () => clearTimeout(t);
    }, [activeTab, q, clientName, employeeName, ref]);

    const blobToBase64 = (blob: Blob): Promise<string> => {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onerror = reject;
            reader.onload = () => {
                const dataUrl = reader.result as string;
                const base64 = dataUrl.split(',')[1];
                resolve(base64);
            };
            reader.readAsDataURL(blob);
        });
    };

    const currentParams = useMemo(() => {
        const params: Record<string, any> = {
            page: 1,
            per_page: activeTab === 'completed' ? 50 : 20,
        };

        if (activeTab !== 'all') {
            params.status = activeTab;
        }

        const qTrim = q.trim();
        if (qTrim) params.q = qTrim;

        const clientTrim = clientName.trim();
        if (clientTrim) params.client_name = clientTrim;

        const employeeTrim = employeeName.trim();
        if (employeeTrim) params.employee_name = employeeTrim;

        const refTrim = ref.trim();
        if (refTrim) params.ref = refTrim;

        return params;
    }, [activeTab, q, clientName, employeeName, ref]);

    const loadTimesheets = async ({ reset }: { reset?: boolean } = {}) => {
        try {
            if (reset) {
                setLoading(true);
                setPage(1);
                setHasMore(false);
            } else {
                setLoadingMore(true);
            }

            const nextPage = reset ? 1 : page + 1;
            const response = await getTimesheets({
                ...(currentParams as any),
                page: nextPage,
            });
            const rawData = response.data?.data;

            const nextItems: Timesheet[] = Array.isArray(rawData)
                ? rawData
                : (Array.isArray(rawData?.data) ? rawData.data : []);

            const currentPage = Number(rawData?.current_page || nextPage);
            const lastPage = Number(rawData?.last_page || currentPage);

            setTimesheets((prev) => {
                if (reset) return nextItems;
                const seen = new Set(prev.map((t) => t.id));
                const merged = [...prev];
                for (const item of nextItems) {
                    if (!seen.has(item.id)) merged.push(item);
                }
                return merged;
            });

            setPage(currentPage);
            setHasMore(currentPage < lastPage);
        } catch (error) {
            console.error('Error loading timesheets:', error);
        } finally {
            setLoading(false);
            setRefreshing(false);
            setLoadingMore(false);
        }
    };

    const handleRefresh = () => {
        setRefreshing(true);
        loadTimesheets({ reset: true });
    };

    const handleEndReached = () => {
        if (loading || loadingMore || refreshing || !hasMore) return;
        loadTimesheets({ reset: false });
    };

    const downloadBlob = (data: any, filename: string) => {
        const g: any = globalThis as any;
        if (!g?.URL || !g?.document) {
            Alert.alert('Saved', filename);
            return;
        }
        const url = g.URL.createObjectURL(data);
        const a = g.document.createElement('a');
        a.href = url;
        a.download = filename;
        g.document.body.appendChild(a);
        a.click();
        a.remove();
        g.URL.revokeObjectURL(url);
    };

    const handleExport = async (format: 'csv' | 'pdf') => {
        try {
            setExporting(format);

            const response = await exportTimesheets({
                ...(currentParams as any),
                format,
                limit: 5000,
            });

            const filename = `timesheets-${new Date().toISOString().slice(0, 10)}.${format}`;

            if (Platform.OS === 'web') {
                downloadBlob(response.data, filename);
                return;
            }

            const fileUri = FileSystem.documentDirectory + filename;
            const base64 = await blobToBase64(response.data);
            await FileSystem.writeAsStringAsync(fileUri, base64, {
                encoding: FileSystem.EncodingType.Base64,
            });

            const canShare = await Sharing.isAvailableAsync();
            if (canShare) {
                await Sharing.shareAsync(fileUri);
            } else {
                Alert.alert('Saved', `${filename} saved`);
            }
        } catch (error) {
            console.error('Error exporting timesheets:', error);
            Alert.alert('Error', 'Failed to export timesheets');
        } finally {
            setExporting(null);
        }
    };

    const handleTimesheetPress = (timesheet: Timesheet) => {
        router.push(`/timesheets/${timesheet.id}`);
    };

    const getStatusColor = (status: string): string => {
        switch (status.toLowerCase()) {
            case 'pending': return '#f59e0b';
            case 'signed': return colors.primary;
            case 'completed': return '#22c55e';
            default: return colors.subtext;
        }
    };

    const getStatusTextColor = (status: string): string => {
        return ['signed', 'completed'].includes(status.toLowerCase()) ? '#fff' : colors.text;
    };

    const formatDate = (dateStr?: string): string => {
        if (!dateStr) return 'N/A';
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-GB', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            });
        } catch {
            return dateStr;
        }
    };

    const formatTime = (time: string): string => {
        if (!time) return '';
        try {
            const [hours, minutes] = time.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 || 12;
            return `${displayHour}:${minutes} ${ampm}`;
        } catch {
            return time;
        }
    };

    const renderTimesheetCard = ({ item }: { item: Timesheet }) => (
        <Pressable
            style={styles.card}
            onPress={() => handleTimesheetPress(item)}
        >
            <View style={styles.cardHeader}>
                <View style={styles.cardHeaderLeft}>
                    <Text style={styles.jobReference}>{item.project || item.job?.client_reference || `Job #${item.job_id}`}</Text>
                    <Text style={styles.cardSubtitle}>{item.task || item.job?.to_language?.name || ''}</Text>
                </View>
                <View style={[styles.statusBadge, { backgroundColor: getStatusColor(item.status || 'pending') }]}>
                    <Text style={[styles.statusText, { color: getStatusTextColor(item.status || 'pending') }]}>
                        {(item.status || 'pending').charAt(0).toUpperCase() + (item.status || 'pending').slice(1)}
                    </Text>
                </View>
            </View>

            <View style={styles.cardDetails}>
                <View style={styles.detailRow}>
                    <Ionicons name="calendar-outline" size={16} color={colors.subtext} />
                    <Text style={styles.detailText}>
                        {formatDate(item.date || item.job?.appointment_date)}
                    </Text>
                </View>

                <View style={styles.detailRow}>
                    <Ionicons name="time-outline" size={16} color={colors.subtext} />
                    <Text style={styles.detailText}>
                        {item.agent_start_time && item.agent_end_time ? `${formatTime(item.agent_start_time)} - ${formatTime(item.agent_end_time)}` : ''}
                    </Text>
                </View>

                <View style={styles.detailRow}>
                    <Ionicons name="hourglass-outline" size={16} color={colors.subtext} />
                    <Text style={styles.detailText}>
                        {typeof item.hours === 'number'
                            ? `${item.hours}h`
                            : `${item.duration_hours || 0}h ${item.duration_minutes || 0}m`}
                    </Text>
                </View>

                {item.employee?.name ? (
                    <View style={styles.detailRow}>
                        <Ionicons name="person-outline" size={16} color={colors.subtext} />
                        <Text style={styles.detailText}>{item.employee.name}</Text>
                    </View>
                ) : null}

                {item.description ? (
                    <View style={styles.detailRow}>
                        <Ionicons name="information-circle-outline" size={16} color={colors.subtext} />
                        <Text style={styles.detailText}>{item.description}</Text>
                    </View>
                ) : null}

                {item.expenses_count && item.expenses_count > 0 && (
                    <View style={styles.detailRow}>
                        <Ionicons name="receipt-outline" size={16} color={colors.subtext} />
                        <Text style={styles.detailText}>
                            {item.expenses_count} expense{item.expenses_count !== 1 ? 's' : ''} • £{item.expenses_total?.toFixed(2)}
                        </Text>
                    </View>
                )}
            </View>

            <View style={styles.cardFooter}>
                <Text style={styles.viewDetails}>View Details →</Text>
            </View>
        </Pressable>
    );

    return (
        <View style={styles.container}>
            {/* Header */}
            <View style={styles.header}>
                <View style={styles.headerRow}>
                    <Text style={styles.headerTitle}>Timesheets</Text>
                    <View style={styles.headerActions}>
                        <Pressable style={styles.iconButton} onPress={() => handleExport('csv')} disabled={!!exporting}>
                            {exporting === 'csv' ? (
                                <ActivityIndicator size="small" color={colors.primary} />
                            ) : (
                                <Ionicons name="download-outline" size={20} color={colors.primary} />
                            )}
                        </Pressable>
                        <Pressable style={styles.iconButton} onPress={() => handleExport('pdf')} disabled={!!exporting}>
                            {exporting === 'pdf' ? (
                                <ActivityIndicator size="small" color={colors.primary} />
                            ) : (
                                <Ionicons name="document-outline" size={20} color={colors.primary} />
                            )}
                        </Pressable>
                    </View>
                </View>
            </View>

            {/* Tabs */}
            <View style={styles.tabs}>
                <Pressable
                    style={[styles.tab, activeTab === 'all' && styles.tabActive]}
                    onPress={() => setActiveTab('all')}
                >
                    <Text style={[styles.tabText, activeTab === 'all' && styles.tabTextActive]}>All</Text>
                </Pressable>
                <Pressable
                    style={[styles.tab, activeTab === 'pending' && styles.tabActive]}
                    onPress={() => setActiveTab('pending')}
                >
                    <Text style={[styles.tabText, activeTab === 'pending' && styles.tabTextActive]}>
                        Pending
                    </Text>
                </Pressable>
                <Pressable
                    style={[styles.tab, activeTab === 'signed' && styles.tabActive]}
                    onPress={() => setActiveTab('signed')}
                >
                    <Text style={[styles.tabText, activeTab === 'signed' && styles.tabTextActive]}>
                        Signed
                    </Text>
                </Pressable>
                <Pressable
                    style={[styles.tab, activeTab === 'completed' && styles.tabActive]}
                    onPress={() => setActiveTab('completed')}
                >
                    <Text style={[styles.tabText, activeTab === 'completed' && styles.tabTextActive]}>
                        Completed
                    </Text>
                </Pressable>
            </View>

            <View style={styles.filters}>
                <Input
                    value={q}
                    onChangeText={setQ}
                    placeholder="Search"
                    returnKeyType="search"
                />
                <Input
                    value={clientName}
                    onChangeText={setClientName}
                    placeholder="Client name"
                    returnKeyType="search"
                />
                <Input
                    value={employeeName}
                    onChangeText={setEmployeeName}
                    placeholder="Agent name"
                    returnKeyType="search"
                />
                <Input
                    value={ref}
                    onChangeText={setRef}
                    placeholder="Job ID (Ref)"
                    keyboardType="number-pad"
                    returnKeyType="search"
                />
            </View>

            {/* List */}
            {loading && timesheets.length === 0 ? (
                <View style={styles.loadingContainer}>
                    <ActivityIndicator size="large" color={colors.primary} />
                </View>
            ) : (
                <FlatList
                    data={timesheets}
                    renderItem={renderTimesheetCard}
                    keyExtractor={(item) => item.id.toString()}
                    contentContainerStyle={styles.listContent}
                    refreshControl={
                        <RefreshControl
                            refreshing={refreshing}
                            onRefresh={handleRefresh}
                            tintColor={colors.primary}
                        />
                    }
                    onEndReached={handleEndReached}
                    onEndReachedThreshold={0.2}
                    ListFooterComponent={
                        loadingMore ? (
                            <View style={{ paddingVertical: spacing.lg }}>
                                <ActivityIndicator color={colors.primary} />
                            </View>
                        ) : null
                    }
                    ListEmptyComponent={
                        <View style={styles.emptyContainer}>
                            <Ionicons name="document-text-outline" size={64} color={colors.subtext} />
                            <Text style={styles.emptyTitle}>No timesheets found</Text>
                            <Text style={styles.emptySubtitle}>
                                {activeTab === 'all'
                                    ? 'No timesheets yet. Complete a job to create one.'
                                    : `No ${activeTab} timesheets found.`}
                            </Text>
                        </View>
                    }
                />
            )}
        </View>
    );
}

const createStyles = (colors: any) => StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: colors.bg,
    },
    header: {
        paddingHorizontal: spacing.lg,
        paddingTop: spacing.xl,
        paddingBottom: spacing.md,
        backgroundColor: colors.surface,
    },
    headerRow: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: spacing.md,
    },
    headerActions: {
        flexDirection: 'row',
        gap: spacing.sm,
    },
    iconButton: {
        width: 36,
        height: 36,
        borderRadius: radius.md,
        backgroundColor: colors.bg,
        alignItems: 'center',
        justifyContent: 'center',
        borderWidth: 1,
        borderColor: colors.border,
    },
    headerTitle: {
        fontSize: 28,
        fontWeight: '700',
        color: colors.text,
    },
    tabs: {
        flexDirection: 'row',
        paddingHorizontal: spacing.lg,
        paddingVertical: spacing.md,
        backgroundColor: colors.surface,
        borderBottomWidth: 1,
        borderBottomColor: colors.border,
        gap: spacing.sm,
    },
    tab: {
        paddingHorizontal: spacing.md,
        paddingVertical: spacing.sm,
        borderRadius: radius.md,
        backgroundColor: colors.bg,
    },
    tabActive: {
        backgroundColor: colors.primary,
    },
    tabText: {
        fontSize: 13,
        fontWeight: '600',
        color: colors.subtext,
    },
    tabTextActive: {
        color: '#fff',
    },
    filters: {
        paddingHorizontal: spacing.lg,
        paddingTop: spacing.sm,
        backgroundColor: colors.surface,
        borderBottomWidth: 1,
        borderBottomColor: colors.border,
    },
    listContent: {
        padding: spacing.lg,
        gap: spacing.md,
    },
    loadingContainer: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
    },
    card: {
        backgroundColor: colors.surface,
        borderRadius: radius.lg,
        padding: spacing.md,
        borderWidth: 1,
        borderColor: colors.border,
    },
    cardHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'flex-start',
        marginBottom: spacing.sm,
    },
    cardHeaderLeft: {
        flex: 1,
    },
    jobReference: {
        fontSize: 16,
        fontWeight: '600',
        color: colors.text,
        marginBottom: 2,
    },
    cardSubtitle: {
        fontSize: 13,
        color: colors.subtext,
    },
    statusBadge: {
        paddingHorizontal: spacing.sm,
        paddingVertical: 4,
        borderRadius: radius.sm,
    },
    statusText: {
        fontSize: 11,
        fontWeight: '600',
    },
    cardDetails: {
        gap: spacing.sm,
        marginBottom: spacing.md,
    },
    detailRow: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: spacing.sm,
    },
    detailText: {
        fontSize: 14,
        color: colors.text,
    },
    cardFooter: {
        borderTopWidth: 1,
        borderTopColor: colors.border,
        paddingTop: spacing.sm,
    },
    viewDetails: {
        fontSize: 14,
        fontWeight: '600',
        color: colors.primary,
    },
    emptyContainer: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: spacing.xl * 2,
    },
    emptyTitle: {
        fontSize: 18,
        fontWeight: '600',
        color: colors.text,
        marginTop: spacing.md,
    },
    emptySubtitle: {
        fontSize: 14,
        color: colors.subtext,
        marginTop: spacing.xs,
    },
});
