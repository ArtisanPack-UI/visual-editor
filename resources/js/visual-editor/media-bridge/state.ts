/**
 * Registry for the host-provided media bridge.
 *
 * Kept in module-local state so both the `editor.MediaUpload` slot-fill
 * and the `settings.mediaUpload` callback can read it without threading
 * props through every editor mount. Registration is idempotent: repeat
 * calls replace the previous bridge, which matches how hosts typically
 * swap implementations during hot-reload.
 */

import { addFilter, removeFilter } from '@wordpress/hooks';
import type { ComponentType } from 'react';

import type {
    GutenbergMediaUploadProps,
    MediaBridgeComponent,
    MediaUploader,
} from './types';

/**
 * Filter namespace the bridge registers against `editor.MediaUpload`. The
 * WordPress hooks API uses namespace strings to dedupe registrations and
 * allow later `removeFilter` calls.
 */
export const BRIDGE_FILTER_NAMESPACE =
    'artisanpack-ui/visual-editor/media-bridge';

let registeredBridge: MediaBridgeComponent | null = null;
let registeredUploader: MediaUploader | null = null;
let filterInstalled = false;

export interface RegisterMediaBridgeOptions {
    /** Component rendered when Gutenberg opens the media library picker. */
    MediaBridge: MediaBridgeComponent;
    /** Upload function used by `settings.mediaUpload`. */
    uploadMedia: MediaUploader;
}

/**
 * Register the bridge used by core media blocks. Hosts call this once —
 * typically immediately before `bootVisualEditor()` — passing `MediaModal`
 * and `uploadMedia` from `artisanpack-ui/media-library`.
 */
export function registerMediaBridge(
    options: RegisterMediaBridgeOptions
): void {
    registeredBridge = options.MediaBridge;
    registeredUploader = options.uploadMedia;
    ensureMediaBridgeFilter();
}

export function getMediaBridge(): MediaBridgeComponent | null {
    return registeredBridge;
}

export function getMediaUploader(): MediaUploader | null {
    return registeredUploader;
}

/**
 * Install the `editor.MediaUpload` filter on first use. Exported so the
 * editor bootstrap can install the filter before a bridge is registered —
 * clicking the Media Library button then surfaces the unconfigured-state
 * message instead of silently doing nothing.
 */
export function ensureMediaBridgeFilter(): void {
    if (filterInstalled) {
        return;
    }

    // The filter callback resolves the slot-fill component lazily so any
    // registration that happens after this call still takes effect.
    // Import inside the callback avoids a module-level cycle with
    // media-upload.tsx, which itself imports from this module.
    addFilter(
        'editor.MediaUpload',
        BRIDGE_FILTER_NAMESPACE,
        () => mediaUploadComponent
    );
    filterInstalled = true;
}

// Lazily-resolved slot-fill component reference. Assigned by
// `media-upload.tsx` on module load so the filter callback above can
// return it without creating an import cycle.
let mediaUploadComponent: ComponentType<GutenbergMediaUploadProps> | null =
    null;

export function setMediaUploadComponent(
    component: ComponentType<GutenbergMediaUploadProps>
): void {
    mediaUploadComponent = component;
}

/**
 * Test helper — clear registration state between specs. Not part of the
 * public API; marked with double underscore to discourage production use.
 */
export function __resetMediaBridgeForTests(): void {
    registeredBridge = null;
    registeredUploader = null;

    if (filterInstalled) {
        removeFilter('editor.MediaUpload', BRIDGE_FILTER_NAMESPACE);
        filterInstalled = false;
    }

    mediaUploadComponent = null;
}
