import { useEffect, useState, useCallback } from 'react';
import { View, Text, ActivityIndicator, ScrollView, Alert, Platform, StyleSheet, Modal, TextInput, Pressable, KeyboardAvoidingView, AppState } from 'react-native';
import { useLocalSearchParams, router } from 'expo-router';
import * as Location from 'expo-location';
import * as ImagePicker from 'expo-image-picker';
import {
  getInterpreterJob,
  getTranslatorJob,
  uploadTimesheetForJob,
  acceptInterpreterJob,
  declineInterpreterJob,
  acceptTranslatorJob,
  declineTranslatorJob,
  completeInterpreterJob,
  dnaInterpreterJob,
  returnInterpreterJob,
  checkInInterpreterJob,
  checkOutInterpreterJob,
  getJobAmount,
  setJobAmount,
  submitInterpreterJobQuote,
  updateInterpreterJobQuote,
  submitTranslatorJobQuote,
  submitJobExpenses,
  getJobExpenses
} from '../../../src/api/client';
import Screen from '../../../src/ui/components/Screen';
import Header from '../../../src/ui/components/Header';
import MapCard from '../../../src/ui/components/MapCard';
import DocumentsList from '../../../src/ui/components/DocumentsList';
import Button from '../../../src/ui/components/Button';
import { useTheme, spacing, typography, radius } from '../../../src/ui/theme';
import { Ionicons } from '@expo/vector-icons';

type Doc = { id: number; type?: string; name?: string };

// Job status enum matching backend config/enums.php
const JOB_STATUS = {
  PENDING: 0,
  ASSIGNED: 1,
  CANCELLED: 2,
  REJECTED: 3,
  COMPLETED: 4,
  QUOTED: 5,
  DNA: 6,
} as const;

type InterpreterDetail = {
  id: number;
  reference?: string;
  language?: string;
  appointment_date?: string;
  start_time?: string | null;
  duration_hours?: number | null;
  duration_minutes?: number | null;
  status?: number;
  status_name?: string;
  agent_status?: string;
  client_name?: string;
  agent_name?: string | null;
  company_name?: string | null;
  department?: string | null;
  skill?: { id?: number; name?: string } | null;
  gender_preference?: string | null;
  special_requirements?: string | null;
  client_reference?: string | null;
  contact_information?: string | null;
  notes?: string | null;
  is_matched?: boolean;
  matched_status?: number | null;
  requires_quote?: boolean;
  can_accept?: boolean;
  address?: { line_1?: string; line_2?: string; county?: string; postcode?: string };
  coordinates?: { lat?: number; lng?: number };
  documents?: Doc[];
  // Backend-provided action flags
  can_complete?: boolean;
  can_dna?: boolean;
  can_return?: boolean;
  can_cancel?: boolean;
  dna?: boolean;
  retrn?: boolean;
  timesheet?: { id?: number; status?: string; signature_method?: string; timesheet_status?: string };
};

type TranslatorDetail = {
  id: number;
  from_language?: string;
  to_language?: string;
  target_date?: string;
  word_count?: number | null;
  status?: number;
  status_name?: string;
  client_name?: string;
  agent_name?: string | null;
  notes?: string | null;
  documents?: Doc[];
  requires_quote?: boolean;
  has_quoted?: boolean;
  can_accept?: boolean;
  agent_quote?: {
    id: number;
    interpreting_cost?: number;
    travel_time?: number;
    travel_cost?: number;
    mileage_miles?: number;
    mileage_cost?: number;
    cost_description?: string;
    cost?: number;
    status?: number | string | null;
  } | null;
  is_matched?: boolean;
  matched_status?: number | null;
};

