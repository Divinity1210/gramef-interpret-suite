import { Redirect, router } from 'expo-router';
import { useEffect, useState, useRef, useCallback } from 'react';
import { ScrollView, RefreshControl, Text, View, Pressable, Alert, Dimensions, ActivityIndicator, Animated, Platform, AppState } from 'react-native';
import PullToRefresh from '../../src/ui/components/PullToRefresh';
import { getInterpreterJobs, getTranslatorJobs, getAgentInvoices, getDashboardStats, getActivities, acceptInterpreterJob, declineInterpreterJob, acceptTranslatorJob, declineTranslatorJob, getUnreadNotificationCount } from '../../src/api/client';
import { useAuthStore } from '../../src/state/auth';
import Screen from '../../src/ui/components/Screen';
import { useTheme, spacing, radius, shadows } from '../../src/ui/theme';
import {
  HomeHeader,
  EarningsCard,
  UpcomingCarousel,
  StatsRow,
  RecentActivityList,
  HotJobsList
} from '../../src/ui/components/DashboardComponents';
import { Ionicons } from '@expo/vector-icons';
import { useToast } from '../../src/ui/components/Toast';
import AnimatedCard from '../../src/ui/components/AnimatedCard';

// Time-aware greeting
function getGreeting(): string {
  const hour = new Date().getHours();
  if (hour < 12) return 'Good morning';
  if (hour < 17) return 'Good afternoon';
  return 'Good evening';
}

