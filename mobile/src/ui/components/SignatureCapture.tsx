import React, { useRef, useState, useMemo, useEffect, useCallback } from 'react';
import {
    View,
    Text,
    StyleSheet,
    Modal,
    Pressable,
    Platform,
} from 'react-native';
import { useTheme, spacing, radius } from '../theme';
import { Ionicons } from '@expo/vector-icons';

interface SignatureCaptureProps {
    visible: boolean;
    onClose: () => void;
    onSave: (signature: string) => void;
    title?: string;
    subtitle?: string;
}

/**
 * Web-compatible signature pad using HTML5 Canvas.
 * Replaces react-native-signature-canvas which relies on WebView
 * and doesn't work on web platform.
 */
export default function SignatureCapture({
    visible,
    onClose,
    onSave,
    title = 'Sign Here',
    subtitle = 'Please sign using your finger or mouse',
}: SignatureCaptureProps) {
    const { colors, isDark } = useTheme();
    const styles = useMemo(() => createStyles(colors), [colors]);
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const [isEmpty, setIsEmpty] = useState(true);
    const isDrawing = useRef(false);
    const lastPoint = useRef<{ x: number; y: number } | null>(null);
    const nativeRef = useRef(null);

    // Set up canvas when modal becomes visible
    useEffect(() => {
        if (visible && Platform.OS === 'web') {
            // Small delay to ensure the DOM element is rendered
            const timer = setTimeout(() => {
                const canvas = canvasRef.current;
                if (canvas) {
                    const rect = canvas.getBoundingClientRect();
                    canvas.width = rect.width * (window.devicePixelRatio || 1);
                    canvas.height = rect.height * (window.devicePixelRatio || 1);
                    const ctx = canvas.getContext('2d');
                    if (ctx) {
                        ctx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1);
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';
                        ctx.lineWidth = 3;
                        ctx.strokeStyle = isDark ? '#ffffff' : '#000000';
                    }
                }
            }, 100);
            return () => clearTimeout(timer);
        }
    }, [visible, isDark]);

    const getCanvasPoint = useCallback((e: any) => {
        const canvas = canvasRef.current;
        if (!canvas) return null;
        const rect = canvas.getBoundingClientRect();

        // Handle both mouse and touch events
        let clientX, clientY;
        if (e.touches && e.touches.length > 0) {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        } else if (e.changedTouches && e.changedTouches.length > 0) {
            clientX = e.changedTouches[0].clientX;
            clientY = e.changedTouches[0].clientY;
        } else {
            clientX = e.clientX;
            clientY = e.clientY;
        }

        return {
            x: clientX - rect.left,
            y: clientY - rect.top,
        };
    }, []);

    const startDrawing = useCallback((e: any) => {
        e.preventDefault();
        isDrawing.current = true;
        const point = getCanvasPoint(e);
        if (point) {
            lastPoint.current = point;
            const ctx = canvasRef.current?.getContext('2d');
            if (ctx) {
                ctx.beginPath();
                ctx.moveTo(point.x, point.y);
            }
        }
        setIsEmpty(false);
    }, [getCanvasPoint]);

    const draw = useCallback((e: any) => {
        e.preventDefault();
        if (!isDrawing.current) return;
        const point = getCanvasPoint(e);
        const ctx = canvasRef.current?.getContext('2d');
        if (point && ctx && lastPoint.current) {
            ctx.lineTo(point.x, point.y);
            ctx.stroke();
            lastPoint.current = point;
        }
    }, [getCanvasPoint]);

    const stopDrawing = useCallback((e: any) => {
        e.preventDefault();
        isDrawing.current = false;
        lastPoint.current = null;
    }, []);

    // Attach event listeners for web canvas
    useEffect(() => {
        if (!visible || Platform.OS !== 'web') return;

        const timer = setTimeout(() => {
            const canvas = canvasRef.current;
            if (!canvas) return;

            // Mouse events
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseleave', stopDrawing);

            // Touch events
            canvas.addEventListener('touchstart', startDrawing, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            canvas.addEventListener('touchend', stopDrawing, { passive: false });
            canvas.addEventListener('touchcancel', stopDrawing, { passive: false });

            return () => {
                canvas.removeEventListener('mousedown', startDrawing);
                canvas.removeEventListener('mousemove', draw);
                canvas.removeEventListener('mouseup', stopDrawing);
                canvas.removeEventListener('mouseleave', stopDrawing);
                canvas.removeEventListener('touchstart', startDrawing);
                canvas.removeEventListener('touchmove', draw);
                canvas.removeEventListener('touchend', stopDrawing);
                canvas.removeEventListener('touchcancel', stopDrawing);
            };
        }, 150);

        return () => clearTimeout(timer);
    }, [visible, startDrawing, draw, stopDrawing]);

    const handleClear = () => {
        const canvas = canvasRef.current;
        if (canvas) {
            const ctx = canvas.getContext('2d');
            if (ctx) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
        }
        setIsEmpty(true);
    };

    const handleSave = () => {
        const canvas = canvasRef.current;
        if (canvas && !isEmpty) {
            const dataURL = canvas.toDataURL('image/png');
            onSave(dataURL);
            handleClear();
        }
    };

    if (Platform.OS !== 'web') {
        // For native platforms, try to use the native library
        // (this is a fallback — the web implementation is the primary fix)
        try {
            const SignatureScreen = require('react-native-signature-canvas').default;

            const webStyle = `
                .m-signature-pad { box-shadow: none; border: none; background-color: ${isDark ? '#1f1f1f' : '#ffffff'}; }
                .m-signature-pad--body { border: none; }
                .m-signature-pad--footer { display: none; }
                body, html { background-color: ${isDark ? '#1f1f1f' : '#ffffff'}; }
            `;

            return (
                <Modal visible={visible} animationType="slide" presentationStyle="fullScreen" onRequestClose={onClose}>
                    <View style={styles.container}>
                        <View style={styles.header}>
                            <Pressable onPress={onClose} style={styles.closeButton}>
                                <Ionicons name="close" size={28} color={colors.text} />
                            </Pressable>
                            <View style={styles.headerContent}>
                                <Text style={styles.title}>{title}</Text>
                                <Text style={styles.subtitle}>{subtitle}</Text>
                            </View>
                            <View style={{ width: 44 }} />
                        </View>
                        <View style={styles.signatureContainer}>
                            <SignatureScreen
                                ref={nativeRef}
                                onOK={(sig: string) => { onSave(sig); }}
                                onEmpty={() => setIsEmpty(true)}
                                onBegin={() => setIsEmpty(false)}
                                webStyle={webStyle}
                                backgroundColor={isDark ? '#1f1f1f' : '#ffffff'}
                                penColor={isDark ? '#ffffff' : '#000000'}
                                minWidth={2}
                                maxWidth={4}
                                style={styles.signature}
                            />
                            <View style={styles.signatureLine} />
                            <Text style={styles.signatureLabel}>Sign above this line</Text>
                        </View>
                        <View style={styles.actions}>
                            <Pressable style={[styles.actionButton, styles.clearButton]} onPress={() => { (nativeRef.current as any)?.clearSignature(); setIsEmpty(true); }}>
                                <Ionicons name="trash-outline" size={20} color={colors.danger} />
                                <Text style={[styles.actionButtonText, { color: colors.danger }]}>Clear</Text>
                            </Pressable>
                            <Pressable style={[styles.actionButton, styles.saveButton, isEmpty && styles.disabledButton]} onPress={() => { (nativeRef.current as any)?.readSignature(); }} disabled={isEmpty}>
                                <Ionicons name="checkmark" size={20} color="#fff" />
                                <Text style={styles.saveButtonText}>Save Signature</Text>
                            </Pressable>
                        </View>
                    </View>
                </Modal>
            );
        } catch {
            // Fall through to web implementation
        }
    }

    // Web implementation using HTML5 Canvas
    return (
        <Modal
            visible={visible}
            animationType="slide"
            presentationStyle="fullScreen"
            onRequestClose={onClose}
        >
            <View style={styles.container}>
                {/* Header */}
                <View style={styles.header}>
                    <Pressable onPress={onClose} style={styles.closeButton}>
                        <Ionicons name="close" size={28} color={colors.text} />
                    </Pressable>
                    <View style={styles.headerContent}>
                        <Text style={styles.title}>{title}</Text>
                        <Text style={styles.subtitle}>{subtitle}</Text>
                    </View>
                    <View style={{ width: 44 }} />
                </View>

                {/* Signature Canvas */}
                <View style={styles.signatureContainer}>
                    <canvas
                        ref={canvasRef as any}
                        style={{
                            width: '100%',
                            height: '100%',
                            backgroundColor: isDark ? '#1f1f1f' : '#ffffff',
                            touchAction: 'none',
                            cursor: 'crosshair',
                        }}
                    />
                    <View style={styles.signatureLine} />
                    <Text style={styles.signatureLabel}>Sign above this line</Text>
                </View>

                {/* Action Buttons */}
                <View style={styles.actions}>
                    <Pressable
                        style={[styles.actionButton, styles.clearButton]}
                        onPress={handleClear}
                    >
                        <Ionicons name="trash-outline" size={20} color={colors.danger} />
                        <Text style={[styles.actionButtonText, { color: colors.danger }]}>
                            Clear
                        </Text>
                    </Pressable>

                    <Pressable
                        style={[
                            styles.actionButton,
                            styles.saveButton,
                            isEmpty && styles.disabledButton
                        ]}
                        onPress={handleSave}
                        disabled={isEmpty}
                    >
                        <Ionicons name="checkmark" size={20} color="#fff" />
                        <Text style={styles.saveButtonText}>Save Signature</Text>
                    </Pressable>
                </View>
            </View>
        </Modal>
    );
}

