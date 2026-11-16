import { View, Text, StyleSheet, ScrollView, Pressable, Animated, Switch } from 'react-native';
import { router } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { useTheme, spacing, radius, shadows } from '../../src/ui/theme';
import { Ionicons } from '@expo/vector-icons';
import { useAuthStore } from '../../src/state/auth';
import AnimatedCard from '../../src/ui/components/AnimatedCard';
import { useToast } from '../../src/ui/components/Toast';

interface MenuItem {
    title: string;
    icon: string;
    route: string;
    description: string;
    badge?: string;
    color?: string;
}

export default function MoreScreen() {
    const { colors, mode, setMode, isDark } = useTheme();
    const user = useAuthStore((state) => state.user);
    const logout = useAuthStore((state) => state.logout);
    const toast = useToast();
    const [biometricEnabled, setBiometricEnabled] = useState(false);

    // Staggered animation refs
    const fadeAnims = useRef(Array.from({ length: 8 }, () => new Animated.Value(0))).current;
    const slideAnims = useRef(Array.from({ length: 8 }, () => new Animated.Value(30))).current;

    useEffect(() => {
        // Staggered fade-in animation
        const animations = fadeAnims.map((anim, index) =>
            Animated.parallel([
                Animated.timing(anim, {
                    toValue: 1,
                    duration: 300,
                    delay: index * 60,
                    useNativeDriver: true,
                }),
                Animated.timing(slideAnims[index], {
                    toValue: 0,
                    duration: 350,
                    delay: index * 60,
                    useNativeDriver: true,
                }),
            ])
        );
        Animated.stagger(40, animations).start();
    }, []);

    const handleLogout = () => {
        logout();
        toast.info('Logged out successfully');
        router.replace('/login');
    };

    const menuItems: MenuItem[] = [
        {
            title: 'Timesheets',
            icon: 'time-outline',
            route: '/timesheets',
            description: 'Track time & submit for approval',
            color: '#276EF1',
        },
        {
            title: 'Profile',
            icon: 'person-outline',
            route: '/profile',
            description: 'View and edit your profile',
            color: '#059669',
        },
        {
            title: 'Documents',
            icon: 'folder-outline',
            route: '/documents',
            description: 'Certificates, IDs & contracts',
            color: '#7C3AED',
        },
        {
            title: 'Finances',
            icon: 'cash-outline',
            route: '/finances/agent-invoices',
            description: 'Invoices, payments & remittances',
            color: '#D97706',
        },
        {
            title: 'Settings',
            icon: 'settings-outline',
            route: '/profile/settings',
            description: 'Theme, notifications & preferences',
            color: '#6B7280',
        },
    ];

    const styles = createStyles(colors, isDark);

    const getInitials = () => {
        const first = user?.first_name?.[0] || '';
        const last = user?.last_name?.[0] || '';
        return (first + last).toUpperCase();
    };

    const getRoleBadge = () => {
        const roles = user?.roles || [];
        if (roles.includes('agent')) return 'Interpreter';
        if (roles.includes('translator')) return 'Translator';
        if (roles.includes('client')) return 'Client';
        return user?.role?.toUpperCase() || 'AGENT';
    };

    return (
        <View style={styles.container}>
            {/* Header */}
            <View style={styles.header}>
                <Text style={styles.headerTitle}>More</Text>
            </View>

            <ScrollView style={styles.content} contentContainerStyle={styles.contentContainer}>
                {/* User Info Card with gradient accent */}
                <Animated.View style={[{ opacity: fadeAnims[0], transform: [{ translateY: slideAnims[0] }] }]}>
                    <AnimatedCard onPress={() => router.push('/profile')} style={styles.userCard}>
                        <View style={[styles.userAvatar, { backgroundColor: colors.primary + '18' }]}>
                            <Text style={[styles.avatarText, { color: colors.primary }]}>{getInitials()}</Text>
                        </View>
                        <View style={styles.userInfo}>
                            <Text style={styles.userName}>
                                {user?.first_name} {user?.last_name}
                            </Text>
                            <Text style={styles.userEmail}>{user?.email}</Text>
                            <View style={styles.roleBadge}>
                                <Text style={styles.roleBadgeText}>{getRoleBadge()}</Text>
                            </View>
                        </View>
                        <Ionicons name="chevron-forward" size={20} color={colors.subtext} />
                    </AnimatedCard>
                </Animated.View>

                {/* Quick Toggle: Dark Mode */}
                <Animated.View style={[{ opacity: fadeAnims[1], transform: [{ translateY: slideAnims[1] }] }]}>
                    <View style={styles.toggleCard}>
                        <View style={styles.toggleLeft}>
                            <View style={[styles.menuItemIcon, { backgroundColor: '#1E1E1E' + '18' }]}>
                                <Ionicons name={isDark ? 'moon' : 'sunny-outline'} size={22} color={isDark ? '#FFD54F' : '#F59E0B'} />
                            </View>
                            <View>
                                <Text style={styles.toggleTitle}>Dark Mode</Text>
                                <Text style={styles.toggleSubtitle}>{isDark ? 'On' : 'Off'} • {mode === 'system' ? 'System default' : 'Manual'}</Text>
                            </View>
                        </View>
                        <Switch
                            value={isDark}
                            onValueChange={(val) => setMode(val ? 'dark' : 'light')}
                            trackColor={{ false: colors.border, true: colors.primary + '60' }}
                            thumbColor={isDark ? colors.primary : '#f4f3f4'}
                        />
                    </View>
                </Animated.View>

                {/* Menu Items */}
                <View style={styles.menuSection}>
                    <Text style={styles.sectionTitle}>Menu</Text>
                    {menuItems.map((item, index) => (
                        <Animated.View
                            key={index}
                            style={{ opacity: fadeAnims[index + 2] || fadeAnims[fadeAnims.length - 1], transform: [{ translateY: slideAnims[index + 2] || slideAnims[slideAnims.length - 1] }] }}
                        >
                            <AnimatedCard
                                onPress={() => router.push(item.route as any)}
                                style={styles.menuItem}
                            >
                                <View style={styles.menuItemLeft}>
                                    <View style={[styles.menuItemIcon, { backgroundColor: (item.color || colors.primary) + '14' }]}>
                                        <Ionicons name={item.icon as any} size={22} color={item.color || colors.primary} />
                                    </View>
                                    <View style={styles.menuItemText}>
                                        <Text style={styles.menuItemTitle}>{item.title}</Text>
                                        <Text style={styles.menuItemDescription}>{item.description}</Text>
                                    </View>
                                </View>
                                <Ionicons name="chevron-forward" size={18} color={colors.subtext} />
                            </AnimatedCard>
                        </Animated.View>
                    ))}
                </View>

                {/* Support Section */}
                <View style={styles.menuSection}>
                    <Text style={styles.sectionTitle}>Support</Text>
                    <AnimatedCard onPress={() => toast.info('Help & Support coming soon')} style={styles.menuItem}>
                        <View style={styles.menuItemLeft}>
                            <View style={[styles.menuItemIcon, { backgroundColor: '#0EA5E9' + '14' }]}>
                                <Ionicons name="help-circle-outline" size={22} color="#0EA5E9" />
                            </View>
                            <View style={styles.menuItemText}>
                                <Text style={styles.menuItemTitle}>Help & Support</Text>
                                <Text style={styles.menuItemDescription}>FAQ, contact support</Text>
                            </View>
                        </View>
                        <Ionicons name="chevron-forward" size={18} color={colors.subtext} />
                    </AnimatedCard>
                    <AnimatedCard onPress={() => toast.info('Terms & Privacy coming soon')} style={styles.menuItem}>
                        <View style={styles.menuItemLeft}>
                            <View style={[styles.menuItemIcon, { backgroundColor: '#6B7280' + '14' }]}>
                                <Ionicons name="shield-checkmark-outline" size={22} color="#6B7280" />
                            </View>
                            <View style={styles.menuItemText}>
                                <Text style={styles.menuItemTitle}>Privacy & Terms</Text>
                                <Text style={styles.menuItemDescription}>Legal information</Text>
                            </View>
                        </View>
                        <Ionicons name="chevron-forward" size={18} color={colors.subtext} />
                    </AnimatedCard>
                </View>

                {/* App Info */}
                <View style={styles.infoSection}>
                    <View style={styles.infoRow}>
                        <Text style={styles.infoLabel}>AtoZ Mobile</Text>
                        <Text style={styles.infoValue}>v1.0.0</Text>
                    </View>
                </View>

                {/* Logout Button */}
                <Pressable style={styles.logoutButton} onPress={handleLogout}>
                    <Ionicons name="log-out-outline" size={20} color={colors.danger} />
                    <Text style={styles.logoutButtonText}>Log Out</Text>
                </Pressable>

                <View style={{ height: 40 }} />
            </ScrollView>
        </View>
    );
}

