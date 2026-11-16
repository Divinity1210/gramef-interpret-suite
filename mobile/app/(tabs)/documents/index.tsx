import { View, Text, StyleSheet, SectionList, Pressable, RefreshControl, Alert, Modal, ActivityIndicator, Platform } from 'react-native';
import { router } from 'expo-router';
import { useState, useEffect, useMemo, useCallback } from 'react';
import { useTheme, spacing, radius } from '../../../src/ui/theme';
import { Ionicons } from '@expo/vector-icons';
import { getDocuments, deleteDocument, uploadDocument } from '../../../src/api/client';
import * as DocumentPicker from 'expo-document-picker';

interface DocItem {
    id: number;
    name: string;
    type: string;
    type_label?: string;
    category: string;
    url: string;
    full_url: string;
    created_at: string;
    job?: {
        id: number;
        type: string;
        reference?: string;
        language?: string;
        appointment_date?: string;
    };
}

interface Section {
    title: string;
    icon: keyof typeof Ionicons.glyphMap;
    data: SectionItem[];
}

// A section item is either an uploaded document or an expected document slot
interface SectionItem {
    kind: 'uploaded' | 'expected';
    // For uploaded documents
    doc?: DocItem;
    // For expected slots
    slotTypeId?: number;
    slotLabel?: string;
    slotIcon?: keyof typeof Ionicons.glyphMap;
}

// Expected profile document types (mirrors web version's agent documents form)
const EXPECTED_PROFILE_DOCS: { typeId: number; label: string; icon: keyof typeof Ionicons.glyphMap }[] = [
    { typeId: 6, label: 'Passport / Photo ID', icon: 'id-card-outline' },
    { typeId: 1, label: 'DBS Certificate', icon: 'shield-checkmark-outline' },
    { typeId: 4, label: 'Interpreter Contract', icon: 'document-text-outline' },
    { typeId: 2, label: 'Proof of Address', icon: 'home-outline' },
    { typeId: 5, label: 'References', icon: 'people-outline' },
    { typeId: 3, label: 'Code of Conduct', icon: 'ribbon-outline' },
    { typeId: 10, label: 'Induction', icon: 'school-outline' },
];

