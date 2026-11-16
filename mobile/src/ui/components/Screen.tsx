import React from 'react';
import { View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import { useTheme, spacing } from '../theme';

export default function Screen({ children }: { children?: React.ReactNode }) {
  const { colors, isDark } = useTheme();

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: colors.bg }} edges={['left', 'right']}>
      <StatusBar style={isDark ? 'light' : 'dark'} />
      <View style={{ flex: 1, padding: spacing.sm }}>{children}</View>
    </SafeAreaView>
  );
}
