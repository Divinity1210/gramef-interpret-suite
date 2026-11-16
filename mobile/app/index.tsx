import { Redirect, router } from 'expo-router';
import { useEffect, useState } from 'react';
import { useAuthStore } from '../src/state/auth';
import { me } from '../src/api/client';
import { View, ActivityIndicator } from 'react-native';

/**
 * Root index screen – acts as the auth gate.
 * 
 * When users visit "/" (e.g., after login or a fresh load),
 * this component checks the auth store and validates the token:
 *   - Valid token → /(tabs)  (dashboard)
 *   - Invalid/expired token → clears auth, redirects to /login
 *   - No token → /login
 */
export default function RootIndex() {
    const token = useAuthStore((s: any) => s.token);
    const isReady = useAuthStore((s: any) => s.isReady);
    const clear = useAuthStore((s: any) => s.clear);
    const [validating, setValidating] = useState(true);
    const [isValid, setIsValid] = useState(false);

    useEffect(() => {
        if (!isReady) return;

        if (!token) {
            setValidating(false);
            setIsValid(false);
            return;
        }

        // Validate the stored token against the backend
        me()
            .then(() => {
                setIsValid(true);
                setValidating(false);
            })
            .catch(() => {
                // Token is stale/expired – clear it and send to login
                console.log('Stored token is invalid, clearing auth state');
                clear();
                setIsValid(false);
                setValidating(false);
            });
    }, [isReady, token]);

    // While the auth store is hydrating or token is being validated
    if (!isReady || validating) {
        return (
            <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#0f172a' }}>
                <ActivityIndicator size="large" color="#3b82f6" />
            </View>
        );
    }

    // Redirect based on validated auth state
    if (isValid && token) {
        return <Redirect href="/(tabs)" />;
    }

    return <Redirect href="/login" />;
}
