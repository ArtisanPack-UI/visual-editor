/**
 * `artisanpack-ui/media-library` wiring helper.
 *
 * The media bridge (`state.ts`) accepts any picker + uploader pair, but
 * the default ArtisanPack UI integration pairs `MediaModal` with
 * `uploadMedia`. This helper packages that wiring behind a single call so
 * host bootstraps don't have to reach into `registerMediaBridge`'s generic
 * signature — and, importantly, so the two symbols stay type-checked
 * against the bridge contract.
 *
 * Usage from a host app (after publishing the media-library React sources
 * via `php artisan vendor:publish --tag=media-react`):
 *
 * ```ts
 * import { registerArtisanpackMediaBridge } from '@artisanpack-ui/visual-editor';
 * import { MediaModal, uploadMedia } from './vendor/media-library';
 *
 * registerArtisanpackMediaBridge({ MediaModal, uploadMedia });
 * ```
 *
 * Hosts using a different library register their own picker directly via
 * `registerMediaBridge` (see `state.ts`). The two entry points share the
 * same registration slot, so calling either overrides the previous
 * registration.
 */

import { registerMediaBridge, type RegisterMediaBridgeOptions } from './state';
import type { MediaBridgeComponent, MediaUploader } from './types';

/**
 * Options accepted by `registerArtisanpackMediaBridge`. Mirrors
 * `RegisterMediaBridgeOptions` but renames `MediaBridge` → `MediaModal`
 * and `uploadMedia` stays the same so the call site reads like a direct
 * hand-off from the library's own exports.
 */
export interface RegisterArtisanpackMediaBridgeOptions {
    /**
     * `MediaModal` component from `artisanpack-ui/media-library`. Must
     * accept the modal-picker props the bridge contract defines (open,
     * onClose, onSelect, multiSelect, allowedTypes, context, title).
     * `MediaModal`'s published props are a superset of this contract.
     */
    MediaModal: MediaBridgeComponent;

    /**
     * `uploadMedia` function from `artisanpack-ui/media-library`. Used by
     * `settings.mediaUpload` for drag-and-drop and direct-upload flows.
     */
    uploadMedia: MediaUploader;
}

/**
 * Register `artisanpack-ui/media-library` as the active media bridge.
 *
 * Equivalent to calling `registerMediaBridge({ MediaBridge: MediaModal,
 * uploadMedia })` — kept as a distinct entry point so hosts can express
 * intent at the call site and so the package surface documents the
 * canonical integration path explicitly.
 */
export function registerArtisanpackMediaBridge(
    options: RegisterArtisanpackMediaBridgeOptions
): void {
    const bridgeOptions: RegisterMediaBridgeOptions = {
        MediaBridge: options.MediaModal,
        uploadMedia: options.uploadMedia,
    };
    registerMediaBridge(bridgeOptions);
}
