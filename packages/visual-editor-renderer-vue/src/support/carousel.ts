/**
 * Carousel block support (#1127).
 *
 * Mirrors the attribute rules in the Blade renderer's `carousel.blade.php`:
 * slides per view clamps to 1–6, Tablet falls back to at most two slides
 * and Mobile to one, the autoplay interval clamps to 2–30 seconds and the
 * control colours pass through `safeCssValue()`. The mirror lives at the
 * matching path in the Vue renderer.
 */

import { attrBoolean, attrRecord, attrString } from './attributes';
import { cssNumber } from './cssNumber';
import { safeCssValue } from './cssValue';

/**
 * The carousel's user-facing text. Blade translates these through `__()`;
 * here a host passes its translations to {@link setCarouselLabels}.
 * `:current` / `:total` in `slidePosition` are the slide's number and the
 * slide count, and `:index` in `dot` is the dot's number, filled in by
 * the front-end script.
 */
export interface CarouselLabels {
    region: string;
    carousel: string;
    slide: string;
    slidePosition: string;
    previous: string;
    next: string;
    dots: string;
    dot: string;
    pause: string;
    play: string;
}

const DEFAULT_CAROUSEL_LABELS: Readonly<CarouselLabels> = {
    region: 'Carousel',
    carousel: 'carousel',
    slide: 'slide',
    slidePosition: ':current of :total',
    previous: 'Previous slide',
    next: 'Next slide',
    dots: 'Choose a slide',
    dot: 'Go to slide :index',
    pause: 'Pause',
    play: 'Play',
};

let labels: CarouselLabels = { ...DEFAULT_CAROUSEL_LABELS };

/**
 * Replace some or all of the carousel's text, for example with the host's
 * translations. Call it before rendering. Calling it with no argument
 * restores the English defaults. A key that's missing, `undefined` or not
 * a string keeps its current text.
 */
export function setCarouselLabels(next?: Partial<CarouselLabels>): void {
    if (next === undefined) {
        labels = { ...DEFAULT_CAROUSEL_LABELS };

        return;
    }

    const merged: CarouselLabels = { ...labels };

    for (const key of Object.keys(DEFAULT_CAROUSEL_LABELS) as Array<keyof CarouselLabels>) {
        const value = next[key];

        if (typeof value === 'string') {
            merged[key] = value;
        }
    }

    labels = merged;
}

/** The carousel text in use. */
export function getCarouselLabels(): Readonly<CarouselLabels> {
    return labels;
}

export type CarouselPlacement = 'below' | 'split' | 'overlay';

const PLACEMENTS: ReadonlyArray<CarouselPlacement> = ['below', 'split', 'overlay'];

const COLOR_PROPERTIES: ReadonlyArray<readonly [string, string]> = [
    ['arrowColor', '--ap-carousel-arrow-color'],
    ['arrowBackgroundColor', '--ap-carousel-arrow-background'],
    ['dotColor', '--ap-carousel-dot-color'],
    ['dotActiveColor', '--ap-carousel-dot-active-color'],
];

export interface ResolvedCarousel {
    readonly placement: CarouselPlacement;
    readonly showArrows: boolean;
    readonly showDots: boolean;
    readonly loop: boolean;
    /** Seconds per slide, or `null` when autoplay is off. */
    readonly autoplay: number | null;
    readonly dimInactive: boolean;
    /** Custom properties, in the order Blade prints them. */
    readonly style: Record<string, string>;
}

/** PHP `is_numeric()` + `round()` + clamp, or `null` for a non-number. */
function clampNumber(value: unknown, min: number, max: number): number | null {
    let numeric: number;

    if (typeof value === 'number') {
        numeric = value;
    } else if (typeof value === 'string' && value.trim() !== '' && /^\s*[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/.test(value)) {
        numeric = Number(value);
    } else {
        return null;
    }

    if (!Number.isFinite(numeric)) {
        return null;
    }

    // PHP's round() rounds halves away from zero.
    const rounded = numeric < 0 ? -Math.round(-numeric) : Math.round(numeric);

    return Math.max(min, Math.min(max, rounded));
}

/** Normalise a carousel's attributes the way `carousel.blade.php` does. */
export function resolveCarousel(attributes: Record<string, unknown>): ResolvedCarousel {
    const base = clampNumber(attributes.slidesPerView, 1, 6) ?? 3;
    const overrides = attrRecord(attrRecord(attributes.responsive).slidesPerView);
    const tablet = clampNumber(overrides.tablet, 1, 6) ?? Math.min(base, 2);
    const mobile = clampNumber(overrides.mobile, 1, 6) ?? 1;

    const rawPlacement = attrString(attributes.controlsPlacement, 'below');
    const placement = (PLACEMENTS as ReadonlyArray<string>).includes(rawPlacement)
        ? (rawPlacement as CarouselPlacement)
        : 'below';
    const dimInactive = attrBoolean(attributes.dimInactive, false);

    const style: Record<string, string> = {
        '--ap-carousel-per-view': String(base),
        '--ap-carousel-per-view-tablet': String(tablet),
        '--ap-carousel-per-view-mobile': String(mobile),
    };

    if (dimInactive) {
        const opacity = clampNumber(attributes.inactiveOpacity, 0, 100) ?? 35;
        style['--ap-carousel-inactive-opacity'] = cssNumber(opacity / 100);
    }

    for (const [attribute, property] of COLOR_PROPERTIES) {
        const color = safeCssValue(attrString(attributes[attribute]));

        if (color !== null) {
            style[property] = color;
        }
    }

    return {
        placement,
        showArrows: attrBoolean(attributes.showArrows, true),
        showDots: attrBoolean(attributes.showDots, true),
        loop: attrBoolean(attributes.loop, false),
        autoplay: attrBoolean(attributes.autoplay, false) ? clampNumber(attributes.autoplayInterval, 2, 30) ?? 5 : null,
        dimInactive,
        style,
    };
}

/** Fill `:name` placeholders, as Laravel's translator does. */
export function fillLabel(template: string, replacements: Record<string, string | number>): string {
    let result = template;

    for (const [key, value] of Object.entries(replacements)) {
        result = result.split(`:${key}`).join(String(value));
    }

    return result;
}

/** The chevron path each arrow draws. */
export const CAROUSEL_ARROW_PATHS = {
    previous: 'M15 6l-6 6 6 6',
    next: 'M9 6l6 6-6 6',
} as const;
