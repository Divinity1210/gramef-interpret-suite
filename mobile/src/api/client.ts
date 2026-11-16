import axios from 'axios';
import axiosRetry from 'axios-retry';
import { API_BASE_URL } from '../config';
import { useAuthStore } from '../state/auth';
import AsyncStorage from '@react-native-async-storage/async-storage';

const instance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000, // 30 second timeout
});

// Configure retry logic for network failures (NOT 500 server bugs)
axiosRetry(instance, {
  retries: 1, // Retry failed requests once
  retryDelay: axiosRetry.exponentialDelay,
  retryCondition: (error) => {
    // Only retry on network errors or 503 (service unavailable) — NOT 500 (server bug)
    return axiosRetry.isNetworkError(error) ||
      error.response?.status === 503;
  },
});

function isWeb() {
  return typeof window !== 'undefined' && typeof document !== 'undefined';
}

async function cacheSet(key: string, value: string) {
  try {
    if (isWeb() && window.localStorage) {
      window.localStorage.setItem(key, value);
      return;
    }
    await AsyncStorage.setItem(key, value);
  } catch { }
}

async function cacheGet(key: string) {
  try {
    if (isWeb() && window.localStorage) {
      return window.localStorage.getItem(key);
    }
    return await AsyncStorage.getItem(key);
  } catch {
    return null;
  }
}

function stableStringify(value: any) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return JSON.stringify(value ?? null);
  }
  const keys = Object.keys(value).sort();
  const out: Record<string, any> = {};
  for (const k of keys) out[k] = value[k];
  return JSON.stringify(out);
}

async function cachedGet<T = any>(
  url: string,
  {
    params,
    ttlMs = 5 * 60_000,
    allowStale = true,
  }: { params?: Record<string, any>; ttlMs?: number; allowStale?: boolean } = {}
) {
  const key = `cache:v1:${url}:${stableStringify(params)}`;
  const now = Date.now();
  try {
    const res = await instance.get<T>(url, { params });
    await cacheSet(key, JSON.stringify({ ts: now, data: res.data }));
    return res;
  } catch (e: any) {
    const raw = await cacheGet(key);
    if (!raw) throw e;
    try {
      const parsed = JSON.parse(raw);
      const ts = Number(parsed?.ts || 0);
      const age = now - ts;
      if (!allowStale && age > ttlMs) {
        throw e;
      }
      return {
        data: parsed?.data,
        status: 200,
        statusText: 'OK',
        headers: {},
        config: { url, params },
        request: null,
      } as any;
    } catch {
      throw e;
    }
  }
}

let hydrationPromise: Promise<void> | null = null;

async function ensureAuthReady() {
  const state = useAuthStore.getState();
  if (state.isReady) return;
  if (!hydrationPromise) {
    hydrationPromise = state
      .hydrate()
      .catch(() => { })
      .finally(() => {
        hydrationPromise = null;
      });
  }
  await hydrationPromise;
}

instance.interceptors.request.use(async (config) => {
  // Skip automatic token injection if the request already has an explicit Authorization header
  // (e.g. when me() is called with a fresh token during login)
  if ((config as any).__skipAuthInterceptor) {
    return config;
  }
  await ensureAuthReady();
  const token = useAuthStore.getState().token;
  if (token) {
    config.headers = config.headers || {};
    (config.headers as any)['Authorization'] = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor: clear stale tokens on 401 errors
instance.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Don't clear auth on login attempts (those have their own error handling)
      const url = error.config?.url || '';
      if (!url.includes('/auth/login')) {
        const state = useAuthStore.getState();
        if (state.token) {
          console.log('API returned 401, clearing stale auth token');
          state.clear();
        }
      }
    }
    return Promise.reject(error);
  }
);


export function login(email: string, password: string, deviceName?: string) {
  return instance.post('/auth/login', {
    email,
    password,
    device_name: deviceName || 'mobile',
  }, {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  });
}

export function register(data: {
  firstName: string;
  lastName: string;
  email: string;
  password: string;
  role: 'agent' | 'client';
  phone?: string;
  title?: string;
}) {
  return instance.post('/auth/register', {
    first_name: data.firstName,
    last_name: data.lastName,
    email: data.email,
    password: data.password,
    role: data.role,
    phone: data.phone,
    title: data.title,
  }, {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  });
}

