/**
 * Mirrors the `blockGap` half of `BlockSupports::applySpacing()` from
 * the Blade renderer (#814). Reads `style.spacing.blockGap` off the
 * block attributes and returns a React-style declaration object:
 *
 * - a string value sets the `--wp--style--block-gap` custom property,
 *   which the block's stylesheet turns into a real `gap`;
 * - the per-axis object form sets `rowGap` (`top`) / `columnGap`
 *   (`left`) directly.
 *
 * Preset references (`var:preset|spacing|40`) expand to their CSS
 * custom property, same as the Blade twin. Empty object when unset.
 *
 * @since 1.12.0
 */

import { attrRecord } from './attributes';

export interface BlockGapStyle {
    '--wp--style--block-gap'?: string;
    rowGap?: string;
    columnGap?: string;
}

function stringAttr(value: unknown): string {
    if (typeof value === 'string') {
        return value.trim();
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
        return String(value);
    }

    return '';
}

/**
 * Expand Gutenberg's `var:preset|{taxonomy}|{slug}` shorthand into a
 * real CSS `var(--wp--preset--{taxonomy}--{slug})` reference. Mirrors
 * `BlockSupports::expandPresetReference()`.
 */
function expandPresetReference(value: string): string {
    const prefix = 'var:preset|';

    if (!value.startsWith(prefix)) {
        return value;
    }

    const parts = value
        .slice(prefix.length)
        .split('|')
        .map((segment) =>
            segment
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
        );

    return `var(--wp--preset--${parts.join('--')})`;
}

/**
 * Return the React style fragment derived from
 * `attributes.style.spacing.blockGap`.
 */
export function applyBlockGap(attributes: Record<string, unknown>): BlockGapStyle {
    const spacing = attrRecord(attrRecord(attributes.style).spacing);
    const blockGap = spacing.blockGap;
    const out: BlockGapStyle = {};

    if (typeof blockGap === 'string') {
        if (blockGap !== '') {
            out['--wp--style--block-gap'] = expandPresetReference(blockGap);
        }

        return out;
    }

    const axes = attrRecord(blockGap);
    const top = stringAttr(axes.top);
    const left = stringAttr(axes.left);

    if (top !== '') {
        out.rowGap = expandPresetReference(top);
    }

    if (left !== '') {
        out.columnGap = expandPresetReference(left);
    }

    return out;
}

/**
 * True when the returned block-gap style has at least one key set.
 */
export function hasBlockGapStyle(style: BlockGapStyle): boolean {
    return Object.keys(style).length > 0;
}
