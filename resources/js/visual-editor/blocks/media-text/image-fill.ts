/**
 * Media & Text — image-fill helpers.
 *
 * Ported from `@wordpress/block-library/src/media-text/image-fill.js`
 * (v9.43.0). The helper computes the `object-position` style applied to
 * the `<img>` inside the media figure when the "Crop image to fill"
 * toggle is enabled.
 */

export interface FocalPoint {
    readonly x: number;
    readonly y: number;
}

export function imageFillStyles(
    url?: string,
    focalPoint?: FocalPoint
): { objectPosition?: string } {
    if (!url) {
        return {};
    }
    return {
        objectPosition: focalPoint
            ? `${Math.round(focalPoint.x * 100)}% ${Math.round(focalPoint.y * 100)}%`
            : `50% 50%`,
    };
}