export function me(token?: string) {
  const headers: any = { Accept: 'application/json' };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  // When an explicit token is provided, skip the interceptor's automatic token injection
  // to prevent it from overwriting with a stale token from the auth store
  return instance.get('/auth/me', {
    headers,
    __skipAuthInterceptor: !!token,
  } as any);
}

export function getInterpreterJobs(params?: Record<string, any>) {
  return cachedGet('/interpreter-jobs', { params, ttlMs: 60_000, allowStale: true });
}

export function getTranslatorJobs(params?: Record<string, any>) {
  return cachedGet('/translator-jobs', { params, ttlMs: 60_000, allowStale: true });
}

export function getInterpreterJob(id: number) {
  return cachedGet(`/interpreter-jobs/${id}`, { ttlMs: 24 * 60 * 60_000, allowStale: true });
}

export function getTranslatorJob(id: number) {
  return cachedGet(`/translator-jobs/${id}`, { ttlMs: 24 * 60 * 60_000, allowStale: true });
}

export function getLanguages() {
  return cachedGet('/languages', { ttlMs: 7 * 24 * 60 * 60_000, allowStale: true });
}

export function getStatuses() {
  return cachedGet('/statuses', { ttlMs: 7 * 24 * 60 * 60_000, allowStale: true });
}

export function getDocumentSignedUrl(id: number) {
  return instance.get(`/documents/${id}`);
}

export function updateInterpreterJob(id: number, data: any) {
  return instance.put(`/interpreter-jobs/${id}`, data);
}

export function acceptInterpreterJob(id: number) {
  return instance.post(`/interpreter-jobs/${id}/accept`);
}

export function declineInterpreterJob(id: number, reason?: string) {
  return instance.post(`/interpreter-jobs/${id}/decline`, { reason });
}

export function completeInterpreterJob(id: number) {
  return instance.post(`/interpreter-jobs/${id}/complete`);
}

export function dnaInterpreterJob(id: number) {
  return instance.post(`/interpreter-jobs/${id}/dna`);
}

export function returnInterpreterJob(id: number) {
  return instance.post(`/interpreter-jobs/${id}/return`);
}

export function checkInInterpreterJob(id: number, latitude: number, longitude: number) {
  return instance.post(`/interpreter-jobs/${id}/check-in`, { latitude, longitude });
}

export function checkOutInterpreterJob(id: number, latitude: number, longitude: number) {
  return instance.post(`/interpreter-jobs/${id}/check-out`, { latitude, longitude });
}

export function getJobAmount(jobId: number) {
  return instance.get(`/interpreter-jobs/${jobId}/amount`);
}

export function setJobAmount(jobId: number, data: {
  base_amount: number;
  expenses?: number;
  surcharge?: number;
  other_charges?: number;
  notes?: string;
}) {
  return instance.post(`/interpreter-jobs/${jobId}/amount`, data);
}

export function acceptTranslatorJob(id: number) {
  return instance.post(`/translator-jobs/${id}/accept`);
}

export function declineTranslatorJob(id: number, reason?: string) {
  return instance.post(`/translator-jobs/${id}/decline`, { reason });
}

// Quote submission (interpreter jobs - 7 fields)
export function submitInterpreterJobQuote(id: number, data: {
  interpreting_cost: number;
  travel_time: number;
  travel_cost: number;
  mileage_miles: number;
  mileage_cost: number;
  cost_description: string;
  cost: number;
}) {
  return instance.post(`/interpreter-jobs/${id}/quotes`, data);
}

export function getInterpreterJobQuotes(id: number) {
  return instance.get(`/interpreter-jobs/${id}/quotes`);
}

export function updateInterpreterJobQuote(jobId: number, quoteId: number, data: {
  interpreting_cost: number;
  travel_time: number;
  travel_cost: number;
  mileage_miles: number;
  mileage_cost: number;
  cost_description: string;
  cost: number;
}) {
  return instance.put(`/interpreter-jobs/${jobId}/quotes/${quoteId}`, data);
}

// Quote submission (translator jobs - 2 fields)
export function submitTranslatorJobQuote(id: number, data: {
  cost_description: string;
  cost: number;
}) {
  return instance.post(`/translator-jobs/${id}/quotes`, data);
}

export function getTranslatorJobQuotes(id: number) {
  return instance.get(`/translator-jobs/${id}/quotes`);
}

