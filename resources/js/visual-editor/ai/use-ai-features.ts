/**
 * React hook that fetches the enabled-features map from the AI API and
 * caches it for the lifetime of the mount. Used by every trigger surface
 * to decide whether to render its affordance.
 */

import { useEffect, useState } from 'react';
import { AI_FEATURE_KEYS } from './ai-api-client';
import type { AiApiClient, AiFeatureKey, AiFeaturesMap } from './ai-api-client';

// Derived from the single AI_FEATURE_KEYS list so a future 6th key only
// needs to land in ai-api-client.ts (review #6).
const EMPTY_STATE: AiFeaturesMap = AI_FEATURE_KEYS.reduce(
    (acc, key) => ({ ...acc, [key]: false }),
    {} as AiFeaturesMap,
);

export interface UseAiFeaturesResult {
    features: AiFeaturesMap;
    loading: boolean;
    error: Error | null;
    isEnabled: (key: AiFeatureKey) => boolean;
}

export function useAiFeatures(client: AiApiClient | null): UseAiFeaturesResult {
    const [features, setFeatures] = useState<AiFeaturesMap>(EMPTY_STATE);
    const [loading, setLoading] = useState<boolean>(client !== null);
    const [error, setError] = useState<Error | null>(null);

    useEffect(() => {
        if (client === null) {
            setLoading(false);
            return;
        }
        let cancelled = false;
        setLoading(true);
        client
            .features()
            .then((next) => {
                if (!cancelled) {
                    setFeatures(next);
                    setError(null);
                }
            })
            .catch((e) => {
                if (!cancelled) {
                    setError(e instanceof Error ? e : new Error(String(e)));
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });
        return () => {
            cancelled = true;
        };
    }, [client]);

    return {
        features,
        loading,
        error,
        isEnabled: (key: AiFeatureKey) => features[key] === true,
    };
}
