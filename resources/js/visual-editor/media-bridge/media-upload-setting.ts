/**
 * `settings.mediaUpload` callback for `BlockEditorProvider`.
 *
 * Gutenberg calls this whenever files are dropped directly onto the
 * canvas or passed through the block-library upload helpers. It is the
 * companion to the slot-fill component in `media-upload.tsx`: the picker
 * opens an existing modal, while this path streams raw `File`s through
 * the host's upload pipeline.
 *
 * Keeping the callback pluggable means consumers who want server-side
 * validation, progress toasts, or multipart streaming can swap the
 * registered uploader without touching the editor code.
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

import { mediaToGutenberg } from './adapter';
import { getMediaUploader } from './state';
import type {
    BridgeMedia,
    GutenbergMedia,
    MediaUploadSettingsOptions,
    MediaUploader,
} from './types';

/**
 * Gutenberg-compatible `settings.mediaUpload` implementation. Resolves
 * the registered uploader at call time — not at module load — so the
 * host can register after the editor has mounted.
 */
export function mediaUploadSetting(
    options: MediaUploadSettingsOptions
): void {
    const uploader = getMediaUploader();

    if (uploader === null) {
        options.onError?.(
            __(
                'Uploads are unavailable until a media bridge is registered.',
                TEXT_DOMAIN
            )
        );
        return;
    }

    runMediaUpload(options, uploader);
}

function runMediaUpload(
    options: MediaUploadSettingsOptions,
    uploader: MediaUploader
): void {
    const files = Array.from(options.filesList);
    if (files.length === 0) {
        return;
    }

    const maxSize = options.maxUploadFileSize;
    if (typeof maxSize === 'number' && maxSize > 0) {
        const tooLarge = files.find((file) => file.size > maxSize);
        if (tooLarge) {
            options.onError?.(
                __('Selected file exceeds the maximum upload size.', TEXT_DOMAIN)
            );
            return;
        }
    }

    void Promise.all(
        files.map((file) =>
            // Defer the `uploader` call onto a resolved-promise `.then` so
            // any synchronous throw lands in the `.catch` below rather
            // than escaping the promise chain.
            Promise.resolve()
                .then(() => uploader(file, options.additionalData))
                .then(unwrap)
        )
    )
        .then((media: BridgeMedia[]): void => {
            const gutenbergMedia: GutenbergMedia[] = media.map(
                mediaToGutenberg
            );
            options.onFileChange?.(gutenbergMedia);
        })
        .catch((error: unknown) => {
            const message =
                error instanceof Error && error.message
                    ? error.message
                    : __('Upload failed.', TEXT_DOMAIN);
            options.onError?.(message);
        });
}

/**
 * `artisanpack-ui/media-library`'s `uploadMedia` resolves with
 * `{ data: Media }` (matching the Laravel API resource), while simpler
 * uploaders may resolve with the bare `Media`. Support both so hosts can
 * pass the library function unchanged.
 */
function unwrap(value: { data: BridgeMedia } | BridgeMedia): BridgeMedia {
    if (value !== null && typeof value === 'object' && 'data' in value) {
        return (value as { data: BridgeMedia }).data;
    }
    return value as BridgeMedia;
}
