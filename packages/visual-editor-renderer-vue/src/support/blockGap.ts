/**
 * Mirrors the `blockGap` half of `BlockSupports::applySpacing()` from
 * the Blade renderer (#814). Reads `style.spacing.blockGap` off the
 * block attributes and returns a Vue-style declaration object (passed
 * as the `style` prop):
 *
 * - a string value sets the `--wp--style--block-gap` custom property,
 *   which the block's stylesheet turns into a real `gap`;
 * - the per-axis object form sets `rowGap` (`top`) / `columnGap`
 *   (`left`) directly.
 *
 * Preset references (`var:preset|spacing|40`) expand to their CSS
 * custom property, same as the Blade twin, and unsafe values are dropped
 * through `blockGapValue()` (F-10). Empty object when unset.
 *
 * @since 1.12.0
 */

import { attrRecord } from './attributes';
import { blockGapValue } from './layoutContainer';

export interface BlockGapStyle {
    '--wp--style--block-gap'?: string;
    rowGap?: string;
    columnGap?: string;
}

/**
 * Return the Vue style fragment derived from
 * `attributes.style.spacing.blockGap`. Values that could add declarations
 * (`1rem;position:fixed`) are dropped (F-10), as in the Blade twin.
 */
export function applyBlockGap(attributes: Record<string, unknown>): BlockGapStyle {
    const spacing = attrRecord(attrRecord(attributes.style).spacing);
    const blockGap = spacing.blockGap;
    const out: BlockGapStyle = {};

    if (typeof blockGap === 'string') {
        const gap = blockGapValue(blockGap);

        if (gap !== null) {
            out['--wp--style--block-gap'] = gap;
        }

        return out;
    }

    const axes = attrRecord(blockGap);
    const top = blockGapValue(axes.top);
    const left = blockGapValue(axes.left);

    if (top !== null) {
        out.rowGap = top;
    }

    if (left !== null) {
        out.columnGap = left;
    }

    return out;
}

/**
 * True when the returned block-gap style has at least one key set.
 */
export function hasBlockGapStyle(style: BlockGapStyle): boolean {
    return Object.keys(style).length > 0;
}
