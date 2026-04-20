/**
 * Save-state toast bridge.
 *
 * M8 (#318) replaces Gutenberg's `Snackbar` with `@artisanpack-ui/react`'s
 * toast system so the editor feels like a Laravel admin page. The hook
 * watches `saveStatus` transitions and fires a toast *only* on error --
 * successful saves are already announced by the live-region indicator in
 * the top bar, and firing a toast on every keystroke save would be noisy.
 */

import { useEffect, useRef } from 'react';
import { useToast } from '@artisanpack-ui/react/feedback';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

import type { SaveStatus } from './top-bar';

export interface UseSaveNotificationsOptions {
    saveStatus: SaveStatus;
    saveErrorMessage?: string | null;
}

export function useSaveNotifications(
    options: UseSaveNotificationsOptions
): void {
    const { saveStatus, saveErrorMessage } = options;
    const toast = useToast();
    const previousStatusRef = useRef<SaveStatus>(saveStatus);

    useEffect(() => {
        const previous = previousStatusRef.current;
        previousStatusRef.current = saveStatus;

        if (saveStatus !== 'error' || previous === 'error') {
            return;
        }

        const message =
            saveErrorMessage ??
            __('Unable to save changes. Please try again.', TEXT_DOMAIN);

        toast.error(message);
    }, [saveErrorMessage, saveStatus, toast]);
}
