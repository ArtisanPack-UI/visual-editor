/**
 * Image — utility helpers.
 *
 * Ported from `@wordpress/block-library/src/image/utils.js` (v9.43.0).
 * Behaviour is byte-equivalent — only TypeScript annotations are added.
 */

import { NEW_TAB_REL, ALLOWED_MEDIA_TYPES } from './constants';

/**
 * Evaluates a CSS aspect-ratio property value as a number.
 *
 * Degenerate or invalid ratios behave as 'auto'. And 'auto' ratios return NaN.
 *
 * @see https://drafts.csswg.org/css-sizing-4/#aspect-ratio
 */
export function evalAspectRatio(value: string): number {
    const [width, height = 1] = value.split('/').map(Number);
    const aspectRatio = width / height;
    return aspectRatio === Infinity || aspectRatio === 0 ? NaN : aspectRatio;
}

export function removeNewTabRel(currentRel: string | undefined): string | undefined {
    let newRel = currentRel;

    if (currentRel !== undefined && newRel) {
        NEW_TAB_REL.forEach((relVal) => {
            const regExp = new RegExp('\\b' + relVal + '\\b', 'gi');
            newRel = newRel ? newRel.replace(regExp, '') : newRel;
        });

        // Only trim if NEW_TAB_REL values was replaced.
        if (newRel !== currentRel) {
            newRel = newRel ? newRel.trim() : newRel;
        }

        if (!newRel) {
            newRel = undefined;
        }
    }

    return newRel;
}

interface LinkTargetSettings {
    readonly linkTarget: string | undefined;
    readonly rel: string | undefined;
}

/**
 * Helper to get the link target settings to be stored.
 */
export function getUpdatedLinkTargetSettings(
    value: boolean,
    { rel }: { rel?: string }
): LinkTargetSettings {
    const linkTarget = value ? '_blank' : undefined;

    let updatedRel: string | undefined;
    if (!linkTarget && !rel) {
        updatedRel = undefined;
    } else {
        updatedRel = removeNewTabRel(rel);
    }

    return {
        linkTarget,
        rel: updatedRel,
    };
}

interface MediaDetailsSize {
    readonly source_url?: string;
}

interface MediaImage {
    readonly media_details?: {
        sizes?: Record<string, MediaDetailsSize>;
    };
}

interface ImageSizeAttributes {
    readonly url?: string;
    readonly width?: string;
    readonly height?: string;
    readonly sizeSlug?: string;
}

/**
 * Determines new Image block attributes size selection.
 */
export function getImageSizeAttributes(
    image: MediaImage,
    size: string
): ImageSizeAttributes {
    const url = image?.media_details?.sizes?.[size]?.source_url;

    if (url) {
        return { url, width: undefined, height: undefined, sizeSlug: size };
    }

    return {};
}

/**
 * Checks if the file has a valid file type.
 */
export function isValidFileType(file: File): boolean {
    return ALLOWED_MEDIA_TYPES.some(
        (mediaType) => file.type.indexOf(mediaType) === 0
    );
}

interface FocalPoint {
    readonly x?: number;
    readonly y?: number;
}

export function mediaPosition(
    { x, y }: FocalPoint = { x: 0.5, y: 0.5 }
): string {
    return `${Math.round((x ?? 0.5) * 100)}% ${Math.round((y ?? 0.5) * 100)}%`;
}
