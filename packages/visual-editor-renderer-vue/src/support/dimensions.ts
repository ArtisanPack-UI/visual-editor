/**
 * Mirrors `BlockSupports::applyDimensions()` from the Blade renderer
 * (#776). Reads `style.dimensions.minHeight` and
 * `style.dimensions.aspectRatio` off the block attributes and returns a
 * Vue-style declaration object (camelCased CSS keys) so the block
 * partial can pass it as the `style` prop.
 *
 * A block only opts into these keys via its `block.json`
 * (`supports.dimensions.minHeight: true` etc.). Callers should invoke
 * this helper unconditionally; if the editor never wrote the key the
 * helper returns an empty object. `dimensions.transform` is
 * intentionally NOT handled here — it flows through the states
 * pipeline in the Blade renderer.
 *
 * @since 1.9.0
 */

import { attrRecord } from './attributes';

function stringAttr(value: unknown): string {
    if (typeof value === 'string') {
        return value.trim();
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
        return String(value);
    }

    return '';
}

function expandPresetReference(value: string): string {
    const prefix = 'var:preset|';

    if (!value.startsWith(prefix)) {
        return value;
    }

    const parts = value
        .slice(prefix.length)
        .split('|')
        .map(kebabCase);

    return `var(--wp--preset--${parts.join('--')})`;
}

function kebabCase(segment: string): string {
    return segment
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export interface DimensionStyle {
    minHeight?: string;
    aspectRatio?: string;
}

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

export function hasDimensionStyle(style: DimensionStyle): boolean {
    return style.minHeight !== undefined || style.aspectRatio !== undefined;
}
