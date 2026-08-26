/**
 * Font Library UI store slice (#739).
 *
 * A tiny framework-agnostic pub/sub store holding one piece of shared UI
 * state: whether the Font Library modal is open. The {@link FontFamilyPicker}
 * mounts in many places at once (the global-styles Typography panel and every
 * per-block / per-element typography control), but only a single
 * {@link FontLibraryModal} instance is mounted at the site-editor root. Rather
 * than thread an `onOpenLibrary` callback through every panel, each picker just
 * calls {@link openFontLibrary} and the one mounted modal — subscribed through
 * {@link useFontLibraryOpen} — reacts.
 *
 * The snapshot is a bare boolean, so its identity is inherently stable and
 * `useSyncExternalStore` bails out of re-renders when the value is unchanged.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.7.0
 */

import { useSyncExternalStore } from 'react';

let open = false;

const listeners = new Set<() => void>();

function emit(): void {
    listeners.forEach((listener) => listener());
}

function setOpen(next: boolean): void {
    if (open === next) {
        return;
    }

    open = next;
    emit();
}

/**
 * Open the Font Library modal. Called from any mounted picker's "Manage
 * fonts…" control.
 *
 * @since 1.7.0
 */
export function openFontLibrary(): void {
    setOpen(true);
}

/**
 * Close the Font Library modal. Wired to the single mounted modal's
 * `onClose`.
 *
 * @since 1.7.0
 */
export function closeFontLibrary(): void {
    setOpen(false);
}

/**
 * Subscribe to open-state changes. Used by {@link useFontLibraryOpen}.
 *
 * @since 1.7.0
 */
export function subscribeFontLibraryOpen(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

/**
 * The current open-state snapshot. A primitive, so its identity is stable
 * between changes and `useSyncExternalStore` can bail out of re-renders.
 *
 * @since 1.7.0
 */
export function getFontLibraryOpenSnapshot(): boolean {
    return open;
}

/**
 * Test-only reset of the store back to its initial, closed state.
 *
 * @since 1.7.0
 */
export function resetFontLibraryUiStore(): void {
    open = false;
    emit();
}

/**
 * React hook returning whether the Font Library modal is open. Mount the single
 * {@link FontLibraryModal} against this at the site-editor root.
 *
 * @since 1.7.0
 */
export function useFontLibraryOpen(): boolean {
    return useSyncExternalStore(
        subscribeFontLibraryOpen,
        getFontLibraryOpenSnapshot,
        getFontLibraryOpenSnapshot
    );
}