const createStyles = (colors: any, isDark: boolean) => StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: colors.bg,
    },
    header: {
        paddingHorizontal: spacing.lg,
        paddingTop: spacing.xl + 8,
        paddingBottom: spacing.md,
        backgroundColor: colors.surface,
        borderBottomWidth: 1,
        borderBottomColor: colors.border,
    },
    headerTitle: {
        fontSize: 28,
        fontWeight: '700',
        color: colors.text,
    },
    content: {
        flex: 1,
    },
    contentContainer: {
        padding: spacing.lg,
        gap: spacing.md,
    },
    userCard: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: colors.surface,
        padding: spacing.md,
        borderRadius: radius.lg,
        gap: spacing.md,
        ...shadows.sm,
    },
    userAvatar: {
        width: 60,
        height: 60,
        borderRadius: 30,
        alignItems: 'center',
        justifyContent: 'center',
    },
    avatarText: {
        fontSize: 22,
        fontWeight: '700',
    },
    userInfo: {
        flex: 1,
    },
    userName: {
        fontSize: 18,
        fontWeight: '700',
        color: colors.text,
    },
    userEmail: {
        fontSize: 13,
        color: colors.subtext,
        marginTop: 2,
    },
    roleBadge: {
        marginTop: 6,
        alignSelf: 'flex-start',
        backgroundColor: colors.primary + '18',
        paddingHorizontal: 10,
        paddingVertical: 3,
        borderRadius: 12,
    },
    roleBadgeText: {
        fontSize: 11,
        color: colors.primary,
        fontWeight: '700',
        textTransform: 'uppercase',
        letterSpacing: 0.5,
    },
    toggleCard: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        backgroundColor: colors.surface,
        padding: spacing.md,
        borderRadius: radius.lg,
        ...shadows.sm,
    },
    toggleLeft: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: spacing.md,
    },
    toggleTitle: {
        fontSize: 15,
        fontWeight: '600',
        color: colors.text,
    },
    toggleSubtitle: {
        fontSize: 12,
        color: colors.subtext,
        marginTop: 2,
    },
    menuSection: {
        gap: spacing.xs,
    },
    sectionTitle: {
        fontSize: 12,
        fontWeight: '700',
        color: colors.subtext,
        marginBottom: spacing.xs,
        textTransform: 'uppercase',
        letterSpacing: 1,
    },
    menuItem: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        backgroundColor: colors.surface,
        padding: spacing.md,
        borderRadius: radius.lg,
        marginBottom: spacing.xs,
        ...shadows.sm,
    },
    menuItemLeft: {
        flexDirection: 'row',
        alignItems: 'center',
        flex: 1,
        gap: spacing.md,
    },
    menuItemIcon: {
        width: 42,
        height: 42,
        borderRadius: radius.md,
        alignItems: 'center',
        justifyContent: 'center',
    },
    menuItemText: {
        flex: 1,
    },
    menuItemTitle: {
        fontSize: 15,
        fontWeight: '600',
        color: colors.text,
    },
    menuItemDescription: {
        fontSize: 12,
        color: colors.subtext,
        marginTop: 2,
    },
    infoSection: {
        backgroundColor: colors.surface,
        borderRadius: radius.lg,
        padding: spacing.md,
        ...shadows.sm,
    },
    infoRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
    },
    infoLabel: {
        fontSize: 14,
        color: colors.subtext,
    },
    infoValue: {
        fontSize: 14,
        fontWeight: '500',
        color: colors.text,
    },
    logoutButton: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: colors.danger + '0A',
        padding: 16,
        borderRadius: radius.lg,
        gap: spacing.sm,
        borderWidth: 1,
        borderColor: colors.danger + '30',
    },
    logoutButtonText: {
        fontSize: 16,
        fontWeight: '600',
        color: colors.danger,
    },
});
