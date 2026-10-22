/**
 * Mirrors `BlockSupports::applyDimensions()` from the Blade renderer
 * (#776). Reads `style.dimensions.minHeight` and
 * `style.dimensions.aspectRatio` off the block attributes and returns a
 * React-style declaration object (camelCased CSS keys) so the block
 * partial can spread it onto the wrapper element.
 *
 * A block only opts into these keys via its `block.json`
 * (`supports.dimensions.minHeight: true` etc.). Callers should invoke
 * this helper unconditionally against every block's attribute tree; if
 * the editor never wrote the key the helper returns an empty object.
 * `dimensions.transform` is intentionally NOT handled here — it flows
 * through the states pipeline in the Blade renderer.
 *
 * @since 1.9.0
 */

import { attrRecord } from './attributes';
import { safeCssValue } from './cssValue';
import { expandPresetReference } from './presetReference';

function stringAttr(value: unknown): string {
    if (typeof value === 'string') {
        return value.trim();
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
        return String(value);
    }

    return '';
}

export interface DimensionStyle {
    minHeight?: string;
    aspectRatio?: string;
}

/**
 * Return the React style fragment derived from
 * `attributes.style.dimensions`. Empty object when nothing is set.
 */
export function applyDimensions(attributes: Record<string, unknown>): DimensionStyle {
    const style = attrRecord(attributes.style);
    const dimensions = attrRecord(style.dimensions);
    const out: DimensionStyle = {};

    // An aspect ratio wins over min-height, as in WordPress's dimensions
    // support: `min-height: unset` keeps a block's default min-height
    // (cover's 430px) from overriding the ratio (#906).
    // A rejected ratio counts as unset (#920 review).
    const aspectRatio = safeDimension(dimensions.aspectRatio);

    if (aspectRatio !== null) {
        out.aspectRatio = aspectRatio;
        out.minHeight = 'unset';

        return out;
    }

    const minHeight = safeDimension(dimensions.minHeight);

    if (minHeight !== null) {
        out.minHeight = minHeight;
    }

    return out;
}

/**
 * True when the returned dimension style has at least one key set.
 */
export function hasDimensionStyle(style: DimensionStyle): boolean {
    return style.minHeight !== undefined || style.aspectRatio !== undefined;
}

/**
 * The `has-aspect-ratio` class WordPress's dimensions support adds when the
 * block has an aspect ratio, mirroring `BlockSupports::applyDimensions()`
 * (#906). `null` otherwise, so it drops out of a class list.
 */
export function dimensionsClassName(attributes: Record<string, unknown>): string | null {
    const dimensions = attrRecord(attrRecord(attributes.style).dimensions);

    return safeDimension(dimensions.aspectRatio) !== null ? 'has-aspect-ratio' : null;
}

/**
 * A dimensions value with its preset reference expanded, or `null` when it
 * is unset or fails `safeCssValue()` — as `BlockSupports::supportValue()`
 * drops it in the Blade renderer (#920 review), so a stored `;` can't
 * append declarations.
 *
 * @since 1.15.3
 */
function safeDimension(value: unknown): string | null {
    return safeCssValue(expandPresetReference(stringAttr(value)));
}
