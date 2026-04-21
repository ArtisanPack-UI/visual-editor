/**
 * Media bridge public surface.
 *
 * Most hosts pair the editor with `artisanpack-ui/media-library` and
 * should wire the bridge through `registerArtisanpackMediaBridge`, which
 * takes the library's `MediaModal` component and `uploadMedia` function
 * and registers both against the bridge in one call. Hosts using a
 * different media library register their own picker component and
 * uploader directly through `registerMediaBridge`.
 *
 * See `docs/gutenberg-adoption.md` for the end-to-end host wiring and
 * issue #345 (supersedes the M4 stub from #314) for background.
 */

export {
    allowedTypesToBridgeTypes,
    mediaListToGutenberg,
    mediaToGutenberg,
} from './adapter';
export { registerArtisanpackMediaBridge } from './artisanpack-default';
export type { RegisterArtisanpackMediaBridgeOptions } from './artisanpack-default';
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
