/**
 * Cover — shared helpers.
 *
 * Ported from `@wordpress/block-library/src/cover/shared.js` (v9.43.0).
 * Behaviour byte-equivalent to upstream.
 */

import { getBlobTypeByURL, isBlobURL } from '@wordpress/blob';

interface FocalPoint {
    readonly x: number;
    readonly y: number;
}

interface MediaInput {
    url?: string;
    src?: string;
    id?: number;
    alt?: string;
    type?: string;
    media_type?: string;
}

interface MediaAttributes {
    url?: string;
    id?: number;
    alt?: string;
    backgroundType?: string;
    hasParallax?: boolean;
}

const POSITION_CLASSNAMES: Record<string, string> = {
    'top left': 'is-position-top-left',
    'top center': 'is-position-top-center',
    'top right': 'is-position-top-right',
    'center left': 'is-position-center-left',
    'center center': 'is-position-center-center',
    center: 'is-position-center-center',
    'center right': 'is-position-center-right',
    'bottom left': 'is-position-bottom-left',
    'bottom center': 'is-position-bottom-center',
    'bottom right': 'is-position-bottom-right',
};

export const IMAGE_BACKGROUND_TYPE = 'image';
export const VIDEO_BACKGROUND_TYPE = 'video';
export const EMBED_VIDEO_BACKGROUND_TYPE = 'embed-video';
export const COVER_MIN_HEIGHT = 50;
export const COVER_MAX_HEIGHT = 1000;
export const COVER_DEFAULT_HEIGHT = 300;
export const DEFAULT_FOCAL_POINT: FocalPoint = { x: 0.5, y: 0.5 };
export const ALLOWED_MEDIA_TYPES: readonly string[] = ['image', 'video'];

export function mediaPosition(
    { x, y }: FocalPoint = DEFAULT_FOCAL_POINT
): string {
    return `${Math.round(x * 100)}% ${Math.round(y * 100)}%`;
}

export function dimRatioToClass(ratio: number | undefined): string | null {
    return ratio === 50 || ratio === undefined
        ? null
        : 'has-background-dim-' + 10 * Math.round(ratio / 10);
}

export function attributesFromMedia(
    media: MediaInput | null | undefined
): MediaAttributes | undefined {
    if (!media || (!media.url && !media.src)) {
        return {
            url: undefined,
            id: undefined,
        };
    }

    if (isBlobURL(media.url)) {
        media.type = getBlobTypeByURL(media.url);
    }

    let mediaType: string;
    if (media.media_type) {
        if (media.media_type === IMAGE_BACKGROUND_TYPE) {
            mediaType = IMAGE_BACKGROUND_TYPE;
        } else {
            mediaType = VIDEO_BACKGROUND_TYPE;
        }
    } else if (
        media.type &&
        (media.type === IMAGE_BACKGROUND_TYPE ||
            media.type === VIDEO_BACKGROUND_TYPE)
    ) {
        mediaType = media.type;
    } else {
        return undefined;
    }

    return {
        url: media.url || media.src,
        id: media.id,
        alt: media?.alt,
        backgroundType: mediaType,
        ...(mediaType === VIDEO_BACKGROUND_TYPE
            ? { hasParallax: undefined }
            : {}),
    };
}

export function isContentPositionCenter(
    contentPosition: string | undefined
): boolean {
    return (
        !contentPosition ||
        contentPosition === 'center center' ||
        contentPosition === 'center'
    );
}

export function getPositionClassName(
    contentPosition: string | undefined
): string {
    if (isContentPositionCenter(contentPosition)) {
        return '';
    }

    return POSITION_CLASSNAMES[contentPosition as string] ?? '';
}