// Expenses (interpreter jobs)
export function submitJobExpenses(id: number, data: {
  base_amount: number;
  expenses_details?: Array<{ type: string; value: number }>;
  notes?: string;
}) {
  return instance.post(`/interpreter-jobs/${id}/expenses`, data);
}

export function getJobExpenses(id: number) {
  return instance.get(`/interpreter-jobs/${id}/expenses`);
}

export function updateProfile(data: {
  title?: string;
  firstName?: string;
  lastName?: string;
  email?: string;
  contact_number?: string;
  gender?: number;
  address_line_1?: string;
  address_line_2?: string;
  county?: string;
  postcode?: string;
  skype_details?: string;
  can_provide_affidavit?: boolean;
  can_provide_affirmation?: boolean;
  bank_name?: string;
  account_holder?: string;
  account_number?: string;
  sort_code?: string;
  password?: string;
}) {
  const payload: any = {};
  if (data.title !== undefined) payload.title = data.title;
  if (data.firstName !== undefined) payload.first_name = data.firstName;
  if (data.lastName !== undefined) payload.last_name = data.lastName;
  if (data.email !== undefined) payload.email = data.email;
  if (data.contact_number !== undefined) payload.contact_number = data.contact_number;
  if (data.gender !== undefined) payload.gender = data.gender;
  if (data.address_line_1 !== undefined) payload.address_line_1 = data.address_line_1;
  if (data.address_line_2 !== undefined) payload.address_line_2 = data.address_line_2;
  if (data.county !== undefined) payload.county = data.county;
  if (data.postcode !== undefined) payload.postcode = data.postcode;
  if (data.skype_details !== undefined) payload.skype_details = data.skype_details;
  if (data.can_provide_affidavit !== undefined) payload.can_provide_affidavit = data.can_provide_affidavit;
  if (data.can_provide_affirmation !== undefined) payload.can_provide_affirmation = data.can_provide_affirmation;
  if (data.bank_name !== undefined) payload.bank_name = data.bank_name;
  if (data.account_holder !== undefined) payload.account_holder = data.account_holder;
  if (data.account_number !== undefined) payload.account_number = data.account_number;
  if (data.sort_code !== undefined) payload.sort_code = data.sort_code;
  if (data.password !== undefined) payload.password = data.password;
  return instance.put('/auth/profile', payload);
}

export function getDocuments() {
  return instance.get('/documents');
}

export function getDocument(id: number) {
  return instance.get(`/documents/${id}`);
}

export function downloadDocument(id: number) {
  return instance.get(`/documents/${id}/download`, { responseType: 'blob' });
}

export function uploadDocument(file: FormData, onUploadProgress?: (progressEvent: any) => void) {
  return instance.post('/documents/upload', file, {
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress,
  });
}

