/**
 * Fires the composed-view fallback toast (#624).
 *
 * Exactly one toast per toggle-on event, no matter how the fallback was
 * reached or how many times the component re-renders while it is showing.
 * The latch is cleared when the view mode returns to `content`, so the
 * next flip of the toggle announces the fallback again — an author who
 * toggles back on has lost the earlier toast and deserves the reason a
 * second time.
 *
 * The latch holds the *message*, not a boolean, so that picking a second
 * broken template from the document panel without leaving composed mode
 * still announces the new failure. A plain boolean would swallow it: the
 * author would change the template and see nothing happen at all.
 *
 * It is also cleared by a successful resolution, so returning to a template
 * that failed earlier announces again — otherwise broken A → working B →
 * broken A would stay silent the second time around.
 *
 * Mirrors `useSaveNotifications`: a hook, so it can be exercised through a
 * tiny harness rather than a full editor mount.
 *
 * @since 1.6.0
 */

import { useEffect, useRef } from 'react';
import { useToast } from '@artisanpack-ui/react/feedback';

import { composedFallbackNotice } from './fallback';
import type { AppliedTemplateState } from './use-applied-template';

export interface UseComposedFallbackToastOptions {
    /** The editor's ephemeral composed-view mode. */
    viewMode: 'content' | 'with-template';
    state: AppliedTemplateState;
}

export function useComposedFallbackToast(
    options: UseComposedFallbackToastOptions
): void {
    const { viewMode, state } = options;
    const toast = useToast();
    const notifiedRef = useRef<string | null>(null);

    useEffect(() => {
        if (viewMode !== 'with-template') {
            notifiedRef.current = null;

            return;
        }

        const notice = composedFallbackNotice(state);

        if (notice === null) {
            // A template that resolved cleanly clears the latch, so
            // broken A → working B → broken A announces twice rather than
            // once. `idle` and `loading` deliberately leave it in place:
            // those are the states a re-fetch of the *same* template
            // passes through, and clearing there would re-announce a
            // failure the author is already looking at.
            if (state.status === 'ok') {
                notifiedRef.current = null;
            }

            return;
        }

        // A re-fetch of the same broken template produces the same copy and
        // must stay silent; a *different* failure produces different copy
        // and gets announced.
        const key = `${notice.tone}:${notice.message}`;

        if (notifiedRef.current === key) {
            return;
        }

        notifiedRef.current = key;

        if (notice.tone === 'error') {
            toast.error(notice.message);

            return;
        }

        toast.warning(notice.message);
    }, [state, toast, viewMode]);
}