export default function Index() {
  const token = useAuthStore((s: any) => s.token);
  const user = useAuthStore((s: any) => s.user);
  const clear = useAuthStore((s: any) => s.clear);
  const { colors } = useTheme();
  const toast = useToast();

  const [loading, setLoading] = useState(false);
  const [upcomingJobs, setUpcomingJobs] = useState<any[]>([]);
  const [hotJobs, setHotJobs] = useState<any[]>([]);
  const [availableJobs, setAvailableJobs] = useState<any[]>([]);
  const [stats, setStats] = useState({ total: 0, uninvoiced: 0, successRate: '0%' });
  const [activities, setActivities] = useState<any[]>([]);
  const [earnings, setEarnings] = useState('£0.00');
  const [earningsLabel, setEarningsLabel] = useState('');
  const [unreadCount, setUnreadCount] = useState(0);

  // Role Detection
  const roles = (user?.roles || []).map((r: string) => r.toLowerCase());
  const isClient = roles.some((r: string) => r.includes('client'));
  const isInterpreter = roles.some((r: string) => r.includes('interpreter'));
  const isTranslator = roles.some((r: string) => r.includes('translator') || r.includes('transcription'));
  const isLinguist = isInterpreter || isTranslator || (!isClient && roles.length === 0);

  async function loadData() {
    if (!token) return;
    setLoading(true);
    try {
      // Fetch unread notification count
      try {
        const countRes = await getUnreadNotificationCount();
        setUnreadCount(countRes.data?.count ?? countRes.data?.data?.count ?? 0);
      } catch { /* ignore */ }

      let allJobs: any[] = [];

      // ── Server-side stats & earnings ──
      try {
        const statsRes = await getDashboardStats();
        const sd = statsRes.data?.data;
        if (sd) {
          const te = sd.total_earnings ?? 0;
          setEarnings(`£${Number(te).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
          setStats({
            total: sd.total_jobs_this_month ?? 0,
            uninvoiced: sd.uninvoiced_jobs ?? 0,
            successRate: `${sd.success_rate ?? 0}%`,
          });
          // Store 'last updated' label
          if (sd.earnings_updated_at) {
            const diff = Date.now() - new Date(sd.earnings_updated_at).getTime();
            const days = Math.floor(diff / 86400000);
            setEarningsLabel(days <= 0 ? 'Updated today' : days === 1 ? 'Updated yesterday' : `Last updated ${days} days ago`);
          } else {
            setEarningsLabel('No payments yet');
          }
        }
      } catch (statsErr) {
        console.warn('Dashboard stats failed, falling back to client-side', statsErr);
        // Fallback: compute client-side if the new endpoint isn't deployed yet
        try {
          const invoiceRes = await getAgentInvoices({ per_page: 100 });
          const invoiceData = invoiceRes.data?.data;
          const invoices = Array.isArray(invoiceData) ? invoiceData : (invoiceData?.data || []);
          const paidInvoices = invoices.filter((inv: any) => {
            const s = String(inv.status || '').toLowerCase();
            return s === 'paid' || s === '3';
          });
          const totalEarnings = paidInvoices.reduce((sum: number, inv: any) => sum + (parseFloat(inv.total_amount) || 0), 0);
          setEarnings(`£${totalEarnings.toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
        } catch {
          setEarnings('£0.00');
        }
      }

      // ── Fetch jobs for upcoming schedule & hot jobs ──
      try {
        const promises: Promise<any>[] = [];
        if (isInterpreter || !isTranslator) promises.push(getInterpreterJobs({ per_page: 20 }));
        if (isTranslator || !isInterpreter) promises.push(getTranslatorJobs({ per_page: 20 }));

        const results = await Promise.allSettled(promises);

        results.forEach(result => {
          if (result.status === 'fulfilled') {
            const rawData = result.value.data?.data;
            const items = Array.isArray(rawData) ? rawData : (rawData?.data || []);
            allJobs = [...allJobs, ...items];
          }
        });

        allJobs.sort((a, b) => {
          const dateA = a.appointment_date || a.target_date || a.created_at || '';
          const dateB = b.appointment_date || b.target_date || b.created_at || '';
          return new Date(dateB).getTime() - new Date(dateA).getTime();
        });
      } catch (jobErr) {
        console.warn('Jobs fetch failed, showing partial dashboard', jobErr);
      }

      const today = new Date();
      today.setHours(0, 0, 0, 0);

      // Helper to map job to display format
      const mapJob = (j: any) => {
        const jobType = j.appointment_date ? 'interpreter' : 'translator';
        const toLang = typeof j.to_language === 'object' ? j.to_language?.name : (j.to_language || 'N/A');
        const fromLang = typeof j.from_language === 'object' ? j.from_language?.name : (j.from_language || 'English');
        const clientName = typeof j.client === 'object' ? j.client?.name : (j.client || 'Client');
        const rawDate = j.appointment_date || j.target_date || j.created_at;
        const dateStr = rawDate ? new Date(rawDate).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : 'TBC';
        const durationStr = j.formatted_duration || (j.duration_hours ? `${j.duration_hours}h ${j.duration_minutes || 0}m` : 'TBC');
        const location = j.address_line_1 ? `${j.address_line_1}${j.city ? ', ' + j.city : ''}` : 'Remote';
        const statusNum = typeof j.status === 'number' ? j.status : parseInt(String(j.status));
        const canAccept = typeof j.can_accept === 'boolean' ? j.can_accept : [0, 5].includes(statusNum);
        return { ...j, jobType, toLang, fromLang, clientName, dateStr, durationStr, location, canAccept };
      };

      // 1. Upcoming Schedule — assigned jobs with future dates
      const upcomingList = allJobs
        .filter(j => {
          const statusNum = typeof j.status === 'number' ? j.status : parseInt(j.status);
          return statusNum === 1; // assigned only
        })
        .filter(j => {
          const rawDate = j.appointment_date || j.target_date;
          if (!rawDate) return true; // include if no date
          return new Date(rawDate).getTime() >= today.getTime();
        })
        .slice(0, 5)
        .map(j => {
          const m = mapJob(j);
          return {
            clientName: m.clientName,
            languagePair: `${m.fromLang} → ${m.toLang}`,
            date: m.dateStr,
            time: j.start_time || 'TBC',
            duration: m.durationStr,
            location: m.location,
            onView: () => router.push(`/(tabs)/jobs/${m.jobType}-${j.id}`),
            onComplete: () => router.push(`/(tabs)/jobs/${m.jobType}-${j.id}`),
          };
        });
      setUpcomingJobs(upcomingList);

      // 2. Hot Jobs 🔥 — unassigned jobs the agent is matched with (upcoming)
      const hotList = allJobs
        .filter(j => {
          const statusNum = typeof j.status === 'number' ? j.status : parseInt(String(j.status));
          const canAccept = typeof j.can_accept === 'boolean' ? j.can_accept : (statusNum === 0 || statusNum === 5);
          return canAccept;
        })
        .filter(j => {
          const rawDate = j.appointment_date || j.target_date;
          if (!rawDate) return true;
          return new Date(rawDate).getTime() >= today.getTime();
        })
        .slice(0, 5)
        .map(j => {
          const m = mapJob(j);
          return {
            title: `${m.toLang} Interpreter`,
            languagePair: `${m.fromLang} ↔ ${m.toLang}`,
            location: m.location,
            time: j.start_time || 'TBC',
            duration: m.durationStr,
            date: m.dateStr,
            id: `${m.jobType}-${j.id}`,
            jobType: m.jobType,
            originalId: j.id,
            canAccept: m.canAccept,
            clientRef: j.client_reference || null,
          };
        });
      setHotJobs(hotList);

      // 3. All Available Jobs — only matched, unassigned jobs (NOT assigned)
      const allAvailable = allJobs
        .filter(j => {
          const statusNum = typeof j.status === 'number' ? j.status : parseInt(String(j.status));
          const canAccept = typeof j.can_accept === 'boolean' ? j.can_accept : [0, 5].includes(statusNum);
          return canAccept;
        })
        .slice(0, 10)
        .map(j => {
          const m = mapJob(j);
          return {
            id: j.id,
            jobType: m.jobType,
            title: `${m.toLang} ${m.jobType === 'interpreter' ? 'Interpretation' : 'Translation'}`,
            languagePair: `${m.fromLang} → ${m.toLang}`,
            date: m.dateStr,
            time: j.start_time || 'TBC',
            location: m.location,
            status: j.status,
            statusName: j.status_name || (j.status === 0 ? 'Available' : j.status === 1 ? 'Assigned' : 'Quoted'),
            canAccept: m.canAccept,
            clientRef: j.client_reference || null,
          };
        });
      setAvailableJobs(allAvailable);

      // 4. Recent Activities — from real API data
      try {
        const actRes = await getActivities({ per_page: 5 });
        const actData = actRes.data?.data;
        const actItems = actData?.items || [];
        if (actItems.length > 0) {
          setActivities(actItems.map((a: any) => ({
            text: a.text,
            icon: a.icon || 'information-circle',
            iconColor: a.icon_color || '#E7F1FF',
            iconColorText: a.icon_color_text || '#0D6EFD',
            timeAgo: a.time_ago || '',
            jobId: a.job_id,
            jobType: a.job_type,
          })));
        } else {
          setActivities([{
            text: 'No recent activity.',
            icon: 'information-circle',
            iconColor: '#E7F1FF',
            iconColorText: '#0D6EFD',
          }]);
        }
      } catch {
        // Fallback: use client-side activities from jobs data
        const fallbackActivities: any[] = [];
        const recentCompleted = allJobs.filter(j => j.status === 4 || j.status_name === 'completed').slice(0, 2);
        recentCompleted.forEach(j => {
          const lang = typeof j.to_language === 'object' ? j.to_language?.name : (j.to_language || 'Job');
          fallbackActivities.push({ text: `${lang} session completed successfully.`, icon: 'checkmark-circle', iconColor: '#D4EDDA', iconColorText: '#28A745' });
        });
        const recentAssigned = allJobs.filter(j => j.status === 1 || j.status_name === 'assigned').slice(0, 2);
        recentAssigned.forEach(j => {
          const lang = typeof j.to_language === 'object' ? j.to_language?.name : (j.to_language || 'Job');
          const date = j.appointment_date ? new Date(j.appointment_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : '';
          fallbackActivities.push({ text: `${lang} booking confirmed${date ? ' for ' + date : ''}.`, icon: 'calendar', iconColor: '#E7F1FF', iconColorText: '#0D6EFD' });
        });
        if (fallbackActivities.length === 0) {
          fallbackActivities.push({ text: 'No recent activity.', icon: 'information-circle', iconColor: '#E7F1FF', iconColorText: '#0D6EFD' });
        }
        setActivities(fallbackActivities.slice(0, 5));
      }

    } catch (e: any) {
      console.error('Dashboard load error', e);
      if (e?.response?.status === 401) {
        await clear();
        router.replace('/login');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadData();
  }, [token, user]);

  // Push notifications now registered at root layout level (_layout.tsx)

  // Auto-refresh jobs when app is visible
  useEffect(() => {
    if (!token) return;
    const interval = setInterval(() => {
      // Only refresh if page is visible (web) or app is active (native)
      if (Platform.OS === 'web') {
        if (document.visibilityState === 'visible') loadData();
      } else {
        if (AppState.currentState === 'active') loadData();
      }
    }, 15000);
    return () => clearInterval(interval);
  }, [token, user]);

  if (!token) return <Redirect href="/login" />;

  const firstName = user?.first_name || user?.name?.split(' ')[0] || 'User';
  const displayName = user?.full_name || user?.name || `${user?.first_name || ''} ${user?.last_name || ''}`.trim() || 'User';
  const profilePicUrl = user?.agent?.profile_picture_url || undefined;
  const greeting = getGreeting();

  const quickActions = [
    { icon: 'time-outline' as const, label: 'Timesheets', color: '#276EF1', onPress: () => router.push('/timesheets') },
    { icon: 'cash-outline' as const, label: 'Finances', color: '#059669', onPress: () => router.push('/finances/agent-invoices') },
    { icon: 'briefcase-outline' as const, label: 'All Jobs', color: '#7C3AED', onPress: () => router.push('/(tabs)/jobs') },
  ];

  const getStatusColor = (status: number | string) => {
    const s = typeof status === 'number' ? status : parseInt(String(status));
    if (s === 0) return { bg: colors.primary + '20', text: colors.primary };
    if (s === 1) return { bg: colors.primary, text: '#fff' };
    if (s === 5) return { bg: '#a855f7', text: '#fff' };
    return { bg: colors.subtext + '30', text: colors.text };
  };

  const handlePullRefresh = useCallback(async () => {
    await loadData();
  }, [token, user]);

  return (
    <Screen>
      <PullToRefresh onRefresh={handlePullRefresh} refreshing={loading} tintColor={colors.primary}>
        <ScrollView
          showsVerticalScrollIndicator={false}
          contentContainerStyle={{ paddingBottom: spacing.sm }}
          refreshControl={Platform.OS !== 'web' ? <RefreshControl refreshing={loading} onRefresh={loadData} /> : undefined}
        >
          {/* Time-Aware Greeting */}
          <View style={{ paddingHorizontal: spacing.lg, paddingTop: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
            <View>
              <Text style={{ fontSize: 14, color: colors.subtext, marginBottom: 2 }}>{greeting},</Text>
              <Text style={{ fontSize: 24, fontWeight: '700', color: colors.text }}>{firstName} 👋</Text>
            </View>
            <Pressable
              onPress={() => router.push('/(tabs)/notifications' as any)}
              style={{ width: 40, height: 40, borderRadius: 20, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.surface, position: 'relative' }}
            >
              <Ionicons name={unreadCount > 0 ? "notifications" : "notifications-outline"} size={20} color={unreadCount > 0 ? colors.primary : colors.text} />
              {unreadCount > 0 && (
                <View style={{
                  position: 'absolute',
                  top: -2,
                  right: -2,
                  minWidth: 18,
                  height: 18,
                  borderRadius: 9,
                  backgroundColor: '#ef4444',
                  alignItems: 'center',
                  justifyContent: 'center',
                  paddingHorizontal: 4,
                  borderWidth: 2,
                  borderColor: colors.bg,
                }}>
                  <Text style={{ color: '#fff', fontSize: 10, fontWeight: '700' }}>
                    {unreadCount > 99 ? '99+' : unreadCount}
                  </Text>
                </View>
              )}
            </Pressable>
          </View>

          {/* Quick Actions */}
          {!isClient && (
            <View style={{ flexDirection: 'row', paddingHorizontal: spacing.lg, gap: 10, marginTop: spacing.md, marginBottom: spacing.sm }}>
              {quickActions.map((action, idx) => (
                <Pressable
                  key={idx}
                  onPress={action.onPress}
                  style={{
                    flex: 1,
                    backgroundColor: colors.surface,
                    borderRadius: radius.lg,
                    paddingVertical: 14,
                    alignItems: 'center',
                    gap: 6,
                    ...shadows.sm,
                  }}
                >
                  <View style={{
                    width: 40,
                    height: 40,
                    borderRadius: 20,
                    backgroundColor: action.color + '14',
                    alignItems: 'center',
                    justifyContent: 'center',
                  }}>
                    <Ionicons name={action.icon} size={20} color={action.color} />
                  </View>
                  <Text style={{ fontSize: 12, fontWeight: '600', color: colors.text }}>{action.label}</Text>
                </Pressable>
              ))}
            </View>
          )}

          {/* Earnings */}
          {!isClient && <View style={{ paddingHorizontal: spacing.lg }}><EarningsCard amount={earnings} subtitle={earningsLabel} /></View>}

          {/* Upcoming Schedule — assigned jobs with future dates */}
          {isClient && <Text style={{ paddingHorizontal: spacing.lg, fontSize: 18, fontWeight: '700', marginBottom: spacing.sm, color: colors.text }}>My Bookings</Text>}
          <View style={{ paddingHorizontal: spacing.lg }}><UpcomingCarousel jobs={upcomingJobs} /></View>

          <View style={{ paddingHorizontal: spacing.lg }}><StatsRow stats={stats} /></View>

          {/* Hot Jobs 🔥 — unassigned, future date */}
          {!isClient && <View style={{ paddingHorizontal: spacing.lg }}>
            <HotJobsList
              jobs={hotJobs}
              onAccept={async (job) => {
                try {
                  if (job.jobType === 'interpreter') {
                    await acceptInterpreterJob(job.originalId);
                  } else {
                    await acceptTranslatorJob(job.originalId);
                  }
                  toast.success('Job accepted successfully!');
                  loadData();
                } catch (e: any) {
                  const message = e.response?.data?.message || 'Failed to accept job';
                  if (message.toLowerCase().includes('quote')) {
                    toast.info('This job requires a quote. Opening quote form...');
                    router.push(`/(tabs)/jobs/${job.id}`);
                  } else {
                    toast.error(message);
                  }
                }
              }}
            />
          </View>}

          {/* Available Jobs — horizontal scrollable tile cards with action buttons */}
          {!isClient && availableJobs.length > 0 && (
            <View style={{ paddingHorizontal: spacing.lg }}>
              <AvailableJobsCarousel
                jobs={availableJobs}
                colors={colors}
                getStatusColor={getStatusColor}
                onRefresh={loadData}
              />
            </View>
          )}

          <View style={{ paddingHorizontal: spacing.lg }}><RecentActivityList activities={activities} /></View>
        </ScrollView>
      </PullToRefresh>
    </Screen>
  );
}

const TILE_WIDTH = 280;

function AvailableJobsCarousel({
  jobs,
  colors,
  getStatusColor,
  onRefresh,
}: {
  jobs: any[];
  colors: any;
  getStatusColor: (s: number | string) => { bg: string; text: string };
  onRefresh: () => void;
}) {
  const [actionLoadingId, setActionLoadingId] = useState<number | null>(null);
  const toast = useToast();

  const handleAccept = async (job: any) => {
    setActionLoadingId(job.id);
    try {
      if (job.jobType === 'interpreter') {
        await acceptInterpreterJob(job.id);
      } else {
        await acceptTranslatorJob(job.id);
      }
      toast.success('Job accepted successfully!');
      onRefresh();
    } catch (e: any) {
      const message = e.response?.data?.message || 'Failed to accept job';
      // If quote is required, navigate to job detail to submit quote
      if (message.toLowerCase().includes('quote')) {
        toast.info('This job requires a quote. Opening quote form...');
        router.push(`/(tabs)/jobs/${job.jobType}-${job.id}`);
      } else {
        toast.error(message);
      }
    } finally {
      setActionLoadingId(null);
    }
  };

  const handleDecline = (job: any) => {
    Alert.alert('Reject Job', 'Are you sure you want to reject this job?', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Reject',
        style: 'destructive',
        onPress: async () => {
          setActionLoadingId(job.id);
          try {
            if (job.jobType === 'interpreter') {
              await declineInterpreterJob(job.id, 'Declined via mobile');
            } else {
              await declineTranslatorJob(job.id, 'Declined via mobile');
            }
            toast.info('Job has been rejected.');
            onRefresh();
          } catch (e: any) {
            toast.error(e.response?.data?.message || 'Failed to reject');
          } finally {
            setActionLoadingId(null);
          }
        },
      },
    ]);
  };

  return (
    <View style={{ marginBottom: spacing.lg }}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: spacing.lg, marginBottom: spacing.sm }}>
        <View>
          <Text style={{ fontSize: 18, fontWeight: '700', color: colors.text }}>Available Jobs</Text>
          <Text style={{ fontSize: 12, color: colors.subtext, marginTop: 2 }}>
            {jobs.length} job{jobs.length !== 1 ? 's' : ''} matched to you
          </Text>
        </View>
        <Pressable onPress={() => router.push('/(tabs)/jobs')}>
          <Text style={{ fontSize: 13, color: colors.primary, fontWeight: '600' }}>See all →</Text>
        </Pressable>
      </View>

      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={{ paddingHorizontal: spacing.lg, gap: 12 }}
        decelerationRate="fast"
        snapToInterval={TILE_WIDTH + 12}
      >
        {jobs.map((job, i) => {
          const sc = getStatusColor(job.status);
          const isLoading = actionLoadingId === job.id;

          return (
            <View
              key={`${job.jobType}-${job.id}`}
              style={{
                width: TILE_WIDTH,
                backgroundColor: colors.surface,
                borderRadius: radius.lg,
                borderWidth: 1,
                borderColor: colors.border,
                overflow: 'hidden',
              }}
            >
              {/* Status Color Bar */}
              <View style={{ height: 4, backgroundColor: sc.bg }} />

              {/* Card Content */}
              <View style={{ padding: spacing.md }}>
                {/* Title + Badge */}
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 8 }}>
                  <Text style={{ fontSize: 16, fontWeight: '700', color: colors.text, flex: 1, marginRight: 8 }} numberOfLines={1}>
                    {job.title}
                  </Text>
                  <View style={{ backgroundColor: sc.bg, borderRadius: 12, paddingHorizontal: 8, paddingVertical: 3 }}>
                    <Text style={{ color: sc.text, fontSize: 10, fontWeight: '700', textTransform: 'uppercase' }}>
                      {job.statusName}
                    </Text>
                  </View>
                </View>

                {/* Job Info */}
                <View style={{ gap: 5, marginBottom: 12 }}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                    <Ionicons name="calendar-outline" size={14} color={colors.subtext} />
                    <Text style={{ fontSize: 13, color: colors.subtext }}>{job.date} • {job.time}</Text>
                  </View>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                    <Ionicons name="location-outline" size={14} color={colors.subtext} />
                    <Text style={{ fontSize: 13, color: colors.subtext }} numberOfLines={1}>{job.location}</Text>
                  </View>
                  {job.languagePair && (
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                      <Ionicons name="language-outline" size={14} color={colors.subtext} />
                      <Text style={{ fontSize: 13, color: colors.subtext }}>{job.languagePair}</Text>
                    </View>
                  )}
                  {job.clientRef && (
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                      <Ionicons name="document-text-outline" size={14} color={colors.subtext} />
                      <Text style={{ fontSize: 12, color: colors.subtext }}>Ref: {job.clientRef}</Text>
                    </View>
                  )}
                </View>

                {/* Action Buttons */}
                <View style={{ flexDirection: 'row', gap: 8 }}>
                  {job.canAccept && (
                    <Pressable
                      onPress={() => handleAccept(job)}
                      disabled={isLoading}
                      style={{
                        flex: 1,
                        flexDirection: 'row',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: 4,
                        backgroundColor: '#22c55e',
                        borderRadius: radius.md,
                        paddingVertical: 10,
                        opacity: isLoading ? 0.5 : 1,
                      }}
                    >
                      {isLoading ? (
                        <ActivityIndicator size="small" color="#fff" />
                      ) : (
                        <>
                          <Ionicons name="checkmark-circle" size={16} color="#fff" />
                          <Text style={{ color: '#fff', fontSize: 12, fontWeight: '700' }}>Accept</Text>
                        </>
                      )}
                    </Pressable>
                  )}

                  {/* View */}
                  <Pressable
                    onPress={() => router.push(`/(tabs)/jobs/${job.jobType}-${job.id}`)}
                    style={{
                      flex: 1,
                      flexDirection: 'row',
                      alignItems: 'center',
                      justifyContent: 'center',
                      gap: 4,
                      backgroundColor: colors.primary,
                      borderRadius: radius.md,
                      paddingVertical: 10,
                    }}
                  >
                    <Ionicons name="eye" size={16} color="#fff" />
                    <Text style={{ color: '#fff', fontSize: 12, fontWeight: '700' }}>View</Text>
                  </Pressable>

                  {job.canAccept && (
                    <Pressable
                      onPress={() => handleDecline(job)}
                      disabled={isLoading}
                      style={{
                        flex: 1,
                        flexDirection: 'row',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: 4,
                        backgroundColor: '#ef4444',
                        borderRadius: radius.md,
                        paddingVertical: 10,
                        opacity: isLoading ? 0.5 : 1,
                      }}
                    >
                      <Ionicons name="close-circle" size={16} color="#fff" />
                      <Text style={{ color: '#fff', fontSize: 12, fontWeight: '700' }}>Reject</Text>
                    </Pressable>
                  )}
                </View>
              </View>
            </View>
          );
        })}
      </ScrollView>
    </View>
  );
}
