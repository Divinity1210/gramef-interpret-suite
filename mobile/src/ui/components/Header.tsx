import React, { useEffect, useState, useCallback, useRef } from 'react';
import { View, Text, Pressable } from 'react-native';
import { colors, spacing, typography } from '../theme';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { getUnreadNotificationCount } from '../../api/client';

export default function Header({ title, onAction }: { title: string; onAction?: () => void }) {
  const [unreadCount, setUnreadCount] = useState(0);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const fetchCount = useCallback(async () => {
    try {
      const res = await getUnreadNotificationCount();
      setUnreadCount(res?.data?.count || 0);
    } catch {
      // silently ignore
    }
  }, []);

  useEffect(() => {
    fetchCount();
    // Poll every 15s for fresher badge
    intervalRef.current = setInterval(fetchCount, 15000);

    // Also re-fetch when page becomes visible (e.g., user returns from notifications)
    const handleVisibility = () => {
      if (typeof document !== 'undefined' && document.visibilityState === 'visible') {
        fetchCount();
      }
    };
    if (typeof document !== 'undefined') {
      document.addEventListener('visibilitychange', handleVisibility);
    }

    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
      if (typeof document !== 'undefined') {
        document.removeEventListener('visibilitychange', handleVisibility);
      }
    };
  }, [fetchCount]);

  return (
    <View style={{ paddingHorizontal: spacing.lg, paddingVertical: spacing.md, borderBottomWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderRadius: 16, margin: spacing.lg, shadowColor: '#000000', shadowOpacity: 0.06, shadowRadius: 10, shadowOffset: { width: 0, height: 4 }, elevation: 2 }}>
      <Text style={typography.title}>{title}</Text>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
        <Pressable
          onPress={() => router.push('/(tabs)/notifications' as any)}
          accessibilityRole="button"
          accessibilityLabel="Notifications"
          style={{ position: 'relative' }}
        >
          <Ionicons name="notifications-outline" size={22} color={colors.text} />
          {unreadCount > 0 && (
            <View style={{
              position: 'absolute', top: -4, right: -6,
              backgroundColor: '#ef4444', borderRadius: 10,
              minWidth: 18, height: 18, alignItems: 'center',
              justifyContent: 'center', paddingHorizontal: 4,
            }}>
              <Text style={{ color: '#fff', fontSize: 10, fontWeight: '700' }}>
                {unreadCount > 99 ? '99+' : unreadCount}
              </Text>
            </View>
          )}
        </Pressable>
        {onAction ? (
          <Pressable onPress={onAction} accessibilityRole="button" accessibilityLabel="Open filters">
            <Ionicons name="options-outline" size={22} color={colors.text} />
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}
