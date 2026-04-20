/**
 * Browser CustomEvent contract for the visual editor.
 *
 * The editor is a self-contained React app, but it's embedded inside host
 * frameworks (Blade, Livewire, Inertia) that need a side-channel for
 * reacting to editor activity without cracking open the React tree. The
 * three events below are the public bridge — they're dispatched on
 * `window` with a namespaced name so multiple editors and listeners can
 * coexist.
 *
 * Event names use the `ve:editor:*` prefix (browser CustomEvents allow
 * colons in their names). Host integrations listen with
 * `window.addEventListener('ve:editor:save', ...)`, or — inside Alpine —
 * `@ve:editor:save.window="..."`.
 */

import type { BlockInstance } from '@wordpress/blocks';

export const VE_EDITOR_CHANGE = 've:editor:change';
export const VE_EDITOR_AUTOSAVE = 've:editor:autosave';
export const VE_EDITOR_SAVE = 've:editor:save';

export interface EditorEventTarget {
    /** Resource slug from `config/visual-editor.php`. */
    resource: string;
    /** Host-model primary key as a string (matches the Blade data attribute). */
    id: string;
}

/**
 * Fires after the debounce window closes and the editor is about to persist
 * a new block tree. Use this for "unsaved changes" indicators that should
 * react immediately, without waiting on the network round-trip.
 */
export interface VeEditorChangeDetail extends EditorEventTarget {
    /** Snapshot of the block tree that's about to be saved. */
    blocks: readonly BlockInstance[];
}

/**
 * Fires when a debounce-triggered save completes successfully. Use this for
 * host UI that should only react to *persisted* autosaves (e.g. a Livewire
 * parent that mirrors `updated_at`).
 */
export interface VeEditorAutosaveDetail extends EditorEventTarget {
    /** Block tree that was just persisted. */
    blocks: readonly BlockInstance[];
    /** ISO 8601 timestamp returned by the API. */
    updatedAt: string | null;
}

/**
 * Fires when an explicit user save (⌘S / the top-bar Save button) completes
 * successfully. Semantically stronger than autosave — host apps typically
 * treat this as the signal to publish, navigate away, or flash a toast.
 */
export interface VeEditorSaveDetail extends EditorEventTarget {
    /** Block tree that was just persisted. */
    blocks: readonly BlockInstance[];
    /** ISO 8601 timestamp returned by the API. */
    updatedAt: string | null;
}

export type VeEditorChangeEvent = CustomEvent<VeEditorChangeDetail>;
export type VeEditorAutosaveEvent = CustomEvent<VeEditorAutosaveDetail>;
export type VeEditorSaveEvent = CustomEvent<VeEditorSaveDetail>;

type EventMap = {
    [VE_EDITOR_CHANGE]: VeEditorChangeDetail;
    [VE_EDITOR_AUTOSAVE]: VeEditorAutosaveDetail;
    [VE_EDITOR_SAVE]: VeEditorSaveDetail;
};

/**
 * Dispatches a typed CustomEvent on `window`. No-op when the document is
 * unavailable (e.g. SSR, Vitest environments that skip the jsdom setup).
 */
export function dispatchEditorEvent<Name extends keyof EventMap>(
    name: Name,
    detail: EventMap[Name],
): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent(name, { detail }));
}
