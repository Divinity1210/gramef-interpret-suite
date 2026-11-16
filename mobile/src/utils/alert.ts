import { Alert, Platform } from 'react-native';

type AlertButton = {
    text: string;
    style?: 'cancel' | 'default' | 'destructive';
    onPress?: () => void;
};

/**
 * Internal: show alert using browser dialogs
 */
function _webAlert(title: string, message: string, buttons?: AlertButton[]) {
    if (!buttons || buttons.length === 0) {
        window.alert(`${title}\n\n${message}`);
        return;
    }

    if (buttons.length === 1) {
        window.alert(`${title}\n\n${message}`);
        buttons[0].onPress?.();
        return;
    }

    // Two or more buttons — use confirm()
    const cancelButton = buttons.find(b => b.style === 'cancel');
    const actionButton = buttons.find(b => b.style !== 'cancel') || buttons[buttons.length - 1];

    const confirmed = window.confirm(`${title}\n\n${message}`);
    if (confirmed) {
        actionButton.onPress?.();
    } else {
        cancelButton?.onPress?.();
    }
}

/**
 * Cross-platform alert that works on both React Native and Web.
 */
export function crossPlatformAlert(
    title: string,
    message: string,
    buttons?: AlertButton[]
) {
    if (Platform.OS !== 'web') {
        Alert.alert(title, message, buttons);
        return;
    }
    _webAlert(title, message, buttons);
}

/**
 * Show a success alert
 */
export function showSuccess(title: string, message: string) {
    crossPlatformAlert(title, message, [{ text: 'OK', style: 'default' }]);
}

/**
 * Show an error alert
 */
export function showError(title: string, message: string) {
    crossPlatformAlert(title, message, [{ text: 'OK', style: 'default' }]);
}

/**
 * GLOBAL POLYFILL: Patch Alert.alert for web browsers.
 * 
 * Call this ONCE at app startup (in _layout.tsx).
 * This makes ALL existing Alert.alert() calls throughout the entire app
 * work on web without changing any other files.
 */
export function polyfillAlertForWeb() {
    if (Platform.OS !== 'web') return;

    Alert.alert = (
        title: string,
        message?: string,
        buttons?: any[],
        _options?: any
    ) => {
        _webAlert(title, message || '', buttons);
    };
}