export default function JobDetail() {
  const { colors } = useTheme();
  const params = useLocalSearchParams();
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<InterpreterDetail | TranslatorDetail | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [jobType, setJobType] = useState<'interpreter' | 'translator'>('interpreter');

  // Amount entry state
  const [showAmountModal, setShowAmountModal] = useState(false);
  const [amountLoading, setAmountLoading] = useState(false);
  const [existingAmount, setExistingAmount] = useState<any>(null);
  const [baseAmount, setBaseAmount] = useState('');
  const [expenses, setExpenses] = useState('');
  const [surcharge, setSurcharge] = useState('');
  const [otherCharges, setOtherCharges] = useState('');
  const [amountNotes, setAmountNotes] = useState('');

  // Quote form state
  const [showQuoteModal, setShowQuoteModal] = useState(false);
  const [quoteLoading, setQuoteLoading] = useState(false);
  const [interpretingCost, setInterpretingCost] = useState('');
  const [travelTime, setTravelTime] = useState('');
  const [travelCost, setTravelCost] = useState('');
  const [mileageMiles, setMileageMiles] = useState('');
  const [mileageCost, setMileageCost] = useState('');
  const [quoteCostDescription, setQuoteCostDescription] = useState('');
  const [quoteCost, setQuoteCost] = useState('');

  // Expense form state
  const [showExpenseModal, setShowExpenseModal] = useState(false);
  const [expenseLoading, setExpenseLoading] = useState(false);
  const [expenseBaseAmount, setExpenseBaseAmount] = useState('');
  const [expenseItems, setExpenseItems] = useState<Array<{ type: string; value: string }>>([{ type: '', value: '' }]);
  const [expenseNotes, setExpenseNotes] = useState('');
  const [existingExpenses, setExistingExpenses] = useState<any>(null);

  useEffect(() => {
    async function load() {
      setLoading(true);
      try {
        const key = String(params.id);
        const [type, rawId] = key.split('-');
        const id = Number(rawId);
        setJobType(type === 'interpreter' ? 'interpreter' : 'translator');
        if (type === 'interpreter') {
          const res = await getInterpreterJob(id);
          setData((res as any)?.data?.data ?? (res as any)?.data ?? null);
        } else {
          const res = await getTranslatorJob(id);
          setData((res as any)?.data?.data ?? (res as any)?.data ?? null);
        }
      } catch (e: any) {
        console.error('Failed to load job:', e);
        Alert.alert('Error', 'Failed to load job details');
      } finally {
        setLoading(false);
      }
    }
    load();
  }, [params.id]);

  // Reload job data
  async function reloadJob() {
    if (!data) return;
    try {
      if (jobType === 'interpreter') {
        const res = await getInterpreterJob(data.id);
        setData((res as any)?.data?.data ?? (res as any)?.data ?? null);
      } else {
        const res = await getTranslatorJob(data.id);
        setData((res as any)?.data?.data ?? (res as any)?.data ?? null);
      }
    } catch (e) {
      console.error('Failed to reload job:', e);
    }
  }

  useEffect(() => {
    if (!data?.id) return;
    const interval = setInterval(() => {
      if (Platform.OS === 'web') {
        if (document.visibilityState === 'visible') {
          reloadJob();
        }
      } else if (AppState.currentState === 'active') {
        reloadJob();
      }
    }, 15000);
    return () => clearInterval(interval);
  }, [data?.id, jobType]);

  // Accept job — if quote required, open quote modal instead of calling API
  async function handleAccept() {
    if (!data) return;
    const raw = data as any;
    if (raw?.requires_quote && !raw?.has_quoted) {
      setShowQuoteModal(true);
      return;
    }
    setActionLoading(true);
    try {
      if (jobType === 'interpreter') {
        await acceptInterpreterJob(data.id);
      } else {
        await acceptTranslatorJob(data.id);
      }
      Alert.alert('Success', 'Job accepted successfully!');
      await reloadJob();
    } catch (e: any) {
      const message = e.response?.data?.message || e.message || 'Failed to accept job';
      Alert.alert('Error', message);
    } finally {
      setActionLoading(false);
    }
  }

  // Decline job
  async function handleDecline() {
    if (!data) return;
    Alert.alert(
      'Decline Job',
      'Are you sure you want to decline this job?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Decline',
          style: 'destructive',
          onPress: async () => {
            setActionLoading(true);
            try {
              if (jobType === 'interpreter') {
                await declineInterpreterJob(data.id, 'Declined via mobile app');
              } else {
                await declineTranslatorJob(data.id, 'Declined via mobile app');
              }
              Alert.alert('Success', 'Job declined successfully');
              router.back();
            } catch (e: any) {
              const message = e.response?.data?.message || e.message || 'Failed to decline job';
              Alert.alert('Error', message);
            } finally {
              setActionLoading(false);
            }
          },
        },
      ]
    );
  }

  // Submit quote
  async function handleSubmitQuote() {
    if (!data) return;
    setQuoteLoading(true);
    try {
      if (jobType === 'interpreter') {
        await submitInterpreterJobQuote(data.id, {
          interpreting_cost: parseFloat(interpretingCost) || 0,
          travel_time: parseFloat(travelTime) || 0,
          travel_cost: parseFloat(travelCost) || 0,
          mileage_miles: parseFloat(mileageMiles) || 0,
          mileage_cost: parseFloat(mileageCost) || 0,
          cost_description: quoteCostDescription || 'N/A',
          cost: parseFloat(quoteCost) || 0,
        });
      } else {
        await submitTranslatorJobQuote(data.id, {
          cost_description: quoteCostDescription || 'N/A',
          cost: parseFloat(quoteCost) || 0,
        });
      }
      setShowQuoteModal(false);
      Alert.alert('Success', 'Quote submitted successfully!');
      // Reset form
      setInterpretingCost(''); setTravelTime(''); setTravelCost('');
      setMileageMiles(''); setMileageCost(''); setQuoteCostDescription(''); setQuoteCost('');
      await reloadJob();
    } catch (e: any) {
      const message = e.response?.data?.message || e.message || 'Failed to submit quote';
      Alert.alert('Error', message);
    } finally {
      setQuoteLoading(false);
    }
  }

  // Submit expenses
  async function handleSubmitExpenses() {
    if (!data) return;
    setExpenseLoading(true);
    try {
      const details = expenseItems
        .filter(item => item.type && parseFloat(item.value) > 0)
        .map(item => ({ type: item.type, value: parseFloat(item.value) }));

      await submitJobExpenses(data.id, {
        base_amount: parseFloat(expenseBaseAmount) || 0,
        expenses_details: details,
        notes: expenseNotes || undefined,
      });
      setShowExpenseModal(false);
      Alert.alert('Success', 'Expenses saved successfully!');
      // Reload expenses
      try {
        const res = await getJobExpenses(data.id);
        setExistingExpenses((res as any)?.data?.data ?? null);
      } catch { }
    } catch (e: any) {
      const message = e.response?.data?.message || e.message || 'Failed to save expenses';
      Alert.alert('Error', message);
    } finally {
      setExpenseLoading(false);
    }
  }

  // Check in / Check out with location
  async function handleAction(action: 'check-in' | 'check-out') {
    if (!data) return;
    setActionLoading(true);
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        Alert.alert('Permission denied', 'Location permission is required for this action.');
        setActionLoading(false);
        return;
      }
      const location = await Location.getCurrentPositionAsync({});
      if (action === 'check-in') {
        await checkInInterpreterJob(data.id, location.coords.latitude, location.coords.longitude);
      } else {
        await checkOutInterpreterJob(data.id, location.coords.latitude, location.coords.longitude);
      }
      Alert.alert('Success', action === 'check-in' ? 'Checked in successfully' : 'Checked out successfully');
      await reloadJob();
    } catch (e: any) {
      const message = e.response?.data?.message || e.message || 'Action failed';
      Alert.alert('Error', message);
    } finally {
      setActionLoading(false);
    }
  }

  // Upload timesheet document
  async function handleUpload() {
    if (!data) return;
    try {
      let asset: any = null;

      // On web/PWA, use file input picker instead of camera
      if (Platform.OS === 'web') {
        const fileAsset = await new Promise<any>((resolve, reject) => {
          const input = document.createElement('input');
          input.type = 'file';
          input.accept = 'image/*,.pdf';
          input.onchange = (e: any) => {
            const file = e.target?.files?.[0];
            if (!file) { reject(new Error('No file selected')); return; }
            resolve({
              uri: URL.createObjectURL(file),
              fileName: file.name,
              mimeType: file.type,
              _webFile: file,
            });
          };
          input.click();
        });
        asset = fileAsset;
      } else {
        // On native, use image picker with gallery option
        const result = await ImagePicker.launchImageLibraryAsync({
          mediaTypes: ImagePicker.MediaTypeOptions.Images,
          quality: 0.7,
        });
        if (result.canceled) return;
        asset = result.assets[0];
      }

      if (!asset) return;
      setActionLoading(true);

      const formData = new FormData();
      if (Platform.OS === 'web' && asset._webFile) {
        formData.append('file', asset._webFile, asset.fileName || 'timesheet.jpg');
      } else {
        formData.append('file', {
          uri: asset.uri,
          name: asset.fileName || 'timesheet.jpg',
          type: asset.mimeType || 'image/jpeg',
        } as any);
      }

      // Single API call — uploads file and attaches to the job
      await uploadTimesheetForJob(data.id, formData);
      Alert.alert('Success', 'Signed timesheet uploaded');
      await reloadJob();
    } catch (e: any) {
      console.error('Upload error:', e);
      Alert.alert('Error', e?.message || 'Upload failed');
    } finally {
      setActionLoading(false);
    }
  }

  // Complete job
  async function handleComplete() {
    if (!data || jobType !== 'interpreter') return;
    Alert.alert(
      'Complete Job',
      'Mark this job as completed?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Complete',
          onPress: async () => {
            setActionLoading(true);
            try {
              await completeInterpreterJob(data.id);
              Alert.alert('Success', 'Job marked as completed!');
              await reloadJob();
            } catch (e: any) {
              const message = e.response?.data?.message || e.message || 'Failed to complete job';
              Alert.alert('Error', message);
            } finally {
              setActionLoading(false);
            }
          },
        },
      ]
    );
  }

  // Mark as DNA
  async function handleDNA() {
    if (!data || jobType !== 'interpreter') return;
    Alert.alert(
      'Did Not Attend',
      'Mark this job as Did Not Attend? This records that the client did not show up.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Confirm DNA',
          style: 'destructive',
          onPress: async () => {
            setActionLoading(true);
            try {
              await dnaInterpreterJob(data.id);
              Alert.alert('Recorded', 'Job marked as Did Not Attend');
              await reloadJob();
            } catch (e: any) {
              const message = e.response?.data?.message || e.message || 'Failed to mark DNA';
              Alert.alert('Error', message);
            } finally {
              setActionLoading(false);
            }
          },
        },
      ]
    );
  }

  // Return job to pool
  async function handleReturn() {
    if (!data || jobType !== 'interpreter') return;
    Alert.alert(
      'Return Job',
      'Return this job? It will be unassigned and become available for others.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Return Job',
          style: 'destructive',
          onPress: async () => {
            setActionLoading(true);
            try {
              await returnInterpreterJob(data.id);
              Alert.alert('Success', 'Job returned successfully');
              router.back();
            } catch (e: any) {
              const message = e.response?.data?.message || e.message || 'Failed to return job';
              Alert.alert('Error', message);
            } finally {
              setActionLoading(false);
            }
          },
        },
      ]
    );
  }

  // Load existing amount entry
  const loadAmount = useCallback(async (jobId: number) => {
    try {
      const res = await getJobAmount(jobId);
      const entry = res?.data?.data;
      if (entry) {
        setExistingAmount(entry);
        setBaseAmount(String(entry.base_amount || '0'));
        setExpenses(String(entry.expenses || '0'));
        setSurcharge(String(entry.surcharge || '0'));
        setOtherCharges(String(entry.other_charges || '0'));
        setAmountNotes(entry.notes || '');
      }
    } catch (e) {
      // No existing entry
    }
  }, []);

  // Open amount modal
  function handleOpenAmount() {
    if (!data) return;
    loadAmount(data.id);
    setShowAmountModal(true);
  }

  // Submit amount
  async function handleSubmitAmount() {
    if (!data) return;
    const base = parseFloat(baseAmount);
    if (isNaN(base) || base < 0) {
      Alert.alert('Invalid', 'Please enter a valid base amount');
      return;
    }
    setAmountLoading(true);
    try {
      await setJobAmount(data.id, {
        base_amount: base,
        expenses: parseFloat(expenses) || 0,
        surcharge: parseFloat(surcharge) || 0,
        other_charges: parseFloat(otherCharges) || 0,
        notes: amountNotes || undefined,
      });
      Alert.alert('Success', 'Amount saved successfully');
      setShowAmountModal(false);
      await loadAmount(data.id);
    } catch (e: any) {
      const msg = e.response?.data?.message || 'Failed to save amount';
      Alert.alert('Error', msg);
    } finally {
      setAmountLoading(false);
    }
  }

  const calculatedTotal = (
    (parseFloat(baseAmount) || 0) +
    (parseFloat(expenses) || 0) +
    (parseFloat(surcharge) || 0) +
    (parseFloat(otherCharges) || 0)
  ).toFixed(2);

  const styles = createStyles(colors);

  // Quote edit state (must be above early returns to satisfy React hooks rules)
  const [editingQuote, setEditingQuote] = useState(false);
  const [editQuoteLoading, setEditQuoteLoading] = useState(false);
  const [editInterpretingCost, setEditInterpretingCost] = useState('');
  const [editTravelTime, setEditTravelTime] = useState('');
  const [editTravelCost, setEditTravelCost] = useState('');
  const [editMileageMiles, setEditMileageMiles] = useState('');
  const [editMileageCost, setEditMileageCost] = useState('');
  const [editOtherCost, setEditOtherCost] = useState('');
  const [editCostDescription, setEditCostDescription] = useState('');

  if (loading) {
    return (
      <Screen>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      </Screen>
    );
  }

  if (!data) {
    return (
      <Screen>
        <View style={styles.loadingContainer}>
          <Text style={[typography.body, { color: colors.text }]}>No data</Text>
        </View>
      </Screen>
    );
  }

  const raw: any = data;
  const isInterpreter =
    typeof raw?.language === 'string' ||
    typeof raw?.appointment_date === 'string' ||
    typeof raw?.start_time === 'string' ||
    !!raw?.to_language_id;

  const status = typeof raw?.status === 'number' ? raw.status : parseInt(String(raw?.status ?? -1));
  const statusName = raw?.status_name || raw?.statusName || '';

  const clientName =
    raw?.client_name ||
    raw?.client?.user?.full_name ||
    raw?.client?.user?.fullName ||
    raw?.client?.user?.name ||
    'N/A';

  const agentName =
    raw?.agent_name ||
    raw?.agent?.user?.full_name ||
    raw?.agent?.user?.fullName ||
    raw?.agent?.user?.name ||
    null;

  const interpreterLanguage =
    raw?.language ||
    raw?.to_language?.name ||
    raw?.toLanguage?.name ||
    'N/A';

  const translatorFrom =
    (typeof raw?.from_language === 'string' ? raw.from_language : raw?.from_language?.name) ||
    raw?.fromLanguage?.name ||
    'N/A';

  const translatorTo =
    (typeof raw?.to_language === 'string' ? raw.to_language : raw?.to_language?.name) ||
    raw?.toLanguage?.name ||
    'N/A';

  const coordsLatRaw = raw?.coordinates?.lat ?? raw?.latitude ?? null;
  const coordsLngRaw = raw?.coordinates?.lng ?? raw?.longitude ?? null;

  const coordsLat = (() => {
    if (typeof coordsLatRaw === 'number') return coordsLatRaw;
    if (typeof coordsLatRaw === 'string') {
      const n = parseFloat(coordsLatRaw);
      return Number.isFinite(n) ? n : null;
    }
    return null;
  })();

  const coordsLng = (() => {
    if (typeof coordsLngRaw === 'number') return coordsLngRaw;
    if (typeof coordsLngRaw === 'string') {
      const n = parseFloat(coordsLngRaw);
      return Number.isFinite(n) ? n : null;
    }
    return null;
  })();

  const documents: Doc[] = Array.isArray(raw?.documents)
    ? raw.documents
      .map((d: any) => ({
        id: Number(d?.id),
        type: d?.type,
        name: d?.name || d?.file_name || d?.file_reference,
      }))
      .filter((d: any) => Number.isFinite(d.id))
    : [];

  // Determine action availability based on status
  const requiresQuote = raw?.requires_quote === true;
  const hasQuoted = raw?.has_quoted === true;
  const canAccept = raw?.can_accept ?? ((status === JOB_STATUS.PENDING || status === JOB_STATUS.QUOTED) && !(requiresQuote && hasQuoted));
  const showQuoteSubmitted = requiresQuote && hasQuoted;

  const handleEditQuote = () => {
    const q = raw?.agent_quote;
    if (q) {
      setEditInterpretingCost(String(q.interpreting_cost || 0));
      setEditTravelTime(String(q.travel_time || 0));
      setEditTravelCost(String(q.travel_cost || 0));
      setEditMileageMiles(String(q.mileage_miles || 0));
      setEditMileageCost(String(q.mileage_cost || 0));
      setEditOtherCost(String(q.cost || 0));
      setEditCostDescription(q.cost_description || '');
    }
    setEditingQuote(true);
  };

  const handleSaveQuoteEdit = async () => {
    if (!raw?.agent_quote?.id || !data?.id) return;
    setEditQuoteLoading(true);
    try {
      await updateInterpreterJobQuote(data.id, raw.agent_quote.id, {
        interpreting_cost: parseFloat(editInterpretingCost) || 0,
        travel_time: parseFloat(editTravelTime) || 0,
        travel_cost: parseFloat(editTravelCost) || 0,
        mileage_miles: parseFloat(editMileageMiles) || 0,
        mileage_cost: parseFloat(editMileageCost) || 0,
        cost: parseFloat(editOtherCost) || 0,
        cost_description: editCostDescription || 'N/A',
      });
      setEditingQuote(false);
      reloadJob();
    } catch (e: any) {
      Alert.alert('Error', e?.response?.data?.message || 'Failed to update quote');
    } finally {
      setEditQuoteLoading(false);
    }
  };
  const isAssigned = status === JOB_STATUS.ASSIGNED || status === JOB_STATUS.QUOTED;
  const isCompleted = status === JOB_STATUS.COMPLETED;
  const isDNA = status === JOB_STATUS.DNA;
  const isCancelled = status === JOB_STATUS.CANCELLED;
  const isRejected = status === JOB_STATUS.REJECTED;

  // Use backend-provided flags when available
  const interpreterData = data as InterpreterDetail;
  const canComplete = interpreterData.can_complete ?? (isInterpreter && isAssigned);
  const canDNA = interpreterData.can_dna ?? (isInterpreter && isAssigned);
  const canReturn = interpreterData.can_return ?? (isInterpreter && isAssigned);
  const canCheckIn = isInterpreter && isAssigned && !isCompleted && !isDNA;

  // Get status badge color
  const getStatusColor = () => {
    switch (status) {
      case JOB_STATUS.PENDING: return colors.primary + '30';
      case JOB_STATUS.ASSIGNED: return colors.primary;
      case JOB_STATUS.CANCELLED: return colors.subtext + '50';
      case JOB_STATUS.REJECTED: return colors.danger + '30';
      case JOB_STATUS.COMPLETED: return '#22c55e';
      case JOB_STATUS.QUOTED: return '#a855f7';
      case JOB_STATUS.DNA: return colors.danger;
      default: return colors.primary + '30';
    }
  };

  const getStatusTextColor = () => {
    return [JOB_STATUS.ASSIGNED, JOB_STATUS.COMPLETED, JOB_STATUS.DNA].includes(status as 1 | 4 | 6) ? '#fff' : colors.text;
  };

  return (
    <Screen>
      <Header title={raw?.reference ? `Job ${raw.reference}` : `Job #${data.id}`} />
      <ScrollView contentContainerStyle={styles.scrollContent}>
        {/* Job Header */}
        <View style={styles.headerCard}>
          {isInterpreter && (
            <>
              <Text style={styles.jobTitle}>🎤 {interpreterLanguage}</Text>
              <Text style={styles.jobSubtitle}>
                {raw?.appointment_date} {raw?.start_time ? `at ${raw.start_time}` : ''}
              </Text>
              {raw?.formatted_duration && (
                <Text style={[styles.jobSubtitle, { fontSize: 13 }]}>Duration: {raw.formatted_duration}</Text>
              )}
            </>
          )}
          {!isInterpreter && (
            <>
              <Text style={styles.jobTitle}>📄 {translatorFrom} → {translatorTo}</Text>
              <Text style={styles.jobSubtitle}>{raw?.target_date}</Text>
            </>
          )}

          {/* Status Badge */}
          <View style={[styles.statusBadge, { backgroundColor: getStatusColor() }]}>
            <Text style={[styles.statusText, { color: getStatusTextColor() }]}>{raw?.agent_status || statusName}</Text>
          </View>
        </View>

        {/* Info Cards */}
        <View style={styles.infoCard}>
          {raw?.company_name && (
            <View style={styles.infoRow}>
              <Ionicons name="business-outline" size={20} color={colors.primary} />
              <View style={styles.infoTextContainer}>
                <Text style={styles.infoLabel}>Company</Text>
                <Text style={styles.infoValue}>{raw.company_name}</Text>
              </View>
            </View>
          )}

          <View style={styles.infoRow}>
            <Ionicons name="person-outline" size={20} color={colors.primary} />
            <View style={styles.infoTextContainer}>
              <Text style={styles.infoLabel}>Client</Text>
              <Text style={styles.infoValue}>{typeof raw?.client === 'object' ? raw?.client?.name : clientName}</Text>
            </View>
          </View>

          {agentName && (
            <View style={styles.infoRow}>
              <Ionicons name="person-circle-outline" size={20} color={colors.primary} />
              <View style={styles.infoTextContainer}>
                <Text style={styles.infoLabel}>Assigned Agent</Text>
                <Text style={styles.infoValue}>{typeof raw?.agent === 'object' ? raw?.agent?.name : agentName}</Text>
              </View>
            </View>
          )}

          {raw?.skill?.name && (
            <View style={styles.infoRow}>
              <Ionicons name="ribbon-outline" size={20} color={colors.primary} />
              <View style={styles.infoTextContainer}>
                <Text style={styles.infoLabel}>Skill</Text>
                <Text style={styles.infoValue}>{raw.skill.name}</Text>
              </View>
            </View>
          )}

          {raw?.from_language?.name && raw?.to_language?.name && isInterpreter && (
            <View style={styles.infoRow}>
              <Ionicons name="language-outline" size={20} color={colors.primary} />
              <View style={styles.infoTextContainer}>
                <Text style={styles.infoLabel}>Language Pair</Text>
                <Text style={styles.infoValue}>{raw.from_language.name} → {raw.to_language.name}</Text>
              </View>
            </View>
          )}

          {raw?.client_reference && (
            <View style={styles.infoRow}>
              <Ionicons name="document-text-outline" size={20} color={colors.primary} />
              <View style={styles.infoTextContainer}>
                <Text style={styles.infoLabel}>Client Reference</Text>
                <Text style={styles.infoValue}>{raw.client_reference}</Text>
              </View>
            </View>
          )}

          {raw?.department && (
            <View style={styles.infoRow}>
              <Ionicons name="briefcase-outline" size={20} color={colors.primary} />
              <View style={styles.infoTextContainer}>
                <Text style={styles.infoLabel}>Department</Text>
                <Text style={styles.infoValue}>{raw.department}</Text>
              </View>
            </View>
          )}

          {raw?.end_time && (
            <View style={styles.infoRow}>
              <Ionicons name="time-outline" size={20} color={colors.primary} />
              <View style={styles.infoTextContainer}>
                <Text style={styles.infoLabel}>End Time</Text>
                <Text style={styles.infoValue}>{raw.end_time}</Text>
              </View>
            </View>
          )}

          {raw?.interpreter_type?.name && (
            <View style={styles.infoRow}>
              <Ionicons name="school-outline" size={20} color={colors.primary} />
              <View style={styles.infoTextContainer}>
                <Text style={styles.infoLabel}>Qualification Required</Text>
                <Text style={styles.infoValue}>{raw.interpreter_type.name}</Text>
              </View>
            </View>
          )}

          {raw?.security_type?.name && (
            <View style={styles.infoRow}>
              <Ionicons name="shield-checkmark-outline" size={20} color={colors.primary} />
              <View style={styles.infoTextContainer}>
                <Text style={styles.infoLabel}>Security Clearance</Text>
                <Text style={styles.infoValue}>{raw.security_type.name}</Text>
              </View>
            </View>
          )}

          {raw?.file_reference && (
            <View style={styles.infoRow}>
              <Ionicons name="folder-outline" size={20} color={colors.primary} />
              <View style={styles.infoTextContainer}>
                <Text style={styles.infoLabel}>File Reference</Text>
                <Text style={styles.infoValue}>{raw.file_reference}</Text>
              </View>
            </View>
          )}
        </View>

        {/* Service User Details */}
        {raw?.service_user && (raw?.service_user?.first_name || raw?.service_user?.last_name) && (
          <View style={styles.infoCard}>
            <Text style={[styles.sectionTitle, { marginBottom: 8 }]}>Service User Details</Text>
            {(raw.service_user.title || raw.service_user.first_name || raw.service_user.last_name) && (
              <View style={styles.infoRow}>
                <Ionicons name="person-outline" size={20} color={colors.primary} />
                <View style={styles.infoTextContainer}>
                  <Text style={styles.infoLabel}>Name</Text>
                  <Text style={styles.infoValue}>
                    {[raw.service_user.title, raw.service_user.first_name, raw.service_user.last_name].filter(Boolean).join(' ')}
                  </Text>
                </View>
              </View>
            )}
            {raw.service_user.date_of_birth && (
              <View style={styles.infoRow}>
                <Ionicons name="calendar-outline" size={20} color={colors.primary} />
                <View style={styles.infoTextContainer}>
                  <Text style={styles.infoLabel}>Date of Birth</Text>
                  <Text style={styles.infoValue}>{raw.service_user.date_of_birth}</Text>
                </View>
              </View>
            )}
            {raw.service_user.personal_identity_number && (
              <View style={styles.infoRow}>
                <Ionicons name="id-card-outline" size={20} color={colors.primary} />
                <View style={styles.infoTextContainer}>
                  <Text style={styles.infoLabel}>ID Number</Text>
                  <Text style={styles.infoValue}>{raw.service_user.personal_identity_number}</Text>
                </View>
              </View>
            )}
            {raw.service_user.contact_person_details && (
              <View style={styles.infoRow}>
                <Ionicons name="call-outline" size={20} color={colors.primary} />
                <View style={styles.infoTextContainer}>
                  <Text style={styles.infoLabel}>Contact Person</Text>
                  <Text style={styles.infoValue}>{raw.service_user.contact_person_details}</Text>
                </View>
              </View>
            )}
          </View>
        )}

        {/* Additional Details */}
        {(raw?.special_requirements || raw?.contact_information || raw?.notes || raw?.gender_preference != null) && (
          <View style={styles.infoCard}>
            <Text style={[styles.sectionTitle, { marginBottom: 8 }]}>Additional Details</Text>
            {raw?.gender_preference != null && (
              <View style={styles.infoRow}>
                <Ionicons name="people-outline" size={20} color={colors.primary} />
                <View style={styles.infoTextContainer}>
                  <Text style={styles.infoLabel}>Gender Preference</Text>
                  <Text style={styles.infoValue}>{raw.gender_name || (raw.gender_preference === 0 ? 'Male' : raw.gender_preference === 1 ? 'Female' : 'Either')}</Text>
                </View>
              </View>
            )}
            <View style={styles.infoRow}>
              <Ionicons name="call-outline" size={20} color={colors.primary} />
              <View style={styles.infoTextContainer}>
                <Text style={styles.infoLabel}>Contact on Arrival</Text>
                <Text style={styles.infoValue}>
                  {raw?.contact_information_is_same_as_account ? 'Same as client account' : raw?.contact_information || 'Not specified'}
                </Text>
              </View>
            </View>
            {raw?.special_requirements && (
              <View style={styles.infoRow}>
                <Ionicons name="alert-circle-outline" size={20} color={colors.primary} />
                <View style={styles.infoTextContainer}>
                  <Text style={styles.infoLabel}>Special Requirements</Text>
                  <Text style={styles.infoValue}>{raw.special_requirements}</Text>
                </View>
              </View>
            )}
            {raw?.require_qualified != null && (
              <View style={styles.infoRow}>
                <Ionicons name="ribbon-outline" size={20} color={colors.primary} />
                <View style={styles.infoTextContainer}>
                  <Text style={styles.infoLabel}>Qualification Level</Text>
                  <Text style={styles.infoValue}>
                    {raw.require_qualified === 1 ? 'Court Qualified Interpreter'
                      : raw.require_qualified === 2 ? 'Community Interpreter'
                        : raw.require_qualified === 3 ? 'Level 2,3,4 Community Interpreter'
                          : raw.require_qualified === 4 ? 'Qualified Translator'
                            : raw?.interpreter_type?.name || 'N/A'}
                  </Text>
                </View>
              </View>
            )}
            {raw?.requested_agent?.name && (
              <View style={styles.infoRow}>
                <Ionicons name="star-outline" size={20} color={colors.primary} />
                <View style={styles.infoTextContainer}>
                  <Text style={styles.infoLabel}>Requested Agent</Text>
                  <Text style={styles.infoValue}>{raw.requested_agent.name}</Text>
                </View>
              </View>
            )}
            {raw?.notes && (
              <View style={styles.infoRow}>
                <Ionicons name="chatbox-ellipses-outline" size={20} color={colors.primary} />
                <View style={styles.infoTextContainer}>
                  <Text style={styles.infoLabel}>Notes</Text>
                  <Text style={styles.infoValue}>{raw.notes}</Text>
                </View>
              </View>
            )}
            {(raw?.dna || raw?.retrn) && (
              <View style={styles.infoRow}>
                <Ionicons name="flag-outline" size={20} color={colors.danger || '#ef4444'} />
                <View style={styles.infoTextContainer}>
                  <Text style={styles.infoLabel}>Flags</Text>
                  <Text style={styles.infoValue}>
                    {[raw?.dna && 'DNA', raw?.retrn && 'Return'].filter(Boolean).join(', ')}
                  </Text>
                </View>
              </View>
            )}
          </View>
        )}

        {/* Job Timestamps */}
        {(raw?.created_at || raw?.updated_at) && (
          <View style={[styles.infoCard, { paddingVertical: spacing.sm }]}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
              {raw?.created_at && (
                <Text style={[styles.infoLabel, { fontSize: 11 }]}>
                  Submitted: {new Date(raw.created_at).toLocaleDateString()}
                </Text>
              )}
              {raw?.updated_at && (
                <Text style={[styles.infoLabel, { fontSize: 11 }]}>
                  Updated: {new Date(raw.updated_at).toLocaleDateString()}
                </Text>
              )}
            </View>
          </View>
        )}

        {/* Accept/Decline Buttons for pending jobs (no quote required) */}
        {canAccept && (
          <View style={styles.actionRow}>
            <Button
              title={actionLoading ? 'Accepting...' : 'Accept'}
              onPress={handleAccept}
              variant="primary"
              disabled={actionLoading}
              style={{ flex: 1 }}
            />
            <Button
              title={actionLoading ? 'Declining...' : 'Decline'}
              onPress={handleDecline}
              variant="secondary"
              style={{ flex: 1, backgroundColor: colors.danger }}
              textStyle={{ color: 'white' }}
              disabled={actionLoading}
            />
          </View>
        )}



        {/* Quote Submitted State */}
        {showQuoteSubmitted && (
          <View style={[styles.actionsCard, { borderLeftWidth: 3, borderLeftColor: '#22c55e' }]}>
            <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 }}>
              <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                <Ionicons name="checkmark-circle" size={22} color="#22c55e" />
                <Text style={[styles.sectionTitle, { marginLeft: 8, marginBottom: 0, color: '#22c55e' }]}>Quote Submitted ✓</Text>
              </View>
              {raw?.agent_quote && (raw.agent_quote.status === 'quote sent' || raw.agent_quote.status === 0 || raw.agent_quote.status === null) && !editingQuote && (
                <Pressable onPress={handleEditQuote} style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: colors.primary + '20', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 6 }}>
                  <Ionicons name="create-outline" size={16} color={colors.primary} />
                  <Text style={{ color: colors.primary, fontWeight: '600', fontSize: 13, marginLeft: 4 }}>Edit</Text>
                </Pressable>
              )}
            </View>

            {editingQuote ? (
              <View style={{ backgroundColor: colors.surface + '80', borderRadius: 8, padding: 12 }}>
                <Text style={[styles.sectionTitle, { fontSize: 15, marginBottom: 12 }]}>Edit Quote</Text>
                {isInterpreter && (
                  <>
                    <Text style={[styles.infoLabel, { marginBottom: 4 }]}>{isInterpreter ? 'Interpreting' : 'Translation'} Cost (per hour)</Text>
                    <TextInput style={[styles.quoteInput, { backgroundColor: colors.surface, color: colors.text, borderColor: colors.border }]} value={editInterpretingCost} onChangeText={setEditInterpretingCost} keyboardType="decimal-pad" placeholder="0.00" placeholderTextColor={colors.subtext} />

                    <Text style={[styles.infoLabel, { marginBottom: 4, marginTop: 8 }]}>Travel Time (hours)</Text>
                    <TextInput style={[styles.quoteInput, { backgroundColor: colors.surface, color: colors.text, borderColor: colors.border }]} value={editTravelTime} onChangeText={setEditTravelTime} keyboardType="decimal-pad" placeholder="0" placeholderTextColor={colors.subtext} />

                    <Text style={[styles.infoLabel, { marginBottom: 4, marginTop: 8 }]}>Travel Cost (per hour)</Text>
                    <TextInput style={[styles.quoteInput, { backgroundColor: colors.surface, color: colors.text, borderColor: colors.border }]} value={editTravelCost} onChangeText={setEditTravelCost} keyboardType="decimal-pad" placeholder="0.00" placeholderTextColor={colors.subtext} />

                    <Text style={[styles.infoLabel, { marginBottom: 4, marginTop: 8 }]}>Mileage (miles)</Text>
                    <TextInput style={[styles.quoteInput, { backgroundColor: colors.surface, color: colors.text, borderColor: colors.border }]} value={editMileageMiles} onChangeText={setEditMileageMiles} keyboardType="decimal-pad" placeholder="0" placeholderTextColor={colors.subtext} />

                    <Text style={[styles.infoLabel, { marginBottom: 4, marginTop: 8 }]}>Mileage Cost (per mile)</Text>
                    <TextInput style={[styles.quoteInput, { backgroundColor: colors.surface, color: colors.text, borderColor: colors.border }]} value={editMileageCost} onChangeText={setEditMileageCost} keyboardType="decimal-pad" placeholder="0.00" placeholderTextColor={colors.subtext} />
                  </>
                )}

                <Text style={[styles.infoLabel, { marginBottom: 4, marginTop: 8 }]}>Other Cost</Text>
                <TextInput style={[styles.quoteInput, { backgroundColor: colors.surface, color: colors.text, borderColor: colors.border }]} value={editOtherCost} onChangeText={setEditOtherCost} keyboardType="decimal-pad" placeholder="0.00" placeholderTextColor={colors.subtext} />

                <Text style={[styles.infoLabel, { marginBottom: 4, marginTop: 8 }]}>Cost Description</Text>
                <TextInput style={[styles.quoteInput, { backgroundColor: colors.surface, color: colors.text, borderColor: colors.border }]} value={editCostDescription} onChangeText={setEditCostDescription} placeholder="Description" placeholderTextColor={colors.subtext} />

                <View style={{ flexDirection: 'row', gap: 8, marginTop: 16 }}>
                  <Pressable onPress={() => setEditingQuote(false)} style={{ flex: 1, paddingVertical: 10, borderRadius: 8, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border, alignItems: 'center' }}>
                    <Text style={{ color: colors.text, fontWeight: '600' }}>Cancel</Text>
                  </Pressable>
                  <Pressable onPress={handleSaveQuoteEdit} disabled={editQuoteLoading} style={{ flex: 1, paddingVertical: 10, borderRadius: 8, backgroundColor: colors.primary, alignItems: 'center', opacity: editQuoteLoading ? 0.6 : 1 }}>
                    <Text style={{ color: '#fff', fontWeight: '600' }}>{editQuoteLoading ? 'Saving...' : 'Save Changes'}</Text>
                  </Pressable>
                </View>
              </View>
            ) : (
              <>
                <Text style={[styles.infoLabel, { marginBottom: 8 }]}>Your quote has been submitted. You will be notified when the admin reviews it.</Text>
                {raw?.agent_quote && (() => {
                  const q = raw.agent_quote;
                  const interpCost = parseFloat(String(q.interpreting_cost)) || 0;
                  const trvlTime = parseFloat(String(q.travel_time)) || 0;
                  const trvlCost = parseFloat(String(q.travel_cost)) || 0;
                  const milMiles = parseFloat(String(q.mileage_miles)) || 0;
                  const milCost = parseFloat(String(q.mileage_cost)) || 0;
                  const otherCost = parseFloat(String(q.cost)) || 0;
                  const jobHrs = (parseFloat(raw?.duration_hours) || 0) + (parseFloat(raw?.duration_minutes) || 0) / 60 || 1;
                  const interpTotal = interpCost * jobHrs;
                  const travelTotal = trvlTime * trvlCost;
                  const mileageTotal = milMiles * milCost;
                  const grandTotal = interpTotal + travelTotal + mileageTotal + otherCost;
                  return (
                    <View style={{ backgroundColor: colors.surface + '80', borderRadius: 8, padding: 12 }}>
                      {q.cost_description && q.cost_description !== 'N/A' && (
                        <Text style={styles.infoValue}>Description: {q.cost_description}</Text>
                      )}
                      {isInterpreter && (
                        <>
                          <Text style={[styles.infoLabel, { marginTop: 4 }]}>Interpreting: £{interpTotal.toFixed(2)}</Text>
                          <Text style={styles.infoLabel}>Travel: £{travelTotal.toFixed(2)}</Text>
                          <Text style={styles.infoLabel}>Mileage: £{mileageTotal.toFixed(2)}</Text>
                          {otherCost > 0 && <Text style={styles.infoLabel}>Other: £{otherCost.toFixed(2)}</Text>}
                        </>
                      )}
                      <Text style={[styles.infoValue, { fontWeight: '700', marginTop: 6 }]}>Total: £{grandTotal.toFixed(2)}</Text>
                    </View>
                  );
                })()}
              </>
            )}
          </View>
        )}

        {/* Map Card for interpreter jobs with address */}
        {isInterpreter && (interpreterData.address || raw?.address_line_1 || raw?.address_line_2 || raw?.postcode) && (
          <MapCard
            address={
              interpreterData.address || {
                line_1: raw?.address_line_1,
                line_2: raw?.address_line_2,
                county: raw?.county,
                postcode: raw?.postcode,
              }
            }
            lat={coordsLat}
            lng={coordsLng}
          />
        )}

        {/* Assigned Job Actions */}
        {isAssigned && isInterpreter && !isCompleted && !isDNA && (
          <View style={styles.actionsCard}>
            <Text style={styles.sectionTitle}>Job Actions</Text>

            {/* Check In/Out */}
            {canCheckIn && (
              <View style={styles.actionRow}>
                <Button
                  title={actionLoading ? '...' : '📍 Check In'}
                  onPress={() => handleAction('check-in')}
                  variant="secondary"
                  disabled={actionLoading}
                  style={{ flex: 1 }}
                />
                <Button
                  title={actionLoading ? '...' : '📍 Check Out'}
                  onPress={() => handleAction('check-out')}
                  variant="outline"
                  disabled={actionLoading}
                  style={{ flex: 1 }}
                />
              </View>
            )}

            {/* Complete/DNA */}
            <View style={styles.actionRow}>
              {canComplete && (
                <Button
                  title={actionLoading ? '...' : '✓ Complete'}
                  onPress={handleComplete}
                  variant="primary"
                  disabled={actionLoading}
                  style={{ flex: 1 }}
                />
              )}
              {canDNA && (
                <Button
                  title={actionLoading ? '...' : 'DNA'}
                  onPress={handleDNA}
                  variant="secondary"
                  style={{ flex: 1, backgroundColor: colors.danger }}
                  textStyle={{ color: 'white' }}
                  disabled={actionLoading}
                />
              )}
            </View>

            {/* Upload Timesheet */}
            <Button
              title={actionLoading ? 'Uploading...' : '📄 Upload Signed Timesheet'}
              onPress={handleUpload}
              variant="outline"
              disabled={actionLoading}
            />

            {/* Travel Expenses */}
            <Button
              title="💰 Travel Expenses"
              onPress={() => setShowExpenseModal(true)}
              variant="outline"
              disabled={actionLoading}
            />

            {/* Electronic Signature */}
            <Button
              title="✍️ Get Electronic Signature"
              onPress={() => {
                const timesheetId = (data as InterpreterDetail).timesheet?.id;
                if (!timesheetId) {
                  Alert.alert('Error', 'No timesheet found for this job. Please try again.');
                  return;
                }
                router.push(`/jobs/sign/${timesheetId}` as any);
              }}
              variant="primary"
              style={{ backgroundColor: '#22c55e' }}
              disabled={actionLoading}
            />

            {/* Return Job */}
            {canReturn && (
              <Button
                title={actionLoading ? '...' : 'Return Job to Pool'}
                onPress={handleReturn}
                variant="outline"
                disabled={actionLoading}
              />
            )}
          </View>
        )}

        {/* Completed Status + Amount Entry */}
        {isCompleted && (
          <>
            <View style={[styles.statusCard, { backgroundColor: '#22c55e20' }]}>
              <Ionicons name="checkmark-circle" size={24} color="#22c55e" />
              <Text style={[styles.statusCardText, { color: '#22c55e' }]}>Job Completed</Text>
            </View>

            {/* Amount Entry Card */}
            <View style={styles.actionsCard}>
              <Text style={styles.sectionTitle}>Amount / Fee Entry</Text>
              {existingAmount ? (
                <View style={{ gap: 6 }}>
                  <View style={styles.amountRow}>
                    <Text style={styles.amountLabel}>Base Amount</Text>
                    <Text style={styles.amountValue}>£{parseFloat(existingAmount.base_amount).toFixed(2)}</Text>
                  </View>
                  {parseFloat(existingAmount.expenses) > 0 && (
                    <View style={styles.amountRow}>
                      <Text style={styles.amountLabel}>Expenses</Text>
                      <Text style={styles.amountValue}>£{parseFloat(existingAmount.expenses).toFixed(2)}</Text>
                    </View>
                  )}
                  {parseFloat(existingAmount.surcharge) > 0 && (
                    <View style={styles.amountRow}>
                      <Text style={styles.amountLabel}>Surcharge</Text>
                      <Text style={styles.amountValue}>£{parseFloat(existingAmount.surcharge).toFixed(2)}</Text>
                    </View>
                  )}
                  {parseFloat(existingAmount.other_charges) > 0 && (
                    <View style={styles.amountRow}>
                      <Text style={styles.amountLabel}>Other Charges</Text>
                      <Text style={styles.amountValue}>£{parseFloat(existingAmount.other_charges).toFixed(2)}</Text>
                    </View>
                  )}
                  <View style={[styles.amountRow, { borderTopWidth: 1, borderTopColor: colors.border, paddingTop: 8, marginTop: 4 }]}>
                    <Text style={[styles.amountLabel, { fontWeight: '700', fontSize: 16 }]}>Total</Text>
                    <Text style={[styles.amountValue, { fontWeight: '700', fontSize: 16, color: colors.primary }]}>£{parseFloat(existingAmount.total_amount).toFixed(2)}</Text>
                  </View>
                  {existingAmount.invoiced && (
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 4 }}>
                      <Ionicons name="checkmark-circle" size={16} color="#22c55e" />
                      <Text style={{ color: '#22c55e', fontSize: 12, fontWeight: '600' }}>Invoiced</Text>
                    </View>
                  )}
                </View>
              ) : (
                <Text style={{ color: colors.subtext, fontSize: 14, marginBottom: 8 }}>No amount entered yet</Text>
              )}
              <Button
                title={existingAmount ? '✏️ Edit Amount' : '💷 Add Amount'}
                onPress={handleOpenAmount}
                variant="outline"
                style={{ marginTop: 8 }}
              />
            </View>

            {/* Timesheet Actions for Completed Jobs */}
            {isInterpreter && (
              <View style={[styles.actionsCard, { marginTop: 0 }]}>
                <Text style={styles.sectionTitle}>Timesheet</Text>
                <Button
                  title={actionLoading ? 'Uploading...' : '📄 Upload Signed Timesheet'}
                  onPress={handleUpload}
                  variant="outline"
                  disabled={actionLoading}
                />
                <Button
                  title="✍️ Get Electronic Signature"
                  onPress={() => {
                    const timesheetId = (data as InterpreterDetail).timesheet?.id;
                    if (!timesheetId) {
                      Alert.alert('Error', 'No timesheet found for this job. Please try again.');
                      return;
                    }
                    router.push(`/jobs/sign/${timesheetId}` as any);
                  }}
                  variant="primary"
                  style={{ backgroundColor: '#22c55e' }}
                  disabled={actionLoading}
                />
              </View>
            )}
          </>
        )}

        {/* DNA Status */}
        {isDNA && (
          <View style={[styles.statusCard, { backgroundColor: colors.danger + '20' }]}>
            <Ionicons name="alert-circle" size={24} color={colors.danger} />
            <Text style={[styles.statusCardText, { color: colors.danger }]}>Did Not Attend</Text>
          </View>
        )}

        {/* Cancelled Status */}
        {isCancelled && (
          <View style={[styles.statusCard, { backgroundColor: colors.subtext + '20' }]}>
            <Ionicons name="close-circle" size={24} color={colors.subtext} />
            <Text style={[styles.statusCardText, { color: colors.subtext }]}>Cancelled</Text>
          </View>
        )}

        {/* Documents */}
        {documents.length > 0 && (
          <View style={styles.documentsSection}>
            <Text style={styles.sectionTitle}>Documents</Text>
            <DocumentsList items={documents} />
          </View>
        )}
      </ScrollView>

      {/* Amount Entry Modal */}
      <Modal
        visible={showAmountModal}
        animationType="slide"
        transparent={true}
        onRequestClose={() => setShowAmountModal(false)}
      >
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
          style={{ flex: 1 }}
        >
          <Pressable
            style={styles.modalOverlay}
            onPress={() => setShowAmountModal(false)}
          >
            <Pressable style={styles.modalContent} onPress={(e) => e.stopPropagation()}>
              <View style={styles.modalHeader}>
                <Text style={styles.modalTitle}>Amount Entry</Text>
                <Pressable onPress={() => setShowAmountModal(false)}>
                  <Ionicons name="close" size={24} color={colors.text} />
                </Pressable>
              </View>

              <ScrollView style={{ maxHeight: 400 }} contentContainerStyle={{ gap: 12 }}>
                <View>
                  <Text style={styles.modalLabel}>Base Amount (£) *</Text>
                  <TextInput
                    style={styles.modalInput}
                    value={baseAmount}
                    onChangeText={setBaseAmount}
                    keyboardType="decimal-pad"
                    placeholder="0.00"
                    placeholderTextColor={colors.subtext}
                  />
                </View>
                <View>
                  <Text style={styles.modalLabel}>Expenses (£)</Text>
                  <TextInput
                    style={styles.modalInput}
                    value={expenses}
                    onChangeText={setExpenses}
                    keyboardType="decimal-pad"
                    placeholder="0.00"
                    placeholderTextColor={colors.subtext}
                  />
                </View>
                <View>
                  <Text style={styles.modalLabel}>Surcharge (£)</Text>
                  <TextInput
                    style={styles.modalInput}
                    value={surcharge}
                    onChangeText={setSurcharge}
                    keyboardType="decimal-pad"
                    placeholder="0.00"
                    placeholderTextColor={colors.subtext}
                  />
                </View>
                <View>
                  <Text style={styles.modalLabel}>Other Charges (£)</Text>
                  <TextInput
                    style={styles.modalInput}
                    value={otherCharges}
                    onChangeText={setOtherCharges}
                    keyboardType="decimal-pad"
                    placeholder="0.00"
                    placeholderTextColor={colors.subtext}
                  />
                </View>
                <View>
                  <Text style={styles.modalLabel}>Notes (optional)</Text>
                  <TextInput
                    style={[styles.modalInput, { height: 60, textAlignVertical: 'top' }]}
                    value={amountNotes}
                    onChangeText={setAmountNotes}
                    placeholder="Any notes..."
                    placeholderTextColor={colors.subtext}
                    multiline
                  />
                </View>

                {/* Total */}
                <View style={[styles.amountRow, { borderTopWidth: 1, borderTopColor: colors.border, paddingTop: 12, marginTop: 4 }]}>
                  <Text style={{ fontSize: 18, fontWeight: '700', color: colors.text }}>Total</Text>
                  <Text style={{ fontSize: 18, fontWeight: '700', color: colors.primary }}>£{calculatedTotal}</Text>
                </View>
              </ScrollView>

              <View style={{ flexDirection: 'row', gap: 12, marginTop: 16 }}>
                <Button
                  title="Cancel"
                  onPress={() => setShowAmountModal(false)}
                  variant="outline"
                  style={{ flex: 1 }}
                />
                <Button
                  title={amountLoading ? 'Saving...' : 'Save Amount'}
                  onPress={handleSubmitAmount}
                  variant="primary"
                  style={{ flex: 1 }}
                  disabled={amountLoading}
                />
              </View>
            </Pressable>
          </Pressable>
        </KeyboardAvoidingView>
      </Modal>

      {/* Quote Submission Modal */}
      <Modal
        visible={showQuoteModal}
        animationType="slide"
        transparent={true}
        onRequestClose={() => setShowQuoteModal(false)}
      >
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
          style={{ flex: 1 }}
        >
          <Pressable style={styles.modalOverlay} onPress={() => setShowQuoteModal(false)}>
            <Pressable style={styles.modalContent} onPress={(e) => e.stopPropagation()}>
              <View style={styles.modalHeader}>
                <Text style={styles.modalTitle}>Submit Quote</Text>
                <Pressable onPress={() => setShowQuoteModal(false)}>
                  <Ionicons name="close" size={24} color={colors.text} />
                </Pressable>
              </View>

              <ScrollView style={{ maxHeight: 400 }}>
                {/* Interpreter-specific fields */}
                {isInterpreter && (
                  <>
                    <Text style={styles.modalLabel}>{isInterpreter ? 'Interpreting Cost (£/hr)' : 'Translation Cost (£)'}</Text>
                    <TextInput
                      style={[styles.modalInput, { marginBottom: 12 }]}
                      value={interpretingCost}
                      onChangeText={setInterpretingCost}
                      keyboardType="decimal-pad"
                      placeholder="0.00"
                      placeholderTextColor={colors.subtext}
                    />

                    <Text style={styles.modalLabel}>Travel Time (hrs)</Text>
                    <TextInput
                      style={[styles.modalInput, { marginBottom: 12 }]}
                      value={travelTime}
                      onChangeText={setTravelTime}
                      keyboardType="decimal-pad"
                      placeholder="0.00"
                      placeholderTextColor={colors.subtext}
                    />

                    <Text style={styles.modalLabel}>Travel Cost (£/hr)</Text>
                    <TextInput
                      style={[styles.modalInput, { marginBottom: 12 }]}
                      value={travelCost}
                      onChangeText={setTravelCost}
                      keyboardType="decimal-pad"
                      placeholder="0.00"
                      placeholderTextColor={colors.subtext}
                    />

                    <Text style={styles.modalLabel}>Mileage (miles)</Text>
                    <TextInput
                      style={[styles.modalInput, { marginBottom: 12 }]}
                      value={mileageMiles}
                      onChangeText={setMileageMiles}
                      keyboardType="decimal-pad"
                      placeholder="0.00"
                      placeholderTextColor={colors.subtext}
                    />

                    <Text style={styles.modalLabel}>Mileage Cost (£/mile)</Text>
                    <TextInput
                      style={[styles.modalInput, { marginBottom: 12 }]}
                      value={mileageCost}
                      onChangeText={setMileageCost}
                      keyboardType="decimal-pad"
                      placeholder="0.00"
                      placeholderTextColor={colors.subtext}
                    />
                  </>
                )}

                <Text style={styles.modalLabel}>Cost Description</Text>
                <TextInput
                  style={[styles.modalInput, { marginBottom: 12 }]}
                  value={quoteCostDescription}
                  onChangeText={setQuoteCostDescription}
                  placeholder="e.g. Parking, Tolls, etc."
                  placeholderTextColor={colors.subtext}
                />

                <Text style={styles.modalLabel}>{isInterpreter ? 'Other Costs (£)' : 'Total Cost (£)'}</Text>
                <TextInput
                  style={[styles.modalInput, { marginBottom: 16 }]}
                  value={quoteCost}
                  onChangeText={setQuoteCost}
                  keyboardType="decimal-pad"
                  placeholder="0.00"
                  placeholderTextColor={colors.subtext}
                />

                {/* Calculated totals for interpreter */}
                {isInterpreter && (
                  <View style={{ backgroundColor: colors.bg, borderRadius: 8, padding: 12, marginBottom: 12 }}>
                    <Text style={[styles.modalLabel, { marginBottom: 8, color: colors.primary }]}>Quote Summary</Text>
                    <View style={styles.amountRow}>
                      <Text style={styles.amountLabel}>Interpreting</Text>
                      <Text style={styles.amountValue}>£{((parseFloat(interpretingCost) || 0) * (((data as any)?.totalHours) || ((parseFloat((data as any)?.duration_hours) || 0) + (parseFloat((data as any)?.duration_minutes) || 0) / 60) || 1)).toFixed(2)}</Text>
                    </View>
                    <View style={styles.amountRow}>
                      <Text style={styles.amountLabel}>Travel</Text>
                      <Text style={styles.amountValue}>£{((parseFloat(travelTime) || 0) * (parseFloat(travelCost) || 0)).toFixed(2)}</Text>
                    </View>
                    <View style={styles.amountRow}>
                      <Text style={styles.amountLabel}>Mileage</Text>
                      <Text style={styles.amountValue}>£{((parseFloat(mileageMiles) || 0) * (parseFloat(mileageCost) || 0)).toFixed(2)}</Text>
                    </View>
                    <View style={styles.amountRow}>
                      <Text style={styles.amountLabel}>Other costs</Text>
                      <Text style={styles.amountValue}>£{(parseFloat(quoteCost) || 0).toFixed(2)}</Text>
                    </View>
                    <View style={[styles.amountRow, { borderTopWidth: 1, borderTopColor: colors.border, paddingTop: 8, marginTop: 8 }]}>
                      <Text style={[styles.amountLabel, { fontWeight: '700' }]}>Grand Total</Text>
                      <Text style={[styles.amountValue, { fontWeight: '700', color: colors.primary }]}>
                        £{(
                          (parseFloat(interpretingCost) || 0) * (((parseFloat((data as any)?.duration_hours) || 0) + (parseFloat((data as any)?.duration_minutes) || 0) / 60) || 1) +
                          (parseFloat(travelTime) || 0) * (parseFloat(travelCost) || 0) +
                          (parseFloat(mileageMiles) || 0) * (parseFloat(mileageCost) || 0) +
                          (parseFloat(quoteCost) || 0)
                        ).toFixed(2)}
                      </Text>
                    </View>
                  </View>
                )}
              </ScrollView>

              <View style={[styles.actionRow, { marginTop: 12 }]}>
                <Button
                  title="Cancel"
                  onPress={() => setShowQuoteModal(false)}
                  variant="outline"
                  style={{ flex: 1 }}
                />
                <Button
                  title={quoteLoading ? 'Submitting...' : 'Submit Quote'}
                  onPress={handleSubmitQuote}
                  variant="primary"
                  style={{ flex: 1 }}
                  disabled={quoteLoading}
                />
              </View>
            </Pressable>
          </Pressable>
        </KeyboardAvoidingView>
      </Modal>

      {/* Expense Entry Modal */}
      <Modal
        visible={showExpenseModal}
        animationType="slide"
        transparent={true}
        onRequestClose={() => setShowExpenseModal(false)}
      >
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
          style={{ flex: 1 }}
        >
          <Pressable style={styles.modalOverlay} onPress={() => setShowExpenseModal(false)}>
            <Pressable style={styles.modalContent} onPress={(e) => e.stopPropagation()}>
              <View style={styles.modalHeader}>
                <Text style={styles.modalTitle}>Travel Expenses</Text>
                <Pressable onPress={() => setShowExpenseModal(false)}>
                  <Ionicons name="close" size={24} color={colors.text} />
                </Pressable>
              </View>

              <ScrollView style={{ maxHeight: 400 }}>
                <Text style={styles.modalLabel}>Base Amount (£)</Text>
                <TextInput
                  style={[styles.modalInput, { marginBottom: 12 }]}
                  value={expenseBaseAmount}
                  onChangeText={setExpenseBaseAmount}
                  keyboardType="decimal-pad"
                  placeholder="0.00"
                  placeholderTextColor={colors.subtext}
                />

                <Text style={[styles.modalLabel, { marginBottom: 8 }]}>Expense Items</Text>
                {expenseItems.map((item, index) => (
                  <View key={index} style={{ flexDirection: 'row', gap: 8, marginBottom: 8 }}>
                    <TextInput
                      style={[styles.modalInput, { flex: 2 }]}
                      value={item.type}
                      onChangeText={(text) => {
                        const newItems = [...expenseItems];
                        newItems[index].type = text;
                        setExpenseItems(newItems);
                      }}
                      placeholder="Type (e.g. Parking)"
                      placeholderTextColor={colors.subtext}
                    />
                    <TextInput
                      style={[styles.modalInput, { flex: 1 }]}
                      value={item.value}
                      onChangeText={(text) => {
                        const newItems = [...expenseItems];
                        newItems[index].value = text;
                        setExpenseItems(newItems);
                      }}
                      keyboardType="decimal-pad"
                      placeholder="0.00"
                      placeholderTextColor={colors.subtext}
                    />
                    {expenseItems.length > 1 && (
                      <Pressable
                        onPress={() => {
                          const newItems = expenseItems.filter((_, i) => i !== index);
                          setExpenseItems(newItems);
                        }}
                        style={{ alignSelf: 'center' }}
                      >
                        <Ionicons name="remove-circle-outline" size={24} color={colors.danger} />
                      </Pressable>
                    )}
                  </View>
                ))}
                <Pressable
                  onPress={() => setExpenseItems([...expenseItems, { type: '', value: '' }])}
                  style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 12 }}
                >
                  <Ionicons name="add-circle-outline" size={20} color={colors.primary} />
                  <Text style={{ color: colors.primary, marginLeft: 4, fontSize: 14 }}>Add expense item</Text>
                </Pressable>

                <Text style={styles.modalLabel}>Notes</Text>
                <TextInput
                  style={[styles.modalInput, { marginBottom: 12, minHeight: 60, textAlignVertical: 'top' }]}
                  value={expenseNotes}
                  onChangeText={setExpenseNotes}
                  multiline
                  placeholder="Optional notes..."
                  placeholderTextColor={colors.subtext}
                />

                {/* Total */}
                <View style={{ backgroundColor: colors.bg, borderRadius: 8, padding: 12, marginBottom: 12 }}>
                  <View style={styles.amountRow}>
                    <Text style={[styles.amountLabel, { fontWeight: '700' }]}>Total</Text>
                    <Text style={[styles.amountValue, { fontWeight: '700', color: colors.primary }]}>
                      £{(
                        (parseFloat(expenseBaseAmount) || 0) +
                        expenseItems.reduce((sum, item) => sum + (parseFloat(item.value) || 0), 0)
                      ).toFixed(2)}
                    </Text>
                  </View>
                </View>
              </ScrollView>

              <View style={[styles.actionRow, { marginTop: 12 }]}>
                <Button
                  title="Cancel"
                  onPress={() => setShowExpenseModal(false)}
                  variant="outline"
                  style={{ flex: 1 }}
                />
                <Button
                  title={expenseLoading ? 'Saving...' : 'Save Expenses'}
                  onPress={handleSubmitExpenses}
                  variant="primary"
                  style={{ flex: 1 }}
                  disabled={expenseLoading}
                />
              </View>
            </Pressable>
          </Pressable>
        </KeyboardAvoidingView>
      </Modal>
    </Screen>
  );
}

