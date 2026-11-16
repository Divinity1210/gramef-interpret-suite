import { Tabs, router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Platform } from 'react-native';
import { useTheme } from '../../src/ui/theme';

export default function TabsLayout() {
  const { colors } = useTheme();

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.subtext,
        tabBarLabelStyle: {
          fontSize: 12,
          fontWeight: '500',
        },
        tabBarStyle: {
          backgroundColor: colors.surface,
          borderTopColor: colors.border,
          height: 55,
          paddingBottom: 4,
          paddingTop: 8,
          position: 'absolute',
          bottom: 0,
          left: 0,
          right: 0,
          elevation: 0,
          borderTopWidth: 1,
        },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Home',
          tabBarIcon: ({ color }: { color: string }) => <Ionicons name="home" size={30} color={color} />,
        }}
      />
      <Tabs.Screen
        name="jobs"
        options={{
          title: 'Jobs',
          tabBarIcon: ({ color }: { color: string }) => <Ionicons name="briefcase" size={30} color={color} />,
        }}
        listeners={{
          tabPress: (event: any) => {
            event.preventDefault();
            router.replace('/(tabs)/jobs');
          },
        }}
      />
      <Tabs.Screen
        name="timesheets"
        options={{
          title: 'Timesheets',
          tabBarIcon: ({ color }: { color: string }) => <Ionicons name="timer-outline" size={30} color={color} />,
        }}
      />
      <Tabs.Screen
        name="messages"
        options={{
          title: 'Messages',
          tabBarIcon: ({ color }: { color: string }) => <Ionicons name="chatbubbles" size={30} color={color} />,
        }}
      />
      <Tabs.Screen
        name="more"
        options={{
          title: 'More',
          tabBarIcon: ({ color }: { color: string }) => <Ionicons name="menu" size={30} color={color} />,
        }}
      />

      {/* Hidden tabs - accessible via More menu */}
      <Tabs.Screen
        name="documents"
        options={{
          href: null, // Hide from tab bar
        }}
      />
      <Tabs.Screen
        name="finances"
        options={{
          href: null, // Hide from tab bar
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          href: null, // Hide from tab bar
        }}
      />
      <Tabs.Screen
        name="notifications"
        options={{
          href: null, // Hidden - accessible via bell icon
        }}
      />
    </Tabs>
  );
}
