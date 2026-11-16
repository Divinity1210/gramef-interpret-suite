import {
    View,
    Text,
    StyleSheet,
    FlatList,
    TextInput,
    Pressable,
    KeyboardAvoidingView,
    Platform,
    ActivityIndicator,
} from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useState, useEffect, useRef } from 'react';
import { useTheme, spacing, radius, shadows } from '../../src/ui/theme';
import { Ionicons } from '@expo/vector-icons';
import { useAuthStore } from '../../src/state/auth';
import { getConversation, sendMessage } from '../../src/api/client';

export default function ChatScreen() {
    const { id } = useLocalSearchParams<{ id: string }>();
    const { user } = useAuthStore();
    const { colors } = useTheme();
    const flatListRef = useRef<FlatList>(null);

    const [conversation, setConversation] = useState<any>(null);
    const [messages, setMessages] = useState<any[]>([]);
    const [inputText, setInputText] = useState('');
    const [loading, setLoading] = useState(true);
    const [sending, setSending] = useState(false);

    useEffect(() => {
        loadConversation();
        // Set up polling for new messages
        const interval = setInterval(loadConversation, 5000);
        return () => clearInterval(interval);
    }, [id]);

    const loadConversation = async () => {
        try {
            const response = await getConversation(Number(id));
            setConversation(response.data.conversation);
            setMessages(response.data.messages || []);
            setLoading(false);
        } catch (error) {
            console.error('Error loading conversation:', error);
            setLoading(false);
        }
    };

    const handleSend = async () => {
        if (!inputText.trim() || sending) return;

        const messageText = inputText.trim();
        setInputText('');
        setSending(true);

        // Optimistic update — add the message immediately
        const optimisticMsg = {
            id: `temp-${Date.now()}`,
            body: messageText,
            type: 'text',
            created_at: new Date().toISOString(),
            is_mine: true,
            user: { id: user?.id, name: user?.name || '' },
        };
        setMessages(prev => [...prev, optimisticMsg]);
        setTimeout(() => flatListRef.current?.scrollToEnd({ animated: true }), 50);

        try {
            await sendMessage(Number(id), messageText);
            // Reload to get the real message with server timestamp
            await loadConversation();
            setTimeout(() => {
                flatListRef.current?.scrollToEnd({ animated: true });
            }, 100);
        } catch (error) {
            console.error('Error sending message:', error);
            setInputText(messageText); // Restore text on error
            // Remove optimistic message
            setMessages(prev => prev.filter(m => m.id !== optimisticMsg.id));
        } finally {
            setSending(false);
        }
    };

    const formatTime = (dateStr: string) => {
        try {
            return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch { return ''; }
    };

    const renderMessage = ({ item }: { item: any }) => (
        <View style={[
            {
                maxWidth: '75%',
                padding: spacing.md,
                borderRadius: radius.lg,
                marginBottom: spacing.sm,
            },
            item.is_mine
                ? { alignSelf: 'flex-end', backgroundColor: colors.primary, borderBottomRightRadius: 4 }
                : { alignSelf: 'flex-start', backgroundColor: colors.surface, borderBottomLeftRadius: 4 },
        ]}>
            {!item.is_mine && (
                <Text style={{ fontSize: 12, fontWeight: '600', color: colors.primary, marginBottom: 4 }}>
                    {item.user?.name}
                </Text>
            )}
            <Text style={{ fontSize: 16, color: item.is_mine ? '#fff' : colors.text, marginBottom: 4 }}>
                {item.body}
            </Text>
            <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', gap: 4 }}>
                <Text style={{ fontSize: 11, color: item.is_mine ? 'rgba(255,255,255,0.7)' : colors.subtext }}>
                    {formatTime(item.created_at)}
                </Text>
                {item.is_mine && (
                    <Ionicons
                        name={item.read_at ? 'checkmark-done' : 'checkmark'}
                        size={14}
                        color={item.read_at ? '#82DBFA' : 'rgba(255,255,255,0.6)'}
                    />
                )}
            </View>
        </View>
    );

    if (loading) {
        return (
            <View style={{ flex: 1, backgroundColor: colors.bg, alignItems: 'center', justifyContent: 'center' }}>
                <ActivityIndicator size="large" color={colors.primary} />
            </View>
        );
    }

    return (
        <KeyboardAvoidingView
            style={{ flex: 1, backgroundColor: colors.bg }}
            behavior={Platform.OS === 'ios' ? 'padding' : undefined}
            keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 0}
        >
            {/* Header */}
            <View style={{
                flexDirection: 'row',
                alignItems: 'center',
                paddingHorizontal: spacing.md,
                paddingTop: spacing.xl,
                paddingBottom: spacing.md,
                backgroundColor: colors.surface,
                gap: spacing.sm,
                borderBottomWidth: 1,
                borderBottomColor: colors.border,
            }}>
                <Pressable
                    onPress={() => router.back()}
                    style={{ width: 40, height: 40, alignItems: 'center', justifyContent: 'center' }}
                >
                    <Ionicons name="arrow-back" size={24} color={colors.text} />
                </Pressable>
                <View style={{
                    width: 38,
                    height: 38,
                    borderRadius: 19,
                    backgroundColor: colors.primary + '14',
                    alignItems: 'center',
                    justifyContent: 'center',
                }}>
                    <Text style={{ fontSize: 14, fontWeight: '700', color: colors.primary }}>
                        {(conversation?.other_user?.name || '??').split(' ').map((p: string) => p[0]).join('')}
                    </Text>
                </View>
                <View style={{ flex: 1 }}>
                    <Text style={{ fontSize: 17, fontWeight: '600', color: colors.text }}>
                        {conversation?.other_user?.name || 'Chat'}
                    </Text>
                    <Text style={{ fontSize: 12, color: colors.subtext }}>Tap for info</Text>
                </View>
            </View>

            {/* Messages List */}
            <FlatList
                ref={flatListRef}
                data={messages}
                renderItem={renderMessage}
                keyExtractor={(item) => item.id.toString()}
                contentContainerStyle={{ padding: spacing.md, paddingBottom: spacing.lg }}
                onContentSizeChange={() => flatListRef.current?.scrollToEnd({ animated: false })}
                ListEmptyComponent={
                    <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', paddingVertical: spacing.xl * 2 }}>
                        <View style={{
                            width: 80,
                            height: 80,
                            borderRadius: 40,
                            backgroundColor: colors.primary + '0A',
                            alignItems: 'center',
                            justifyContent: 'center',
                            marginBottom: spacing.md,
                        }}>
                            <Ionicons name="chatbubble-outline" size={36} color={colors.primary + '40'} />
                        </View>
                        <Text style={{ fontSize: 18, fontWeight: '600', color: colors.text, marginBottom: 4 }}>
                            No messages yet
                        </Text>
                        <Text style={{ fontSize: 14, color: colors.subtext }}>
                            Start the conversation!
                        </Text>
                    </View>
                }
            />

            {/* Input */}
            <View style={{
                flexDirection: 'row',
                alignItems: 'flex-end',
                padding: spacing.md,
                backgroundColor: colors.surface,
                gap: spacing.sm,
                borderTopWidth: 1,
                borderTopColor: colors.border,
            }}>
                <Pressable style={{ width: 40, height: 40, alignItems: 'center', justifyContent: 'center' }}>
                    <Ionicons name="add-circle" size={28} color={colors.primary} />
                </Pressable>
                <TextInput
                    style={{
                        flex: 1,
                        backgroundColor: colors.bg,
                        borderRadius: radius.lg,
                        paddingHorizontal: spacing.md,
                        paddingVertical: spacing.sm,
                        fontSize: 16,
                        color: colors.text,
                        maxHeight: 100,
                        borderWidth: 1,
                        borderColor: colors.border,
                    }}
                    value={inputText}
                    onChangeText={setInputText}
                    placeholder="Type a message..."
                    placeholderTextColor={colors.subtext}
                    multiline
                    maxLength={5000}
                />
                <Pressable
                    style={[
                        {
                            width: 44,
                            height: 44,
                            borderRadius: 22,
                            backgroundColor: colors.primary,
                            alignItems: 'center',
                            justifyContent: 'center',
                        },
                        (!inputText.trim() || sending) && { opacity: 0.5 },
                    ]}
                    onPress={handleSend}
                    disabled={!inputText.trim() || sending}
                >
                    {sending ? (
                        <ActivityIndicator size="small" color="#fff" />
                    ) : (
                        <Ionicons name="send" size={20} color="#fff" />
                    )}
                </Pressable>
            </View>
        </KeyboardAvoidingView>
    );
}