const createStyles = (colors: any) => StyleSheet.create({
  loadingContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  scrollContent: {
    padding: spacing.lg,
    gap: spacing.md,
  },
  headerCard: {
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    padding: spacing.lg,
    borderWidth: 1,
    borderColor: colors.border,
  },
  jobTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.text,
  },
  jobSubtitle: {
    fontSize: 14,
    color: colors.subtext,
    marginTop: 4,
  },
  statusBadge: {
    alignSelf: 'flex-start',
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    borderRadius: radius.sm,
    marginTop: spacing.md,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
    textTransform: 'capitalize',
  },
  infoCard: {
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    padding: spacing.lg,
    borderWidth: 1,
    borderColor: colors.border,
    gap: spacing.md,
  },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
  },
  infoTextContainer: {
    flex: 1,
  },
  infoLabel: {
    fontSize: 12,
    color: colors.subtext,
  },
  infoValue: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.text,
  },
  quoteInput: {
    borderWidth: 1,
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    fontSize: 15,
  },
  actionsCard: {
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    padding: spacing.lg,
    borderWidth: 1,
    borderColor: colors.border,
    gap: spacing.md,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: spacing.sm,
  },
  actionRow: {
    flexDirection: 'row',
    gap: spacing.md,
  },
  statusCard: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.lg,
    borderRadius: radius.lg,
    gap: spacing.sm,
  },
  statusCardText: {
    fontSize: 16,
    fontWeight: '700',
  },
  documentsSection: {
    marginTop: spacing.md,
  },
  // Amount styles
  amountRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  amountLabel: {
    fontSize: 14,
    color: colors.subtext,
  },
  amountValue: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.text,
  },
  // Modal styles
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: spacing.lg,
    paddingBottom: spacing.xl,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.lg,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.text,
  },
  modalLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 4,
  },
  modalInput: {
    backgroundColor: colors.bg,
    borderRadius: radius.md,
    padding: spacing.md,
    fontSize: 16,
    color: colors.text,
    borderWidth: 1,
    borderColor: colors.border,
  },
});
