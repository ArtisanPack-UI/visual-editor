/**
 * Carousel — shared attribute normalisation (#1127).
 *
 * The renderers (Blade, React, Vue) apply the same rules, so the canvas
 * previews what the front end prints: slides per view clamps to 1–6,
 * Tablet falls back to at most two slides and Mobile to one when the
 * author hasn't set a value at that size, and the control colours pass
 * through `safeCssValue()`.
 */

import { presetSlug } from '../../support/preset-slug';
import { safeCssValue } from '../../support/safe-css-value';

export type ControlsPlacement = 'below' | 'split' | 'overlay';

export interface CarouselAttributes {
    readonly slidesPerView: number;
    readonly showArrows: boolean;
    readonly showDots: boolean;
    readonly controlsPlacement: ControlsPlacement;
    readonly loop: boolean;
    readonly autoplay: boolean;
    readonly autoplayInterval: number;
    readonly dimInactive: boolean;
    readonly inactiveOpacity: number;
    readonly arrowColor: string;
    readonly arrowBackgroundColor: string;
    readonly dotColor: string;
    readonly dotActiveColor: string;
    readonly responsive?: Record<string, unknown>;
    readonly style?: Record<string, unknown>;
}

export interface SlidesPerView {
    readonly base: number;
    readonly tablet: number;
    readonly mobile: number;
}

export const MIN_SLIDES_PER_VIEW = 1;
export const MAX_SLIDES_PER_VIEW = 6;
export const MIN_AUTOPLAY_INTERVAL = 2;
export const MAX_AUTOPLAY_INTERVAL = 30;

export const PLACEMENTS: ReadonlyArray<ControlsPlacement> = ['below', 'split', 'overlay'];

function clampInt(value: unknown, min: number, max: number): number | null {
    const numeric = typeof value === 'number' ? value : typeof value === 'string' && value.trim() !== '' ? Number(value) : NaN;

    if (!Number.isFinite(numeric)) {
        return null;
    }

    return Math.min(max, Math.max(min, Math.round(numeric)));
}

/**
 * Slides per view at each size. `responsive` is the raw
 * `attributes.responsive` bag; its `slidesPerView.tablet` /
 * `slidesPerView.mobile` overrides win over the fallbacks.
 */
export function resolveSlidesPerView(base: unknown, responsive: unknown): SlidesPerView {
    const baseValue = clampInt(base, MIN_SLIDES_PER_VIEW, MAX_SLIDES_PER_VIEW) ?? 3;
    const overrides =
        responsive !== null && typeof responsive === 'object'
            ? ((responsive as Record<string, unknown>).slidesPerView as Record<string, unknown> | undefined)
            : undefined;
    const bag = overrides !== null && typeof overrides === 'object' ? overrides : {};

    return {
        base: baseValue,
        tablet: clampInt(bag.tablet, MIN_SLIDES_PER_VIEW, MAX_SLIDES_PER_VIEW) ?? Math.min(baseValue, 2),
        mobile: clampInt(bag.mobile, MIN_SLIDES_PER_VIEW, MAX_SLIDES_PER_VIEW) ?? 1,
    };
}

/** The slides per view in force at an editor breakpoint key. */
export function slidesPerViewAt(perView: SlidesPerView, breakpoint: string): number {
    if (breakpoint === 'mobile') {
        return perView.mobile;
    }

    if (breakpoint === 'tablet') {
        return perView.tablet;
    }

    return perView.base;
}

export function normalizePlacement(value: unknown): ControlsPlacement {
    return (PLACEMENTS as ReadonlyArray<unknown>).includes(value) ? (value as ControlsPlacement) : 'below';
}

export function normalizeAutoplayInterval(value: unknown): number {
    return clampInt(value, MIN_AUTOPLAY_INTERVAL, MAX_AUTOPLAY_INTERVAL) ?? 5;
}

export function normalizeInactiveOpacity(value: unknown): number {
    return clampInt(value, 0, 100) ?? 35;
}

/**
 * The custom properties the carousel stylesheet reads, in the order the
 * renderers print them.
 */
export function carouselStyleVariables(attributes: Partial<CarouselAttributes>, perView: SlidesPerView): Record<string, string> {
    const style: Record<string, string> = {
        '--ap-carousel-per-view': String(perView.base),
        '--ap-carousel-per-view-tablet': String(perView.tablet),
        '--ap-carousel-per-view-mobile': String(perView.mobile),
    };

    if (attributes.dimInactive === true) {
        style['--ap-carousel-inactive-opacity'] = String(normalizeInactiveOpacity(attributes.inactiveOpacity) / 100);
    }

    const colors: ReadonlyArray<readonly [keyof CarouselAttributes, string]> = [
        ['arrowColor', '--ap-carousel-arrow-color'],
        ['arrowBackgroundColor', '--ap-carousel-arrow-background'],
        ['dotColor', '--ap-carousel-dot-color'],
        ['dotActiveColor', '--ap-carousel-dot-active-color'],
    ];

    for (const [attribute, property] of colors) {
        const value = attributes[attribute];

        if (typeof value !== 'string') {
            continue;
        }

        const safe = safeCssValue(value);

        if (safe !== null) {
            style[property] = safe;
        }
    }

    return style;
}

/**
 * The Block spacing gap as a CSS value: `var:preset|spacing|big_gap` →
 * `var(--wp--preset--spacing--big-gap)`, with the slug normalised like the
 * declared custom properties; anything else as-is.
 */
export function blockGapValue(style: Record<string, unknown> | undefined): string | null {
    const spacing = style?.spacing as Record<string, unknown> | undefined;
    const gap = spacing?.blockGap;

    if (typeof gap !== 'string' || gap === '') {
        return null;
    }

    const match = gap.match(/^var:preset\|spacing\|(.+)$/);

    return match ? `var(--wp--preset--spacing--${presetSlug(match[1])})` : gap;
}

/** How many positions the dots stand for: one per first-visible slide. */
export function positionCount(slideCount: number, perView: number): number {
    return Math.max(1, slideCount - perView + 1);
}
