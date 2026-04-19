/**
 * Media bridge public surface.
 *
 * Hosts wire `artisanpack-ui/media-library` into the editor by calling
 * `registerMediaBridge()` with the library's `MediaModal` component and
 * `uploadMedia` function. See `docs/gutenberg-adoption.md` and issue
 * #314 for background.
 */

export {
    allowedTypesToBridgeTypes,
    mediaListToGutenberg,
    mediaToGutenberg,
} from './adapter';
export { MediaUploadBridge } from './media-upload';
export { mediaUploadSetting } from './media-upload-setting';
export {
    ensureMediaBridgeFilter,
    getMediaBridge,
    getMediaUploader,
    registerMediaBridge,
    BRIDGE_FILTER_NAMESPACE,
} from './state';
export type { RegisterMediaBridgeOptions } from './state';
export type {
    BridgeMedia,
    BridgeMediaType,
    GutenbergMedia,
    GutenbergMediaSize,
    GutenbergMediaUploadProps,
    MediaBridgeComponent,
    MediaBridgeComponentProps,
    MediaUploadSettingsOptions,
    MediaUploader,
} from './types';
