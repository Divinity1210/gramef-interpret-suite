import { useRef, ReactNode } from 'react';
import { Animated, Pressable, ViewStyle, StyleSheet } from 'react-native';

interface AnimatedCardProps {
    children: ReactNode;
    onPress?: () => void;
    style?: ViewStyle | ViewStyle[];
    disabled?: boolean;
    scaleValue?: number;
}

export default function AnimatedCard({
    children,
    onPress,
    style,
    disabled = false,
    scaleValue = 0.97,
}: AnimatedCardProps) {
    const scale = useRef(new Animated.Value(1)).current;

    const handlePressIn = () => {
        Animated.spring(scale, {
            toValue: scaleValue,
            useNativeDriver: true,
            tension: 150,
            friction: 8,
        }).start();
    };

    const handlePressOut = () => {
        Animated.spring(scale, {
            toValue: 1,
            useNativeDriver: true,
            tension: 150,
            friction: 8,
        }).start();
    };

    // Extract flex-related props for the outer Pressable so tiles can fill width
    const flatStyle = StyleSheet.flatten(style) || {};
    const { flex, flexGrow, flexShrink, flexBasis, alignSelf, width, minWidth, maxWidth, ...innerStyle } = flatStyle as any;
    const outerStyle: any = {};
    if (flex !== undefined) outerStyle.flex = flex;
    if (flexGrow !== undefined) outerStyle.flexGrow = flexGrow;
    if (flexShrink !== undefined) outerStyle.flexShrink = flexShrink;
    if (flexBasis !== undefined) outerStyle.flexBasis = flexBasis;
    if (alignSelf !== undefined) outerStyle.alignSelf = alignSelf;
    if (width !== undefined) outerStyle.width = width;
    if (minWidth !== undefined) outerStyle.minWidth = minWidth;
    if (maxWidth !== undefined) outerStyle.maxWidth = maxWidth;

    return (
        <Pressable
            onPress={onPress}
            onPressIn={handlePressIn}
            onPressOut={handlePressOut}
            disabled={disabled}
            style={outerStyle}
        >
            <Animated.View
                style={[
                    styles.card,
                    innerStyle,
                    { transform: [{ scale }] },
                    disabled && styles.disabled,
                ]}
            >
                {children}
            </Animated.View>
        </Pressable>
    );
}

const styles = StyleSheet.create({
    card: {
        overflow: 'hidden',
    },
    disabled: {
        opacity: 0.6,
    },
});
