/**
 * Font Library UI store — unit tests (#739).
 *
 * Exercises the shared open-state slice the site-editor pickers and the single
 * mounted modal coordinate through: open/close transitions, snapshot reads,
 * subscriber notification (including the no-op when the value is unchanged),
 * and the test-only reset.
 *
 * @since 1.7.0
 */

import { afterEach, describe, expect, it, vi } from 'vitest';

import {
    closeFontLibrary,
    getFontLibraryOpenSnapshot,
    openFontLibrary,
    resetFontLibraryUiStore,
    subscribeFontLibraryOpen,
} from '../font-library-ui-store';

afterEach(() => {
    resetFontLibraryUiStore();
});

describe('font-library-ui-store', () => {
    it('starts closed', () => {
        expect(getFontLibraryOpenSnapshot()).toBe(false);
    });

    it('opens and closes', () => {
        openFontLibrary();
        expect(getFontLibraryOpenSnapshot()).toBe(true);

        closeFontLibrary();
        expect(getFontLibraryOpenSnapshot()).toBe(false);
    });

    it('notifies subscribers on a real change', () => {
        const listener = vi.fn();
        const unsubscribe = subscribeFontLibraryOpen(listener);

        openFontLibrary();
        expect(listener).toHaveBeenCalledTimes(1);

        closeFontLibrary();
        expect(listener).toHaveBeenCalledTimes(2);

        unsubscribe();
    });

    it('does not notify when the value is unchanged', () => {
        openFontLibrary();

        const listener = vi.fn();
        const unsubscribe = subscribeFontLibraryOpen(listener);

        // Already open — a second open is a no-op and must not fire.
        openFontLibrary();
        expect(listener).not.toHaveBeenCalled();

        unsubscribe();
    });

    it('stops notifying after unsubscribe', () => {
        const listener = vi.fn();
        const unsubscribe = subscribeFontLibraryOpen(listener);

        unsubscribe();
        openFontLibrary();

        expect(listener).not.toHaveBeenCalled();
    });

    it('reset returns the store to closed', () => {
        openFontLibrary();
        resetFontLibraryUiStore();

        expect(getFontLibraryOpenSnapshot()).toBe(false);
    });
});