export default function DocumentsScreen() {
    const { colors } = useTheme();
    const styles = useMemo(() => createStyles(colors), [colors]);
    const [documents, setDocuments] = useState<DocItem[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [showTypePicker, setShowTypePicker] = useState(false);
    const [pendingFile, setPendingFile] = useState<DocumentPicker.DocumentPickerAsset | null>(null);

    useEffect(() => {
        loadDocuments();
    }, []);

    const loadDocuments = async () => {
        try {
            setLoading(true);
            const response = await getDocuments();
            const rawData = response.data?.data;
            const docs = Array.isArray(rawData) ? rawData : (rawData?.data || []);
            setDocuments(docs);
        } catch (error) {
            // Silently handle
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const handleRefresh = () => {
        setRefreshing(true);
        loadDocuments();
    };

    const pickAndUpload = useCallback(async (typeId?: number) => {
        try {
            const result = await DocumentPicker.getDocumentAsync({
                type: ['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                copyToCacheDirectory: true,
            });

            if (result.canceled || !result.assets?.length) return;

            const asset = result.assets[0];

            if (typeId !== undefined) {
                await doUpload(asset, typeId);
            } else {
                setPendingFile(asset);
                setShowTypePicker(true);
            }
        } catch (e) {
            Alert.alert('Error', 'Could not pick document');
        }
    }, []);

    const handleUploadForSlot = useCallback(async (typeId: number) => {
        await pickAndUpload(typeId);
    }, [pickAndUpload]);

    const handleFabUpload = useCallback(async () => {
        await pickAndUpload();
    }, [pickAndUpload]);

    const handleTypeSelected = useCallback(async (typeId: number) => {
        setShowTypePicker(false);
        if (pendingFile) {
            await doUpload(pendingFile, typeId);
            setPendingFile(null);
        }
    }, [pendingFile]);

    const doUpload = async (asset: DocumentPicker.DocumentPickerAsset, typeId: number) => {
        setUploading(true);
        setUploadProgress(0);
        try {
            const formData = new FormData();
            formData.append('file', {
                uri: asset.uri,
                name: asset.name,
                type: asset.mimeType || 'application/octet-stream',
            } as any);
            formData.append('document_type', String(typeId));

            await uploadDocument(formData, (progressEvent: any) => {
                if (progressEvent.total) {
                    setUploadProgress(Math.round((progressEvent.loaded / progressEvent.total) * 100));
                }
            });

            Alert.alert('Success', 'Document uploaded successfully');
            loadDocuments();
        } catch (e: any) {
            const msg = e.response?.data?.message || 'Failed to upload document';
            Alert.alert('Upload Failed', msg);
        } finally {
            setUploading(false);
            setUploadProgress(0);
        }
    };

    const handleDelete = (doc: DocItem) => {
        Alert.alert(
            'Delete Document',
            `Are you sure you want to delete "${doc.name}"?`,
            [
                { text: 'Cancel', style: 'cancel' },
                {
                    text: 'Delete',
                    style: 'destructive',
                    onPress: async () => {
                        try {
                            await deleteDocument(doc.id);
                            Alert.alert('Success', 'Document deleted');
                            loadDocuments();
                        } catch (error) {
                            Alert.alert('Error', 'Failed to delete document');
                        }
                    },
                },
            ]
        );
    };

    const getDocumentIcon = (type: string): keyof typeof Ionicons.glyphMap => {
        const t = String(type || '').toLowerCase();
        if (t.includes('pdf')) return 'document-text';
        if (t.includes('image') || t.includes('photo') || t.includes('png') || t.includes('jpg') || t.includes('jpeg')) return 'image';
        if (t.includes('doc') || t.includes('word')) return 'document';
        if (t.includes('xls') || t.includes('sheet')) return 'grid';
        return 'document-outline';
    };

    const formatDate = (dateStr?: string) => {
        if (!dateStr) return '';
        try {
            return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        } catch { return dateStr; }
    };

    // Build sections: Profile & Certificates with slots, then job documents
    const sections: Section[] = useMemo(() => {
        const result: Section[] = [];

        // --- Profile & Certificates Section ---
        const profileDocs = documents.filter(d => d.category === 'agent-profile');
        const profileItems: SectionItem[] = [];

        // Map uploaded docs by their type ID
        const uploadedByType: Record<number, DocItem> = {};
        profileDocs.forEach(doc => {
            const typeNum = typeof doc.type === 'number' ? doc.type : parseInt(String(doc.type));
            if (!isNaN(typeNum)) {
                uploadedByType[typeNum] = doc;
            }
        });

        // Build slots: show uploaded docs for expected types, and empty slots for missing
        EXPECTED_PROFILE_DOCS.forEach(expected => {
            if (uploadedByType[expected.typeId]) {
                profileItems.push({
                    kind: 'uploaded',
                    doc: uploadedByType[expected.typeId],
                    slotLabel: expected.label,
                    slotIcon: expected.icon,
                    slotTypeId: expected.typeId,
                });
            } else {
                profileItems.push({
                    kind: 'expected',
                    slotLabel: expected.label,
                    slotIcon: expected.icon,
                    slotTypeId: expected.typeId,
                });
            }
        });

        // Add any uploaded profile docs that don't match expected types
        profileDocs.forEach(doc => {
            const typeNum = typeof doc.type === 'number' ? doc.type : parseInt(String(doc.type));
            const isExpected = EXPECTED_PROFILE_DOCS.some(e => e.typeId === typeNum);
            if (!isExpected) {
                profileItems.push({
                    kind: 'uploaded',
                    doc,
                    slotLabel: doc.type_label || doc.name,
                    slotIcon: 'document-outline',
                });
            }
        });

        result.push({
            title: 'Profile & Certificates',
            icon: 'shield-checkmark-outline',
            data: profileItems,
        });

        // --- Job Documents Sections ---
        const jobInterpreterDocs = documents.filter(d => d.category === 'job-interpreter');
        if (jobInterpreterDocs.length > 0) {
            result.push({
                title: 'Interpreter Job Documents',
                icon: 'mic-outline',
                data: jobInterpreterDocs.map(doc => ({ kind: 'uploaded' as const, doc })),
            });
        }

        const jobTranslatorDocs = documents.filter(d => d.category === 'job-translator');
        if (jobTranslatorDocs.length > 0) {
            result.push({
                title: 'Translator Job Documents',
                icon: 'language-outline',
                data: jobTranslatorDocs.map(doc => ({ kind: 'uploaded' as const, doc })),
            });
        }

        const otherDocs = documents.filter(d => !['agent-profile', 'job-interpreter', 'job-translator'].includes(d.category));
        if (otherDocs.length > 0) {
            result.push({
                title: 'Other Documents',
                icon: 'folder-outline',
                data: otherDocs.map(doc => ({ kind: 'uploaded' as const, doc })),
            });
        }

        return result;
    }, [documents]);

    const uploadedCount = documents.length;
    const profileUploadedCount = documents.filter(d => d.category === 'agent-profile').length;

    const renderSectionHeader = ({ section }: { section: Section }) => {
        const isProfile = section.title === 'Profile & Certificates';
        const uploadedInSection = isProfile
            ? section.data.filter(d => d.kind === 'uploaded').length
            : section.data.length;
        const totalInSection = section.data.length;

        return (
            <View style={styles.sectionHeader}>
                <Ionicons name={section.icon} size={20} color={colors.primary} />
                <Text style={styles.sectionTitle}>{section.title}</Text>
                <View style={styles.sectionBadge}>
                    <Text style={styles.sectionBadgeText}>
                        {isProfile ? `${uploadedInSection}/${totalInSection}` : uploadedInSection}
                    </Text>
                </View>
            </View>
        );
    };

    const renderItem = ({ item }: { item: SectionItem }) => {
        if (item.kind === 'expected') {
            // Empty slot for a missing document
            return (
                <Pressable style={styles.slotCard} onPress={() => handleUploadForSlot(item.slotTypeId!)}>
                    <View style={[styles.slotIcon, { backgroundColor: colors.subtext + '10' }]}>
                        <Ionicons name={item.slotIcon || 'document-outline'} size={24} color={colors.subtext} />
                    </View>
                    <View style={styles.documentInfo}>
                        <Text style={styles.slotLabel}>{item.slotLabel}</Text>
                        <View style={styles.missingRow}>
                            <Ionicons name="alert-circle-outline" size={13} color="#f59e0b" />
                            <Text style={styles.missingText}>Not uploaded</Text>
                        </View>
                    </View>
                    <Pressable style={styles.uploadBtn} onPress={() => handleUploadForSlot(item.slotTypeId!)}>
                        <Ionicons name="cloud-upload-outline" size={18} color={colors.primary} />
                        <Text style={styles.uploadBtnText}>Upload</Text>
                    </Pressable>
                </Pressable>
            );
        }

        // Uploaded document
        const doc = item.doc!;
        return (
            <Pressable
                style={styles.documentCard}
                onPress={() => router.push(`/documents/${doc.id}` as any)}
                onLongPress={() => handleDelete(doc)}
            >
                <View style={[styles.documentIcon, { backgroundColor: '#22c55e15' }]}>
                    <Ionicons
                        name={item.slotIcon || getDocumentIcon(doc.type)}
                        size={24}
                        color="#22c55e"
                    />
                </View>
                <View style={styles.documentInfo}>
                    <Text style={styles.documentName} numberOfLines={1}>
                        {item.slotLabel || doc.type_label || doc.name || 'Untitled'}
                    </Text>
                    <Text style={styles.documentFilename} numberOfLines={1}>
                        {doc.name}
                    </Text>
                    {/* Job reference if available */}
                    {doc.job && (
                        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 2 }}>
                            <Ionicons name="briefcase-outline" size={12} color={colors.accent} />
                            <Text style={[styles.documentMeta, { color: colors.accent }]}>
                                {doc.job.reference || `Job #${doc.job.id}`}
                                {doc.job.language ? ` • ${doc.job.language}` : ''}
                            </Text>
                        </View>
                    )}
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 3 }}>
                        <View style={styles.uploadedBadge}>
                            <Ionicons name="checkmark-circle" size={10} color="#22c55e" />
                            <Text style={styles.uploadedBadgeText}>Uploaded</Text>
                        </View>
                        <Text style={styles.documentDate}>{formatDate(doc.created_at)}</Text>
                    </View>
                </View>
                <Ionicons name="chevron-forward" size={18} color={colors.subtext} />
            </Pressable>
        );
    };

    const renderEmpty = () => (
        <View style={styles.emptyContainer}>
            <Ionicons name="folder-open-outline" size={80} color={colors.subtext} />
            <Text style={styles.emptyText}>No documents yet</Text>
            <Text style={styles.emptySubtext}>Documents linked to your jobs and profile will appear here</Text>
        </View>
    );

    return (
        <View style={styles.container}>
            <View style={styles.header}>
                <Text style={styles.headerTitle}>Documents</Text>
                <Text style={styles.headerSubtitle}>
                    {uploadedCount} uploaded • {EXPECTED_PROFILE_DOCS.length - profileUploadedCount} profile docs pending
                </Text>
            </View>

            <SectionList
                sections={sections}
                renderItem={renderItem}
                renderSectionHeader={renderSectionHeader}
                keyExtractor={(item, index) =>
                    item.kind === 'uploaded' && item.doc
                        ? `doc-${item.doc.id}`
                        : `slot-${item.slotTypeId || index}`
                }
                contentContainerStyle={styles.listContent}
                stickySectionHeadersEnabled={false}
                refreshControl={
                    <RefreshControl refreshing={refreshing} onRefresh={handleRefresh} tintColor={colors.primary} />
                }
            />

            <Pressable style={styles.fab} onPress={handleFabUpload}>
                <Ionicons name="add" size={28} color="#fff" />
            </Pressable>

            {/* Document Type Picker Modal (for FAB uploads) */}
            <Modal visible={showTypePicker} transparent animationType="slide" onRequestClose={() => setShowTypePicker(false)}>
                <Pressable style={styles.modalOverlay} onPress={() => setShowTypePicker(false)}>
                    <View style={styles.modalSheet}>
                        <Text style={styles.modalTitle}>Select Document Type</Text>
                        <Text style={styles.modalSubtitle}>What type of document are you uploading?</Text>
                        {EXPECTED_PROFILE_DOCS.map(doc => (
                            <Pressable
                                key={doc.typeId}
                                style={styles.modalOption}
                                onPress={() => handleTypeSelected(doc.typeId)}
                            >
                                <Ionicons name={doc.icon} size={22} color={colors.primary} />
                                <Text style={styles.modalOptionText}>{doc.label}</Text>
                            </Pressable>
                        ))}
                        <Pressable style={styles.modalCancel} onPress={() => setShowTypePicker(false)}>
                            <Text style={styles.modalCancelText}>Cancel</Text>
                        </Pressable>
                    </View>
                </Pressable>
            </Modal>

            {/* Upload Progress Overlay */}
            {uploading && (
                <View style={styles.uploadOverlay}>
                    <View style={styles.uploadCard}>
                        <ActivityIndicator size="large" color={colors.primary} />
                        <Text style={styles.uploadText}>Uploading... {uploadProgress}%</Text>
                        <View style={styles.progressBar}>
                            <View style={[styles.progressFill, { width: `${uploadProgress}%` }]} />
                        </View>
                    </View>
                </View>
            )}
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
        paddingTop: spacing.xl,
        paddingBottom: spacing.md,
        backgroundColor: colors.surface,
    },
    headerTitle: {
        fontSize: 28,
        fontWeight: '700',
        color: colors.text,
    },
    headerSubtitle: {
        fontSize: 13,
        color: colors.subtext,
        marginTop: 2,
    },
    sectionHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: spacing.sm,
        paddingVertical: spacing.md,
        paddingTop: spacing.lg,
    },
    sectionTitle: {
        fontSize: 16,
        fontWeight: '700',
        color: colors.text,
        flex: 1,
    },
    sectionBadge: {
        backgroundColor: colors.primary + '20',
        borderRadius: 10,
        paddingHorizontal: 8,
        paddingVertical: 2,
    },
    sectionBadgeText: {
        fontSize: 12,
        fontWeight: '600',
        color: colors.primary,
    },
    listContent: {
        padding: spacing.lg,
    },
    // Uploaded document card
    documentCard: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: colors.surface,
        borderRadius: radius.lg,
        padding: spacing.md,
        marginBottom: spacing.sm,
        gap: spacing.md,
        borderWidth: 1,
        borderColor: colors.border,
    },
    documentIcon: {
        width: 48,
        height: 48,
        borderRadius: radius.md,
        alignItems: 'center',
        justifyContent: 'center',
    },
    documentInfo: {
        flex: 1,
    },
    documentName: {
        fontSize: 14,
        fontWeight: '600',
        color: colors.text,
        marginBottom: 1,
    },
    documentFilename: {
        fontSize: 11,
        color: colors.subtext,
    },
    documentMeta: {
        fontSize: 11,
        color: colors.subtext,
    },
    uploadedBadge: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 3,
        backgroundColor: '#22c55e10',
        borderRadius: 4,
        paddingHorizontal: 5,
        paddingVertical: 1,
    },
    uploadedBadgeText: {
        fontSize: 9,
        fontWeight: '700',
        color: '#22c55e',
    },
    documentDate: {
        fontSize: 11,
        color: colors.subtext,
    },
    // Empty slot card
    slotCard: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: colors.surface,
        borderRadius: radius.lg,
        padding: spacing.md,
        marginBottom: spacing.sm,
        gap: spacing.md,
        borderWidth: 1,
        borderColor: colors.border,
        borderStyle: 'dashed',
    },
    slotIcon: {
        width: 48,
        height: 48,
        borderRadius: radius.md,
        alignItems: 'center',
        justifyContent: 'center',
    },
    slotLabel: {
        fontSize: 14,
        fontWeight: '600',
        color: colors.text,
        marginBottom: 2,
    },
    missingRow: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 4,
    },
    missingText: {
        fontSize: 12,
        color: '#f59e0b',
        fontWeight: '500',
    },
    uploadBtn: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 4,
        backgroundColor: colors.primary + '10',
        paddingHorizontal: 10,
        paddingVertical: 6,
        borderRadius: radius.md,
    },
    uploadBtnText: {
        fontSize: 12,
        fontWeight: '600',
        color: colors.primary,
    },
    emptyContainer: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: spacing.xl * 2,
    },
    emptyText: {
        fontSize: 20,
        fontWeight: '600',
        color: colors.text,
        marginTop: spacing.lg,
        marginBottom: spacing.sm,
    },
    emptySubtext: {
        fontSize: 14,
        color: colors.subtext,
        textAlign: 'center',
        paddingHorizontal: spacing.xl,
    },
    fab: {
        position: 'absolute',
        right: spacing.lg,
        bottom: spacing.xl,
        width: 56,
        height: 56,
        borderRadius: 28,
        backgroundColor: colors.primary,
        alignItems: 'center',
        justifyContent: 'center',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.3,
        shadowRadius: 8,
        elevation: 8,
    },
    // Modal styles
    modalOverlay: {
        flex: 1,
        backgroundColor: 'rgba(0,0,0,0.5)',
        justifyContent: 'flex-end',
    },
    modalSheet: {
        backgroundColor: colors.surface,
        borderTopLeftRadius: 20,
        borderTopRightRadius: 20,
        padding: spacing.lg,
        paddingBottom: 40,
    },
    modalTitle: {
        fontSize: 18,
        fontWeight: '700',
        color: colors.text,
        marginBottom: 4,
    },
    modalSubtitle: {
        fontSize: 13,
        color: colors.subtext,
        marginBottom: spacing.md,
    },
    modalOption: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: spacing.md,
        paddingVertical: 14,
        borderBottomWidth: 1,
        borderBottomColor: colors.border,
    },
    modalOptionText: {
        fontSize: 15,
        fontWeight: '500',
        color: colors.text,
    },
    modalCancel: {
        alignItems: 'center',
        paddingVertical: 14,
        marginTop: spacing.sm,
    },
    modalCancelText: {
        fontSize: 15,
        fontWeight: '600',
        color: '#ef4444',
    },
    // Upload overlay
    uploadOverlay: {
        ...StyleSheet.absoluteFillObject,
        backgroundColor: 'rgba(0,0,0,0.5)',
        justifyContent: 'center',
        alignItems: 'center',
    },
    uploadCard: {
        backgroundColor: colors.surface,
        borderRadius: radius.lg,
        padding: spacing.xl,
        alignItems: 'center',
        width: 240,
    },
    uploadText: {
        fontSize: 14,
        fontWeight: '600',
        color: colors.text,
        marginTop: spacing.md,
        marginBottom: spacing.sm,
    },
    progressBar: {
        width: '100%',
        height: 6,
        backgroundColor: colors.border,
        borderRadius: 3,
        overflow: 'hidden',
    },
    progressFill: {
        height: '100%',
        backgroundColor: colors.primary,
        borderRadius: 3,
    },
});