const createStyles = (colors: any) => StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: colors.bg,
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingHorizontal: spacing.md,
        paddingTop: spacing.xl,
        paddingBottom: spacing.md,
        backgroundColor: colors.surface,
        borderBottomWidth: 1,
        borderBottomColor: colors.border,
    },
    closeButton: {
        width: 44,
        height: 44,
        alignItems: 'center',
        justifyContent: 'center',
    },
    headerContent: {
        flex: 1,
        alignItems: 'center',
    },
    title: {
        fontSize: 20,
        fontWeight: '700',
        color: colors.text,
    },
    subtitle: {
        fontSize: 14,
        color: colors.subtext,
        marginTop: 2,
    },
    signatureContainer: {
        flex: 1,
        margin: spacing.lg,
        borderRadius: radius.lg,
        overflow: 'hidden',
        borderWidth: 2,
        borderColor: colors.border,
        borderStyle: 'dashed',
    },
    signature: {
        flex: 1,
    },
    signatureLine: {
        position: 'absolute',
        bottom: 60,
        left: 20,
        right: 20,
        height: 2,
        backgroundColor: colors.subtext + '40',
    },
    signatureLabel: {
        position: 'absolute',
        bottom: 35,
        left: 0,
        right: 0,
        textAlign: 'center',
        fontSize: 12,
        color: colors.subtext,
    },
    actions: {
        flexDirection: 'row',
        padding: spacing.lg,
        paddingBottom: spacing.xl,
        gap: spacing.md,
        backgroundColor: colors.surface,
        borderTopWidth: 1,
        borderTopColor: colors.border,
    },
    actionButton: {
        flex: 1,
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: spacing.md,
        borderRadius: radius.md,
        gap: spacing.sm,
    },
    clearButton: {
        backgroundColor: colors.bg,
        borderWidth: 1,
        borderColor: colors.danger,
    },
    saveButton: {
        backgroundColor: colors.primary,
    },
    disabledButton: {
        opacity: 0.5,
    },
    actionButtonText: {
        fontSize: 16,
        fontWeight: '600',
    },
    saveButtonText: {
        fontSize: 16,
        fontWeight: '600',
        color: '#fff',
    },
});
