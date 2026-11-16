import { View, Text, StyleSheet, FlatList, Pressable, RefreshControl, ActivityIndicator, TextInput, ScrollView, Alert, Animated, Dimensions, Platform, AppState } from 'react-native';
import PullToRefresh from '../../../src/ui/components/PullToRefresh';
import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { router } from 'expo-router';
import { useTheme, spacing, radius } from '../../../src/ui/theme';
import { getInterpreterJobs, getTranslatorJobs, acceptInterpreterJob, declineInterpreterJob, acceptTranslatorJob, declineTranslatorJob } from '../../../src/api/client';
import { Ionicons } from '@expo/vector-icons';
import { useAuthStore } from '../../../src/state/auth';

type TabType = 'available' | 'active' | 'completed';
type JobTypeFilter = 'all' | 'interpreter' | 'translator';

const CARD_WIDTH = Dimensions.get('window').width - 40;

interface Job {
  id: number;
  status: number | string;
  status_name?: string;
  appointment_date?: string;
  target_date?: string;
  start_time?: string;
  duration_hours?: number;
  duration_minutes?: number;
  to_language?: { name: string };
  from_language?: { name: string };
  department?: string;
  address_line_1?: string;
  county?: string;
  postcode?: string;
  client_reference?: string;
  client?: { name: string };
  skill?: { id?: number; name?: string };
  can_accept?: boolean;
  type: 'interpreter' | 'translator';
}

