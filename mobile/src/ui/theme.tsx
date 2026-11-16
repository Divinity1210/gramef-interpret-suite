import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { useColorScheme } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

// Light theme colors
const lightColors = {
  bg: '#F8F9FA',
  surface: '#FFFFFF',
  text: '#212529',
  subtext: '#6C757D',
  primary: '#276EF1',
  primaryDark: '#1E40AF',
  primaryText: '#FFFFFF',
  onPrimary: '#FFFFFF',
  secondary: '#28A745',
  accent: '#FFC107',
  danger: '#DC3545',
  border: '#E9ECEF',
  card: '#FFFFFF',
  blueLight: '#E7F1FF',
  greenLight: '#D4EDDA',
  // Additional semantic colors
  statusPending: '#FFC107',
  statusActive: '#276EF1',
  statusComplete: '#28A745',
  statusCancelled: '#6C757D',
};

// Dark theme colors
const darkColors = {
  bg: '#121212',
  surface: '#1E1E1E',
  text: '#E1E1E1',
  subtext: '#9E9E9E',
  primary: '#5B8DEF',
  primaryDark: '#3D6AD6',
  primaryText: '#FFFFFF',
  onPrimary: '#FFFFFF',
  secondary: '#4CAF50',
  accent: '#FFD54F',
  danger: '#EF5350',
  border: '#2C2C2C',
  card: '#252525',
  blueLight: '#1A237E',
  greenLight: '#1B5E20',
  // Additional semantic colors
  statusPending: '#FFD54F',
  statusActive: '#5B8DEF',
  statusComplete: '#4CAF50',
  statusCancelled: '#757575',
};

export type ThemeMode = 'light' | 'dark' | 'system';

export type ThemeColors = typeof lightColors;

interface ThemeContextType {
  colors: ThemeColors;
  mode: ThemeMode;
  isDark: boolean;
  setMode: (mode: ThemeMode) => void;
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

const THEME_STORAGE_KEY = 'app_theme_mode';

export function ThemeProvider({ children }: { children: ReactNode }) {
  const systemScheme = useColorScheme();
  const [mode, setMode] = useState<ThemeMode>('system');
  const [isLoaded, setIsLoaded] = useState(false);

  // Load saved theme preference
  useEffect(() => {
    loadThemePreference();
  }, []);

  const loadThemePreference = async () => {
    try {
      const savedMode = await AsyncStorage.getItem(THEME_STORAGE_KEY);
      if (savedMode && ['light', 'dark', 'system'].includes(savedMode)) {
        setMode(savedMode as ThemeMode);
      }
    } catch (error) {
      console.error('Error loading theme preference:', error);
    } finally {
      setIsLoaded(true);
    }
  };

  const handleSetMode = async (newMode: ThemeMode) => {
    setMode(newMode);
    try {
      await AsyncStorage.setItem(THEME_STORAGE_KEY, newMode);
    } catch (error) {
      console.error('Error saving theme preference:', error);
    }
  };

  // Determine if dark mode should be active
  const isDark = mode === 'dark' || (mode === 'system' && systemScheme === 'dark');

  // Select colors based on theme
  const colors = isDark ? darkColors : lightColors;

  const value: ThemeContextType = {
    colors,
    mode,
    isDark,
    setMode: handleSetMode,
  };

  // Don't render until theme is loaded to prevent flash
  if (!isLoaded) {
    return null;
  }

  return (
    <ThemeContext.Provider value={value}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme(): ThemeContextType {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within a ThemeProvider');
  }
  return context;
}

// Export static values for non-component usage (fallback to light)
export const spacing = {
  xs: 4,
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,
};

export const radius = {
  sm: 10,
  md: 16,
  lg: 24,
  full: 9999,
};

// Shadow elevation tokens
export const shadows = {
  sm: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.08,
    shadowRadius: 4,
    elevation: 2,
  },
  md: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.12,
    shadowRadius: 8,
    elevation: 4,
  },
  lg: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.16,
    shadowRadius: 16,
    elevation: 8,
  },
  glow: (color: string) => ({
    shadowColor: color,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 12,
    elevation: 6,
  }),
};

// Animation tokens
export const animation = {
  fast: 150,
  normal: 250,
  slow: 400,
  spring: { tension: 120, friction: 8 },
  springGentle: { tension: 80, friction: 10 },
  springBouncy: { tension: 150, friction: 6 },
};

// Gradient color pairs for premium cards
export const gradients = {
  primary: ['#276EF1', '#1E40AF'],
  success: ['#059669', '#047857'],
  warning: ['#D97706', '#B45309'],
  danger: ['#DC2626', '#B91C1C'],
  purple: ['#7C3AED', '#5B21B6'],
  ocean: ['#0EA5E9', '#0284C7'],
  sunset: ['#F59E0B', '#EF4444'],
};

// Legacy export for backwards compatibility
export const colors = lightColors;

export const typography = {
  title: { fontSize: 24, fontWeight: '700' as const },
  h1: { fontSize: 20, fontWeight: '600' as const },
  h2: { fontSize: 18, fontWeight: '600' as const },
  body: { fontSize: 15 },
  bodyBold: { fontSize: 15, fontWeight: '600' as const },
  hint: { fontSize: 14 },
  caption: { fontSize: 12, fontWeight: '500' as const },
  badge: { fontSize: 11, fontWeight: '700' as const },
};
