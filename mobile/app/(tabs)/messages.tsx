import { View, Text, StyleSheet, FlatList, Pressable, RefreshControl, TextInput, Animated } from 'react-native';
import { router } from 'expo-router';
import { useState, useEffect, useRef, useMemo } from 'react';
import { useTheme, spacing, radius, shadows } from '../../src/ui/theme';
import { Ionicons } from '@expo/vector-icons';
import { getConversations } from '../../src/api/client';
import AnimatedCard from '../../src/ui/components/AnimatedCard';

export default function MessagesScreen() {
    const { colors } = useTheme();
    const [conversations, setConversations] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const fabScale = useRef(new Animated.Value(0)).current;

    useEffect(() => {
        loadConversations();
        // FAB bounce-in animation
        Animated.spring(fabScale, {
            toValue: 1,
            delay: 400,
            useNativeDriver: true,
            tension: 100,
            friction: 6,
        }).start();
    }, []);

    const loadConversations = async () => {
        try {
            setLoading(true);
            const response = await getConversations();
            const rawData = response.data?.data;
            setConversations(Array.isArray(rawData) ? rawData : (rawData?.data || []));
        } catch (error) {
            console.error('Error loading conversations:', error);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const handleRefresh = () => {
        setRefreshing(true);
        loadConversations();
    };

    // Filter conversations by search
    const filteredConversations = useMemo(() => {
        if (!searchQuery.trim()) return conversations;
        const q = searchQuery.toLowerCase();
        return conversations.filter((c: any) =>
            (c.other_user?.name || '').toLowerCase().includes(q) ||
            (c.last_message?.body || '').toLowerCase().includes(q)
        );
    }, [conversations, searchQuery]);

    const styles = createStyles(colors);

    const formatTime = (dateStr: string) => {
        const date = new Date(dateStr);
        const now = new Date();
        const diff = now.getTime() - date.getTime();
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        if (days === 0) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } else if (days === 1) {
            return 'Yesterday';
        } else if (days < 7) {
            return date.toLocaleDateString([], { weekday: 'short' });
        }
        return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    };

    const getInitials = (name: string) => {
        const parts = name.split(' ');
        return (parts[0]?.[0] || '') + (parts[1]?.[0] || '');
    };

    const renderConversation = ({ item, index }: { item: any; index: number }) => (
        <AnimatedCard
            onPress={() => router.push(`/chat/${item.id}`)}
            style={styles.conversationCard}
        >
            <View style={[styles.avatar, { backgroundColor: colors.primary + '14' }]}>
                <Text style={[styles.avatarText, { color: colors.primary }]}>
                    {getInitials(item.other_user?.name || '??')}
                </Text>
                {item.is_online && <View style={styles.onlineDot} />}
            </View>
            <View style={styles.conversationInfo}>
                <View style={styles.conversationHeader}>
                    <Text style={styles.userName} numberOfLines={1}>
                        {item.other_user?.name || 'Unknown'}
                    </Text>
                    {item.last_message && (
                        <Text style={[styles.timestamp, item.has_unread && { color: colors.primary, fontWeight: '600' }]}>
                            {formatTime(item.last_message.created_at)}
                        </Text>
                    )}
                </View>
                <View style={styles.messageRow}>
                    {item.last_message && (
                        <Text style={[styles.lastMessage, item.has_unread && { color: colors.text, fontWeight: '500' }]} numberOfLines={1}>
                            {item.last_message.is_mine ? 'You: ' : ''}
                            {item.last_message.body}
                        </Text>
                    )}
                    {item.has_unread && (
                        <View style={styles.unreadBadge}>
                            <Text style={styles.unreadCount}>{item.unread_count || ''}</Text>
                        </View>
                    )}
                </View>
            </View>
        </AnimatedCard>
    );

    const renderEmpty = () => (
        <View style={styles.emptyContainer}>
            <View style={styles.emptyIconContainer}>
                <Ionicons name="chatbubbles-outline" size={64} color={colors.primary + '40'} />
            </View>
            <Text style={styles.emptyText}>No conversations yet</Text>
            <Text style={styles.emptySubtext}>Messages from admins and support will appear here</Text>
        </View>
    );

    return (
        <View style={styles.container}>
            {/* Header */}
            <View style={styles.header}>
                <Text style={styles.headerTitle}>Messages</Text>
            </View>

            {/* Search bar */}
            <View style={styles.searchContainer}>
                <Ionicons name="search" size={18} color={colors.subtext} />
                <TextInput
                    style={styles.searchInput}
                    placeholder="Search conversations..."
                    placeholderTextColor={colors.subtext}
                    value={searchQuery}
                    onChangeText={setSearchQuery}
                />
                {searchQuery.length > 0 && (
                    <Pressable onPress={() => setSearchQuery('')}>
                        <Ionicons name="close-circle" size={18} color={colors.subtext} />
                    </Pressable>
                )}
            </View>

            {/* Conversations List */}
            <FlatList
                data={filteredConversations}
                renderItem={renderConversation}
                keyExtractor={(item) => item.id.toString()}
                contentContainerStyle={[
                    styles.listContent,
                    filteredConversations.length === 0 && styles.emptyList,
                ]}
                refreshControl={
                    <RefreshControl refreshing={refreshing} onRefresh={handleRefresh} tintColor={colors.primary} />
                }
                ListEmptyComponent={!loading ? renderEmpty : null}
            />

            {/* Compose FAB */}
            <Animated.View style={[styles.fab, { transform: [{ scale: fabScale }] }]}>
                <Pressable
                    style={styles.fabButton}
                    onPress={() => router.push('/chat/new' as any)}
                >
                    <Ionicons name="create-outline" size={24} color="#fff" />
                </Pressable>
            </Animated.View>
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
        paddingTop: spacing.xl + 8,
        paddingBottom: spacing.sm,
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
        marginHorizontal: spacing.lg,
        marginVertical: spacing.sm,
        paddingHorizontal: spacing.md,
        paddingVertical: 10,
        borderRadius: radius.md,
        gap: spacing.sm,
        borderWidth: 1,
        borderColor: colors.border,
    },
    searchInput: {
        flex: 1,
        fontSize: 15,
        color: colors.text,
        padding: 0,
    },
    listContent: {
        padding: spacing.lg,
        paddingBottom: 100,
    },
    emptyList: {
        flex: 1,
    },
    conversationCard: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: colors.surface,
        borderRadius: radius.lg,
        padding: spacing.md,
        marginBottom: spacing.sm,
        gap: spacing.md,
        ...shadows.sm,
    },
    avatar: {
        width: 50,
        height: 50,
        borderRadius: 25,
        alignItems: 'center',
        justifyContent: 'center',
        position: 'relative',
    },
    avatarText: {
        fontSize: 16,
        fontWeight: '700',
    },
    onlineDot: {
        position: 'absolute',
        bottom: 0,
        right: 0,
        width: 14,
        height: 14,
        borderRadius: 7,
        backgroundColor: '#22C55E',
        borderWidth: 2.5,
        borderColor: '#fff',
    },
    conversationInfo: {
        flex: 1,
    },
    conversationHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 4,
    },
    userName: {
        fontSize: 15,
        fontWeight: '600',
        color: colors.text,
        flex: 1,
        marginRight: 8,
    },
    timestamp: {
        fontSize: 12,
        color: colors.subtext,
    },
    messageRow: {
        flexDirection: 'row',
        alignItems: 'center',
    },
    lastMessage: {
        flex: 1,
        fontSize: 14,
        color: colors.subtext,
    },
    unreadBadge: {
        minWidth: 20,
        height: 20,
        borderRadius: 10,
        backgroundColor: colors.primary,
        alignItems: 'center',
        justifyContent: 'center',
        paddingHorizontal: 5,
        marginLeft: 8,
    },
    unreadCount: {
        fontSize: 11,
        fontWeight: '700',
        color: '#fff',
    },
    emptyContainer: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: spacing.xl * 2,
    },
    emptyIconContainer: {
        width: 100,
        height: 100,
        borderRadius: 50,
        backgroundColor: colors.primary + '0A',
        alignItems: 'center',
        justifyContent: 'center',
        marginBottom: spacing.lg,
    },
    emptyText: {
        fontSize: 20,
        fontWeight: '700',
        color: colors.text,
        marginBottom: spacing.sm,
    },
    emptySubtext: {
        fontSize: 14,
        color: colors.subtext,
        textAlign: 'center',
        maxWidth: 260,
    },
    fab: {
        position: 'absolute',
        bottom: 100,
        right: 24,
    },
    fabButton: {
        width: 56,
        height: 56,
        borderRadius: 28,
        backgroundColor: colors.primary,
        alignItems: 'center',
        justifyContent: 'center',
        ...shadows.lg,
    },
});
