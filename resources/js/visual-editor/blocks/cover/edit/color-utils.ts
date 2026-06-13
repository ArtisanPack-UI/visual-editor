/**
 * Cover — color utilities for the editor.
 *
 * Ported from `@wordpress/block-library/src/cover/edit/color-utils.js`
 * (v9.43.0).
 */

import { colord, extend } from 'colord';
import namesPlugin from 'colord/plugins/names';
import { FastAverageColor } from 'fast-average-color';
import memoize from 'memize';

import { applyFilters } from '@wordpress/hooks';

extend([namesPlugin]);

export const DEFAULT_BACKGROUND_COLOR = '#FFF';
export const DEFAULT_OVERLAY_COLOR = '#000';

interface RgbaColor {
    r: number;
    g: number;
    b: number;
    a: number;
}

export function compositeSourceOver(
    source: RgbaColor,
    dest: RgbaColor
): RgbaColor {
    return {
        r: source.r * source.a + dest.r * dest.a * (1 - source.a),
        g: source.g * source.a + dest.g * dest.a * (1 - source.a),
        b: source.b * source.a + dest.b * dest.a * (1 - source.a),
        a: source.a + dest.a * (1 - source.a),
    };
}

interface FastAverageColorCache {
    fastAverageColor?: FastAverageColor;
}

export function retrieveFastAverageColor(): FastAverageColor {
    const cache = retrieveFastAverageColor as unknown as FastAverageColorCache;
    if (!cache.fastAverageColor) {
        cache.fastAverageColor = new FastAverageColor();
    }
    return cache.fastAverageColor;
}

/**
 * Hard cap on how long we'll wait for FastAverageColor to resolve. The
 * library wires `load`/`error`/`abort` handlers on the `<img>` it
 * creates, but a stalled browser request (CORS preflight stall, a
 * service-worker that never responds, certain mixed-content blocks the
 * browser silently drops) never fires any of those events — leaving
 * the promise pending forever. Without the timeout, the editor's
 * color-picker and media-select code paths await this promise and
 * appear to hang. 5 s is long enough to cover legitimately slow
 * loads (FastAverageColor uses a regular HTTP fetch under the hood)
 * while keeping the inspector responsive when the URL is broken.
 */
const GET_MEDIA_COLOR_TIMEOUT_MS = 5000;

function timeout( ms: number ): Promise<typeof TIMEOUT_SENTINEL> {
    return new Promise( ( resolve ) => {
        setTimeout( () => resolve( TIMEOUT_SENTINEL ), ms );
    } );
}

const TIMEOUT_SENTINEL = Symbol( 'getMediaColor.timeout' );

export const getMediaColor = memoize(async (url: string | undefined) => {
    if (!url) {
        return DEFAULT_BACKGROUND_COLOR;
    }

    const { r, g, b, a } = colord(DEFAULT_BACKGROUND_COLOR).toRgb();

    try {
        const imgCrossOrigin = applyFilters(
            'media.crossOrigin',
            undefined,
            url
        );
        const colorPromise = retrieveFastAverageColor().getColorAsync(url, {
            defaultColor: [r, g, b, a * 255],
            silent: process.env.NODE_ENV === 'production',
            crossOrigin: imgCrossOrigin as string | undefined,
        });

        const winner = await Promise.race( [
            colorPromise,
            timeout( GET_MEDIA_COLOR_TIMEOUT_MS ),
        ] );

        if ( winner === TIMEOUT_SENTINEL ) {
            if ( 'production' !== process.env.NODE_ENV ) {
                // eslint-disable-next-line no-console -- developer-facing diagnostic.
                console.warn(
                    `[cover] getMediaColor timed out after ${ GET_MEDIA_COLOR_TIMEOUT_MS }ms for URL "${ url }"; falling back to default. The <img> load event never fired — usually a CORS, mixed-content, or service-worker issue.`
                );
            }
            return DEFAULT_BACKGROUND_COLOR;
        }

        return winner.hex;
    } catch {
        return DEFAULT_BACKGROUND_COLOR;
    }
});

export function compositeIsDark(
    dimRatio: number | undefined,
    overlayColor: string | undefined,
    backgroundColor: string | undefined
): boolean {
    const overlay = overlayColor ?? DEFAULT_OVERLAY_COLOR;
    const background = backgroundColor ?? DEFAULT_BACKGROUND_COLOR;
    if (overlay === background || dimRatio === 100) {
        return colord(overlay).isDark();
    }
    const overlayRgba = colord(overlay)
        .alpha((dimRatio ?? 100) / 100)
        .toRgb();
    const backgroundRgba = colord(background).toRgb();
    const composite = compositeSourceOver(overlayRgba, backgroundRgba);
    return colord(composite).isDark();
}
