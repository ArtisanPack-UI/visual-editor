/**
 * Gallery — shared utility helpers.
 *
 * Ported from `@wordpress/block-library/src/gallery/shared.js`
 * (v9.43.0). `defaultColumnsNumber` and `pickRelevantMediaFiles` are
 * the two entry points reused by the edit component and by
 * deprecations.
 */

interface MediaSizeEntry {
    readonly url?: string;
    readonly source_url?: string;
}

interface MediaSizes {
    readonly [slug: string]: MediaSizeEntry | undefined;
}

interface MediaInfo {
    readonly alt?: string;
    readonly id?: number;
    readonly link?: string;
    readonly url?: string;
    readonly source_url?: string;
    readonly sizes?: MediaSizes;
    readonly media_details?: {
        readonly sizes?: MediaSizes;
    };
    readonly [key: string]: unknown;
}

interface RelevantMediaFiles {
    alt?: string;
    id?: number;
    link?: string;
    url?: string;
    fullUrl?: string;
}

export function defaultColumnsNumber(imageCount: number | undefined): number {
    return imageCount ? Math.min(3, imageCount) : 3;
}

export function pickRelevantMediaFiles(
    image: MediaInfo | null | undefined,
    sizeSlug: string = 'large'
): RelevantMediaFiles {
    const imageProps: RelevantMediaFiles = Object.fromEntries(
        Object.entries(image ?? {}).filter(([key]) =>
            ['alt', 'id', 'link'].includes(key)
        )
    ) as RelevantMediaFiles;

    imageProps.url =
        image?.sizes?.[sizeSlug]?.url ||
        image?.media_details?.sizes?.[sizeSlug]?.source_url ||
        image?.url ||
        image?.source_url;
    const fullUrl =
        image?.sizes?.full?.url ||
        image?.media_details?.sizes?.full?.source_url;
    if (fullUrl) {
        imageProps.fullUrl = fullUrl;
    }
    return imageProps;
}