export function uploadTimesheetForJob(jobId: number, file: FormData) {
  return instance.post(`/interpreter-jobs/${jobId}/upload-timesheet`, file, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
}

export function deleteDocument(id: number) {
  return instance.delete(`/documents/${id}`);
}

// Push Subscriptions
export function registerPushToken(
  endpoint: string,
  platform: 'web' | 'ios' | 'android',
  keys?: { p256dh?: string; auth?: string },
  userAgent?: string
) {
  return instance.post('/push-subscriptions', {
    endpoint,
    platform,
    keys,
    user_agent: userAgent,
  });
}

export function removePushToken(endpoint: string) {
  return instance.delete('/push-subscriptions', { data: { endpoint } });
}

export function registerPushSubscription(data: {
  endpoint: string;
  p256dh_key: string;
  auth_key: string;
}) {
  return instance.post('/push-subscriptions', {
    endpoint: data.endpoint,
    p256dh_key: data.p256dh_key,
    auth_key: data.auth_key,
    platform: 'web',
    user_agent: typeof navigator !== 'undefined' ? navigator.userAgent : undefined,
  });
}

// Notifications
export function getNotifications(page = 1, perPage = 20) {
  return instance.get('/notifications', { params: { page, per_page: perPage } });
}

export function getUnreadNotificationCount() {
  return instance.get('/notifications/unread-count');
}

export function markNotificationRead(id: string) {
  return instance.post(`/notifications/${id}/read`);
}

export function markAllNotificationsRead() {
  return instance.post('/notifications/read-all');
}
export function getConversations() {
  return instance.get('/conversations');
}

export function getConversation(id: number) {
  return instance.get(`/conversations/${id}`);
}

export function createConversation(userId: number) {
  return instance.post('/conversations', { user_id: userId });
}

export function sendMessage(conversationId: number, body: string, type: string = 'text') {
  return instance.post(`/conversations/${conversationId}/messages`, { body, type });
}

export function markMessageAsRead(messageId: number) {
  return instance.patch(`/messages/${messageId}/read`);
}

// Timesheets
export function getTimesheets(params?: {
  status?: string | string[];
  from_date?: string;
  to_date?: string;
  page?: number;
  per_page?: number;
  q?: string;
  ref?: string;
  client_name?: string;
  employee_name?: string;
  project?: string;
  employee?: number;
}) {
  return cachedGet('/timesheets', { params: params as any, ttlMs: 60_000, allowStale: true });
}

export function exportTimesheets(params?: {
  format?: 'csv' | 'pdf';
  limit?: number;
  status?: string | string[];
  from_date?: string;
  to_date?: string;
  q?: string;
  ref?: string;
  client_name?: string;
  employee_name?: string;
  project?: string;
  employee?: number;
}) {
  return instance.get('/timesheets/export', { params, responseType: 'blob' });
}

export function downloadTimesheetPdf(id: number) {
  return instance.get(`/timesheets/${id}/pdf`, { responseType: 'blob' });
}

export function getTimesheet(id: number) {
  return instance.get(`/timesheets/${id}`);
}

export function createTimesheet(data: {
  job_id: number;
  agent_start_time: string;
  agent_end_time: string;
  agent_duration_hours?: number;
  agent_duration_minutes?: number;
  notes?: string;
  client_name?: string;
  client_phone?: string;
  client_designation?: string;
}) {
  return instance.post('/timesheets', data);
}

export function updateTimesheet(id: number, data: {
  agent_start_time?: string;
  agent_end_time?: string;
  notes?: string;
}) {
  return instance.put(`/timesheets/${id}`, data);
}

export function deleteTimesheet(id: number) {
  return instance.delete(`/timesheets/${id}`);
}

export function addTimesheetExpense(timesheetId: number, expense: {
  type: string;
  amount: number;
}) {
  return instance.post(`/timesheets/${timesheetId}/expenses`, expense);
}

export function removeTimesheetExpense(timesheetId: number, expenseId: number) {
  return instance.delete(`/timesheets/${timesheetId}/expenses/${expenseId}`);
}

export function signTimesheet(timesheetId: number, data: {
  agent_signature: string;
  client_signature: string;
  client_name: string;
  client_phone?: string;
  client_designation?: string;
}) {
  return instance.post(`/timesheets/${timesheetId}/sign`, data);
}


// ============================================
// PHASE 2: Client Invoices & Remittances API
// ============================================

// Client Invoices
export function getClientInvoices(params?: {
  status?: string;
  from_date?: string;
  to_date?: string;
  page?: number;
}) {
  return instance.get('/client-invoices', { params });
}

export function getClientInvoice(id: number) {
  return instance.get(`/client-invoices/${id}`);
}

export function downloadClientInvoicePdf(id: number) {
  return instance.get(`/client-invoices/${id}/pdf`, {
    responseType: 'blob'
  });
}

// Remittances
export function getRemittances(params?: {
  status?: string;
  page?: number;
}) {
  return instance.get('/remittances', { params });
}

export function createRemittance(data: FormData) {
  return instance.post('/remittances', data, {
    headers: { 'Content-Type': 'multipart/form-data' }
  });
}

export function getRemittance(id: number) {
  return instance.get(`/remittances/${id}`);
}

// Agent Invoices (Payslips)
export function getAgentInvoices(params?: {
  status?: string;
  from_date?: string;
  to_date?: string;
  page?: number;
  per_page?: number;
}) {
  return instance.get('/agent-invoices', { params });
}

// Dashboard Stats (server-side computed)
export function getDashboardStats() {
  return cachedGet('/dashboard-stats', { ttlMs: 60_000, allowStale: true });
}

// Activities feed (real agent activity from all_updates)
export function getActivities(params?: { page?: number; per_page?: number }) {
  return cachedGet('/activities', { params: params as any, ttlMs: 60_000, allowStale: true });
}

export function getAgentInvoice(id: number) {
  return instance.get(`/agent-invoices/${id}`);
}

export function downloadAgentInvoicePdf(id: number) {
  return instance.get(`/agent-invoices/${id}/pdf`, {
    responseType: 'blob'
  });
}
