/**
 * Data-shape adapters between `artisanpack-ui/media-library` (the bridge)
 * and the Gutenberg attachment contract expected by core media blocks.
 *
 * These functions are deliberately pure so they can be reused by the
 * slot-fill component and by the `settings.mediaUpload` callback without
 * duplicating logic.
 */

import type {
    BridgeMedia,
    BridgeMediaType,
    GutenbergMedia,
    GutenbergMediaSize,
} from './types';

/**
 * Map a media-library `Media` record to the attachment shape Gutenberg
 * core blocks read. Missing or nullable fields collapse to empty strings
 * or get omitted so blocks don't render `"null"` placeholders in alt text
 * or captions.
 */
export function mediaToGutenberg(media: BridgeMedia): GutenbergMedia {
    const result: GutenbergMedia = {
        id: media.id,
        url: media.url,
        alt: media.alt_text ?? '',
        caption: media.caption ?? '',
        mime: media.mime_type,
    };

    const mediaType = inferMediaType(media);
    if (mediaType !== null) {
        result.media_type = mediaType;
    }

    if (typeof media.width === 'number') {
        result.width = media.width;
    }

    if (typeof media.height === 'number') {
        result.height = media.height;
    }

    if (typeof media.file_name === 'string' && media.file_name.length > 0) {
        result.filename = media.file_name;
    }

    const sizes = extractImageSizes(media);
    if (sizes !== null) {
        result.sizes = sizes;
    }

    return result;
}

/**
 * Map an array of media-library records. Kept as a thin helper so callers
 * don't have to remember to import both `map` and `mediaToGutenberg`.
 */
export function mediaListToGutenberg(
    items: readonly BridgeMedia[]
): GutenbergMedia[] {
    return items.map(mediaToGutenberg);
}

/**
 * Translate Gutenberg's `allowedTypes` array — which can mix category
 * names ("image", "video") with mime types ("image/png") — into the
 * categorical filter the bridge's `MediaModal` consumes.
 *
 * Returns `undefined` when the input would allow every category so the
 * picker does not over-constrain itself.
 */
export function allowedTypesToBridgeTypes(
    allowedTypes?: readonly string[]
): BridgeMediaType[] | undefined {
    if (!allowedTypes || allowedTypes.length === 0) {
        return undefined;
    }

    const types = new Set<BridgeMediaType>();

    for (const raw of allowedTypes) {
        const category = toBridgeCategory(raw);
        if (category !== null) {
            types.add(category);
        }
    }

    if (types.size === 0) {
        return undefined;
    }

    return Array.from(types);
}

function toBridgeCategory(value: string): BridgeMediaType | null {
    const normalized = value.toLowerCase().trim();

    if (normalized === 'image' || normalized.startsWith('image/')) {
        return 'image';
    }

    if (normalized === 'video' || normalized.startsWith('video/')) {
        return 'video';
    }

    if (normalized === 'audio' || normalized.startsWith('audio/')) {
        return 'audio';
    }

    if (
        normalized === 'document' ||
        normalized === 'application' ||
        normalized.startsWith('application/') ||
        normalized.startsWith('text/')
    ) {
        return 'document';
    }

    return null;
}

function inferMediaType(
    media: BridgeMedia
): 'image' | 'video' | 'audio' | 'file' | null {
    if (media.is_image) {
        return 'image';
    }

    if (media.is_video) {
        return 'video';
    }

    if (media.is_audio) {
        return 'audio';
    }

    if (media.is_document) {
        return 'file';
    }

    const prefix = media.mime_type.split('/')[0]?.toLowerCase();
    switch (prefix) {
        case 'image':
            return 'image';
        case 'video':
            return 'video';
        case 'audio':
            return 'audio';
        case 'application':
        case 'text':
            return 'file';
        default:
            return null;
    }
}

function extractImageSizes(
    media: BridgeMedia
): Record<string, GutenbergMediaSize> | null {
    const rawSizes = (media.metadata as { sizes?: unknown } | null | undefined)
        ?.sizes;

    if (!rawSizes || typeof rawSizes !== 'object') {
        return null;
    }

    const sizes: Record<string, GutenbergMediaSize> = {};

    for (const [key, value] of Object.entries(
        rawSizes as Record<string, unknown>
    )) {
        if (typeof value === 'string') {
            sizes[key] = { url: value };
            continue;
        }

        if (value && typeof value === 'object') {
            const entry = value as {
                url?: unknown;
                width?: unknown;
                height?: unknown;
            };

            if (typeof entry.url !== 'string') {
                continue;
            }

            const size: GutenbergMediaSize = { url: entry.url };
            if (typeof entry.width === 'number') {
                size.width = entry.width;
            }
            if (typeof entry.height === 'number') {
                size.height = entry.height;
            }
            sizes[key] = size;
        }
    }

    if (Object.keys(sizes).length === 0) {
        return null;
    }

    return sizes;
}
