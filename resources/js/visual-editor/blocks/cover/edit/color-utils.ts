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
        const color = await retrieveFastAverageColor().getColorAsync(url, {
            defaultColor: [r, g, b, a * 255],
            silent: process.env.NODE_ENV === 'production',
            crossOrigin: imgCrossOrigin as string | undefined,
        });
        return color.hex;
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
