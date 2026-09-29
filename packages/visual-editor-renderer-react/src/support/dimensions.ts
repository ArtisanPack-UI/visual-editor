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

    const minHeight = stringAttr(dimensions.minHeight);

    if (minHeight !== '') {
        out.minHeight = expandPresetReference(minHeight);
    }

    const aspectRatio = stringAttr(dimensions.aspectRatio);

    if (aspectRatio !== '') {
        out.aspectRatio = expandPresetReference(aspectRatio);
    }

    return out;
}

/**
 * True when the returned dimension style has at least one key set.
 */
export function hasDimensionStyle(style: DimensionStyle): boolean {
    return style.minHeight !== undefined || style.aspectRatio !== undefined;
}