export default function JobsListScreen() {
  const { colors } = useTheme();
  const user = useAuthStore((s) => s.user);
  const [activeTab, setActiveTab] = useState<TabType>('available');
  const [allJobs, setAllJobs] = useState<Job[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchActive, setSearchActive] = useState(false);
  const [actionLoadingId, setActionLoadingId] = useState<number | null>(null);
  const [jobTypeFilter, setJobTypeFilter] = useState<JobTypeFilter>('all');

  // Determine user role
  const roles = (user?.roles || []).map((r: string) => r.toLowerCase());
  const isInterpreter = roles.some((r: string) => r.includes('interpreter'));
  const isTranslator = roles.some((r: string) => r.includes('translator') || r.includes('transcription'));
  const showJobTypeToggle = isInterpreter && isTranslator;

  useEffect(() => {
    loadJobs();
  }, [activeTab]);

  // Auto-refresh jobs when app is visible
  useEffect(() => {
    const interval = setInterval(() => {
      if (Platform.OS === 'web') {
        if (document.visibilityState === 'visible') loadJobs();
      } else {
        if (AppState.currentState === 'active') loadJobs();
      }
    }, 15000);
    return () => clearInterval(interval);
  }, [activeTab]);

  const canAcceptJob = useCallback((job: Job) => {
    if (typeof job.can_accept === 'boolean') {
      return job.can_accept;
    }
    const status = typeof job.status === 'number' ? job.status : parseInt(String(job.status));
    return [0, 5].includes(status) || isNaN(status);
  }, []);

  const loadJobs = async () => {
    try {
      setLoading(true);
      const fetchedJobs: Job[] = [];
      const promises: Promise<any>[] = [];

      const params: any = { per_page: 50 };
      if (searchQuery.trim()) {
        params.q = searchQuery.trim();
      }

      if (isInterpreter || (!isInterpreter && !isTranslator)) {
        promises.push(getInterpreterJobs(params).catch(e => ({ data: { data: [] } })));
      }
      if (isTranslator || (!isInterpreter && !isTranslator)) {
        promises.push(getTranslatorJobs(params).catch(e => ({ data: { data: [] } })));
      }

      const results = await Promise.allSettled(promises);
      results.forEach((result) => {
        if (result.status === 'fulfilled') {
          const rawData = result.value?.data?.data;
          const items = Array.isArray(rawData) ? rawData : (rawData?.data || []);
          fetchedJobs.push(...items.map((j: any) => ({
            ...j,
            type: j.type || (j.appointment_date !== undefined ? 'interpreter' : 'translator'),
          })));
        }
      });

      setAllJobs(fetchedJobs);
    } catch (error) {
      // Silently handle
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  // Filter by job type + tab + search
  const filteredJobs = useMemo(() => {
    let jobs = allJobs;

    // Job type filtering
    if (jobTypeFilter !== 'all') {
      jobs = jobs.filter(job => job.type === jobTypeFilter);
    }

    // Tab filtering
    jobs = jobs.filter(job => {
      const status = typeof job.status === 'number' ? job.status : parseInt(String(job.status));
      switch (activeTab) {
        case 'available': return canAcceptJob(job);
        case 'active': return status === 1;
        case 'completed': return [2, 4, 6].includes(status);
      }
    });

    // Client-side text filter
    if (searchQuery.trim()) {
      const q = searchQuery.trim().toLowerCase();
      jobs = jobs.filter(job => {
        const lang = typeof job.to_language === 'object' ? job.to_language?.name : '';
        const fromLang = typeof job.from_language === 'object' ? job.from_language?.name : '';
        const clientName = typeof job.client === 'object' ? job.client?.name : '';
        return (
          String(job.id).includes(q) ||
          (job.client_reference || '').toLowerCase().includes(q) ||
          (lang || '').toLowerCase().includes(q) ||
          (fromLang || '').toLowerCase().includes(q) ||
          (job.address_line_1 || '').toLowerCase().includes(q) ||
          (job.postcode || '').toLowerCase().includes(q) ||
          (clientName || '').toLowerCase().includes(q)
        );
      });
    }

    return jobs;
  }, [allJobs, activeTab, searchQuery, jobTypeFilter, canAcceptJob]);

  const handleRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadJobs();
  }, []);

  const handleSearch = useCallback(() => {
    loadJobs();
  }, [searchQuery]);

  const handleJobPress = (job: Job) => {
    router.push(`/jobs/${job.type}-${job.id}`);
  };

  // Accept job inline
  const handleAccept = async (job: Job) => {
    setActionLoadingId(job.id);
    try {
      if (job.type === 'interpreter') {
        await acceptInterpreterJob(job.id);
      } else {
        await acceptTranslatorJob(job.id);
      }
      Alert.alert('Success', 'Job accepted successfully!');
      loadJobs();
    } catch (e: any) {
      const message = e.response?.data?.message || e.message || 'Failed to accept job';
      Alert.alert('Error', message);
    } finally {
      setActionLoadingId(null);
    }
  };

  // Decline job inline
  const handleDecline = (job: Job) => {
    Alert.alert(
      'Reject Job',
      `Are you sure you want to reject this job?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Reject',
          style: 'destructive',
          onPress: async () => {
            setActionLoadingId(job.id);
            try {
              if (job.type === 'interpreter') {
                await declineInterpreterJob(job.id, 'Declined via mobile app');
              } else {
                await declineTranslatorJob(job.id, 'Declined via mobile app');
              }
              Alert.alert('Rejected', 'Job has been rejected.');
              loadJobs();
            } catch (e: any) {
              const message = e.response?.data?.message || e.message || 'Failed to reject job';
              Alert.alert('Error', message);
            } finally {
              setActionLoadingId(null);
            }
          },
        },
      ]
    );
  };

  const getStatusLabel = (status: number | string): string => {
    const statusNum = typeof status === 'number' ? status : parseInt(String(status));
    switch (statusNum) {
      case 0: return 'Available';
      case 1: return 'Assigned';
      case 2: return 'Cancelled';
      case 3: return 'Rejected';
      case 4: return 'Completed';
      case 5: return 'Quoted';
      case 6: return 'DNA';
      default: return 'Available';
    }
  };

  const getStatusColor = (status: number | string): string => {
    const statusNum = typeof status === 'number' ? status : parseInt(String(status));
    switch (statusNum) {
      case 0: return '#3b82f6';
      case 1: return '#8b5cf6';
      case 2: return '#6b7280';
      case 3: return '#ef4444';
      case 4: return '#22c55e';
      case 5: return '#f59e0b';
      case 6: return '#ef4444';
      default: return '#3b82f6';
    }
  };

  const formatDate = (dateStr?: string): string => {
    if (!dateStr) return 'TBD';
    try {
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch { return dateStr; }
  };

  const getTabCounts = useMemo(() => {
    let jobs = allJobs;
    if (jobTypeFilter !== 'all') {
      jobs = jobs.filter(j => j.type === jobTypeFilter);
    }
    const available = jobs.filter(j => {
      return canAcceptJob(j);
    }).length;
    const active = jobs.filter(j => {
      const s = typeof j.status === 'number' ? j.status : parseInt(String(j.status));
      return s === 1;
    }).length;
    const completed = jobs.filter(j => {
      const s = typeof j.status === 'number' ? j.status : parseInt(String(j.status));
      return [2, 4, 6].includes(s);
    }).length;
    return { available, active, completed };
  }, [allJobs, jobTypeFilter, canAcceptJob]);

  const styles = createStyles(colors);
  const isJobLoading = (id: number) => actionLoadingId === id;

  const renderJobCard = ({ item }: { item: Job }) => {
    const statusNum = typeof item.status === 'number' ? item.status : parseInt(String(item.status));
    const isAvailable = canAcceptJob(item);
    const statusColor = getStatusColor(item.status);

    return (
      <View style={styles.cardOuter}>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          snapToAlignment="start"
          decelerationRate="fast"
          snapToOffsets={[0, CARD_WIDTH + 12]}
          contentContainerStyle={{ alignItems: 'stretch' }}
        >
          {/* Main Job Card */}
          <Pressable
            style={[styles.jobCard, { width: CARD_WIDTH }]}
            onPress={() => handleJobPress(item)}
          >
            {/* Status strip */}
            <View style={[styles.statusStrip, { backgroundColor: statusColor }]} />

            <View style={styles.cardContent}>
              {/* Header Row */}
              <View style={styles.jobHeader}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.jobTitle} numberOfLines={1}>
                    {item.to_language?.name || 'N/A'}
                  </Text>
                  <Text style={styles.jobType}>
                    {item.type === 'interpreter' ? '🎤 Interpretation' : '📄 Translation'}
                  </Text>
                </View>
                <View style={[styles.statusBadge, { backgroundColor: statusColor + '20' }]}>
                  <View style={[styles.statusDot, { backgroundColor: statusColor }]} />
                  <Text style={[styles.statusText, { color: statusColor }]}>
                    {item.status_name || getStatusLabel(item.status)}
                  </Text>
                </View>
              </View>

              {/* Details Grid */}
              <View style={styles.detailsGrid}>
                <View style={styles.detailItem}>
                  <Ionicons name="calendar-outline" size={14} color={colors.subtext} />
                  <Text style={styles.detailText}>
                    {formatDate(item.appointment_date || item.target_date)}
                  </Text>
                </View>

                {item.start_time && (
                  <View style={styles.detailItem}>
                    <Ionicons name="time-outline" size={14} color={colors.subtext} />
                    <Text style={styles.detailText}>{item.start_time}</Text>
                  </View>
                )}

                <View style={styles.detailItem}>
                  <Ionicons name="location-outline" size={14} color={colors.subtext} />
                  <Text style={styles.detailText} numberOfLines={1}>
                    {item.address_line_1 || 'Remote'}
                  </Text>
                </View>

                {item.client_reference && (
                  <View style={styles.detailItem}>
                    <Ionicons name="document-text-outline" size={14} color={colors.subtext} />
                    <Text style={styles.detailText}>Ref: {item.client_reference}</Text>
                  </View>
                )}
              </View>

              {/* Footer */}
              <View style={styles.jobFooter}>
                <Text style={styles.jobId}>#{item.id}</Text>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
                  <Text style={styles.swipeHint}>
                    {isAvailable ? 'Swipe for actions →' : 'Tap to view'}
                  </Text>
                </View>
              </View>
            </View>
          </Pressable>

          {/* Action Buttons Panel (revealed on swipe) */}
          <View style={styles.actionsPanel}>
            {/* View Button */}
            <Pressable
              style={[styles.actionBtn, styles.viewBtn]}
              onPress={() => handleJobPress(item)}
            >
              <Ionicons name="eye-outline" size={22} color="#fff" />
              <Text style={styles.actionBtnText}>View</Text>
            </Pressable>

            {/* Accept Button - only for available jobs */}
            {isAvailable && (
              <Pressable
                style={[styles.actionBtn, styles.acceptBtn, isJobLoading(item.id) && styles.actionBtnDisabled]}
                onPress={() => handleAccept(item)}
                disabled={isJobLoading(item.id)}
              >
                <Ionicons name="checkmark-circle-outline" size={22} color="#fff" />
                <Text style={styles.actionBtnText}>
                  {isJobLoading(item.id) ? '...' : 'Accept'}
                </Text>
              </Pressable>
            )}

            {/* Reject Button - only for available jobs */}
            {isAvailable && (
              <Pressable
                style={[styles.actionBtn, styles.rejectBtn, isJobLoading(item.id) && styles.actionBtnDisabled]}
                onPress={() => handleDecline(item)}
                disabled={isJobLoading(item.id)}
              >
                <Ionicons name="close-circle-outline" size={22} color="#fff" />
                <Text style={styles.actionBtnText}>
                  {isJobLoading(item.id) ? '...' : 'Reject'}
                </Text>
              </Pressable>
            )}
          </View>
        </ScrollView>
      </View>
    );
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <Text style={styles.headerTitle}>Jobs</Text>
          <Pressable onPress={() => setSearchActive(!searchActive)}>
            <Ionicons name={searchActive ? 'close-circle' : 'search'} size={24} color={colors.text} />
          </Pressable>
        </View>

        {/* Search Bar */}
        {searchActive && (
          <View style={styles.searchContainer}>
            <Ionicons name="search-outline" size={18} color={colors.subtext} />
            <TextInput
              style={styles.searchInput}
              placeholder="Search by ID, language, reference, client..."
              placeholderTextColor={colors.subtext}
              value={searchQuery}
              onChangeText={setSearchQuery}
              onSubmitEditing={handleSearch}
              returnKeyType="search"
              autoFocus
            />
            {searchQuery.length > 0 && (
              <Pressable onPress={() => { setSearchQuery(''); loadJobs(); }}>
                <Ionicons name="close-circle" size={18} color={colors.subtext} />
              </Pressable>
            )}
          </View>
        )}
      </View>

      {/* Job Type Toggle - shown when agent has both interpreter & translator roles */}
      {showJobTypeToggle && (
        <View style={styles.jobTypeToggleContainer}>
          {([
            { key: 'all' as JobTypeFilter, label: 'All Jobs', icon: 'layers-outline' as const },
            { key: 'interpreter' as JobTypeFilter, label: '🎤 Interpreting', icon: undefined },
            { key: 'translator' as JobTypeFilter, label: '📄 Translation', icon: undefined },
          ]).map(option => (
            <Pressable
              key={option.key}
              style={[styles.jobTypeBtn, jobTypeFilter === option.key && styles.jobTypeBtnActive]}
              onPress={() => setJobTypeFilter(option.key)}
            >
              {option.icon && (
                <Ionicons
                  name={option.icon}
                  size={14}
                  color={jobTypeFilter === option.key ? '#fff' : colors.subtext}
                  style={{ marginRight: 4 }}
                />
              )}
              <Text style={[styles.jobTypeText, jobTypeFilter === option.key && styles.jobTypeTextActive]}>
                {option.label}
              </Text>
            </Pressable>
          ))}
        </View>
      )}

      {/* Tab Bar with Counts */}
      <View style={styles.tabs}>
        {([
          { key: 'available' as TabType, label: 'Available', count: getTabCounts.available, icon: 'radio-button-on' as const },
          { key: 'active' as TabType, label: 'Active', count: getTabCounts.active, icon: 'briefcase' as const },
          { key: 'completed' as TabType, label: 'Completed', count: getTabCounts.completed, icon: 'checkmark-done' as const },
        ]).map(tab => (
          <Pressable
            key={tab.key}
            style={[styles.tab, activeTab === tab.key && styles.tabActive]}
            onPress={() => setActiveTab(tab.key)}
          >
            <Ionicons
              name={tab.icon}
              size={14}
              color={activeTab === tab.key ? '#fff' : colors.subtext}
              style={{ marginRight: 4 }}
            />
            <Text style={[styles.tabText, activeTab === tab.key && styles.tabTextActive]}>
              {tab.label}
            </Text>
            {tab.count > 0 && (
              <View style={[styles.tabBadge, activeTab === tab.key && styles.tabBadgeActive]}>
                <Text style={[styles.tabBadgeText, activeTab === tab.key && styles.tabBadgeTextActive]}>
                  {tab.count}
                </Text>
              </View>
            )}
          </Pressable>
        ))}
      </View>

      {loading && !refreshing ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : (
        <PullToRefresh onRefresh={handleRefresh} refreshing={refreshing} tintColor={colors.primary}>
          <FlatList
            data={filteredJobs}
            renderItem={renderJobCard}
            keyExtractor={(item) => `${item.type}-${item.id}`}
            contentContainerStyle={styles.listContent}
            refreshControl={
              Platform.OS !== 'web' ? <RefreshControl refreshing={refreshing} onRefresh={handleRefresh} tintColor={colors.primary} /> : undefined
            }
            ListHeaderComponent={searchQuery.trim() ? (
              <View style={styles.searchResultHeader}>
                <Text style={{ fontSize: 13, color: colors.subtext }}>
                  {filteredJobs.length} result{filteredJobs.length !== 1 ? 's' : ''} for "{searchQuery.trim()}"
                </Text>
              </View>
            ) : activeTab === 'available' ? (
              <View style={styles.swipeInstruction}>
                <Ionicons name="swap-horizontal-outline" size={16} color={colors.primary} />
                <Text style={styles.swipeInstructionText}>
                  Swipe cards left to reveal Accept, View & Reject actions
                </Text>
              </View>
            ) : null}
            ListEmptyComponent={() => (
              <View style={styles.emptyContainer}>
                <Ionicons
                  name={activeTab === 'available' ? 'radio-button-on-outline' : activeTab === 'active' ? 'briefcase-outline' : 'checkmark-done-outline'}
                  size={64}
                  color={colors.border}
                />
                <Text style={styles.emptyTitle}>
                  {searchQuery.trim() ? 'No matching jobs' : `No ${activeTab} jobs`}
                </Text>
                <Text style={styles.emptySubtitle}>
                  {searchQuery.trim()
                    ? `No jobs match "${searchQuery.trim()}". Try a different search.`
                    : activeTab === 'available'
                      ? 'There are no jobs available for you at the moment. Check back later!'
                      : activeTab === 'active'
                        ? 'You have no assigned jobs currently'
                        : 'No completed jobs yet'}
                </Text>
              </View>
            )}
          />
        </PullToRefresh>
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
  headerTitle: {
    fontSize: 28,
    fontWeight: '700',
    color: colors.text,
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.bg,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    marginTop: spacing.sm,
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: colors.border,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 10,
    fontSize: 14,
    color: colors.text,
  },
  searchResultHeader: {
    marginBottom: spacing.sm,
  },
  swipeInstruction: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: colors.primary + '10',
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    borderRadius: radius.md,
    marginBottom: spacing.md,
  },
  swipeInstructionText: {
    fontSize: 12,
    color: colors.primary,
    fontWeight: '500',
  },
  jobTypeToggleContainer: {
    flexDirection: 'row',
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
    backgroundColor: colors.surface,
    gap: 6,
  },
  jobTypeBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 7,
    borderRadius: radius.md,
    backgroundColor: colors.bg,
    borderWidth: 1,
    borderColor: colors.border,
  },
  jobTypeBtnActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  jobTypeText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.subtext,
  },
  jobTypeTextActive: {
    color: '#fff',
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
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: spacing.sm,
    borderRadius: radius.full,
    backgroundColor: colors.bg,
    gap: 2,
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
  tabBadge: {
    backgroundColor: colors.subtext + '20',
    borderRadius: 10,
    paddingHorizontal: 5,
    paddingVertical: 1,
    marginLeft: 3,
  },
  tabBadgeActive: {
    backgroundColor: 'rgba(255,255,255,0.3)',
  },
  tabBadgeText: {
    fontSize: 10,
    fontWeight: '700',
    color: colors.subtext,
  },
  tabBadgeTextActive: {
    color: '#fff',
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
  cardOuter: {
    overflow: 'hidden',
    borderRadius: radius.lg,
  },
  jobCard: {
    flexDirection: 'row',
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: colors.border,
  },
  statusStrip: {
    width: 4,
  },
  cardContent: {
    flex: 1,
    padding: spacing.md,
  },
  jobHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: spacing.sm,
  },
  jobTitle: {
    fontSize: 17,
    fontWeight: '700',
    color: colors.text,
    marginBottom: 2,
  },
  jobType: {
    fontSize: 12,
    color: colors.subtext,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: radius.full,
    gap: 4,
  },
  statusDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  statusText: {
    fontSize: 11,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  detailsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
    marginBottom: spacing.sm,
  },
  detailItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    minWidth: '45%',
  },
  detailText: {
    fontSize: 13,
    color: colors.subtext,
    flex: 1,
  },
  jobFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  jobId: {
    fontSize: 12,
    color: colors.subtext,
    fontWeight: '500',
  },
  swipeHint: {
    fontSize: 11,
    color: colors.primary,
    fontWeight: '500',
    fontStyle: 'italic',
  },
  // Action buttons panel
  actionsPanel: {
    flexDirection: 'row',
    marginLeft: 8,
    gap: 6,
    alignItems: 'stretch',
  },
  actionBtn: {
    width: 72,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: radius.lg,
    gap: 4,
    paddingVertical: spacing.md,
  },
  actionBtnText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#fff',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  actionBtnDisabled: {
    opacity: 0.5,
  },
  viewBtn: {
    backgroundColor: '#3b82f6',
  },
  acceptBtn: {
    backgroundColor: '#22c55e',
  },
  rejectBtn: {
    backgroundColor: '#ef4444',
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: spacing.xl * 2,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.text,
    marginTop: spacing.lg,
  },
  emptySubtitle: {
    fontSize: 14,
    color: colors.subtext,
    marginTop: spacing.sm,
    textAlign: 'center',
    paddingHorizontal: spacing.xl,
  },
});
