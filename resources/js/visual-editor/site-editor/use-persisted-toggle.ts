/**
 * Tiny localStorage-backed boolean toggle hook.
 *
 * Used by the site-editor shell to remember whether the navigator (and
 * inspector) sidebar is collapsed across navigations and reloads — the
 * issue's acceptance criteria require that "navigator sidebar is
 * collapsible; state persists across navigations within the site
 * editor." Falls back to in-memory state when storage isn't available
 * (private browsing, SSR) so the hook never throws.
 */

import { useCallback, useEffect, useRef, useState } from 'react';

function readStored(key: string, fallback: boolean): boolean {
    if (typeof window === 'undefined' || window.localStorage === undefined) {
        return fallback;
    }

    try {
        const raw = window.localStorage.getItem(key);

        if (raw === null) {
            return fallback;
        }

        return raw === 'true';
    } catch {
        // Storage access can throw in private mode / sandboxed iframes.
        return fallback;
    }
}

export function usePersistedToggle(
    storageKey: string,
    defaultValue: boolean
): [boolean, (next: boolean | ((prev: boolean) => boolean)) => void] {
    const [value, setValue] = useState<boolean>(() =>
        readStored(storageKey, defaultValue)
    );

    // Mirror the latest value in a ref so the persistence effect can
    // write without depending on `value`, avoiding redundant writes
    // when the consumer re-renders for unrelated reasons.
    const lastWrittenRef = useRef<boolean>(value);

    useEffect(() => {
        if (typeof window === 'undefined' || window.localStorage === undefined) {
            return;
        }

        if (lastWrittenRef.current === value) {
            return;
        }

        try {
            window.localStorage.setItem(storageKey, value ? 'true' : 'false');
            lastWrittenRef.current = value;
        } catch {
            // Ignore quota / disabled-storage errors.
        }
    }, [storageKey, value]);

    const update = useCallback(
        (next: boolean | ((prev: boolean) => boolean)): void => {
            setValue((prev) =>
                typeof next === 'function' ? next(prev) : next
            );
        },
        []
    );

    return [value, update];
}
