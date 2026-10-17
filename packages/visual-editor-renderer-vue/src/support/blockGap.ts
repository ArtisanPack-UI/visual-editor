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

export interface ColumnsGapStyle {
    '--ap-columns-gap'?: string;
}

/**
 * Mirrors `LayoutSupport::columnsGapDeclaration()` (#914). A Columns
 * block whose `blockGap` is a per-axis object only prints
 * `rowGap` / `columnGap`, so the Column width calc read the baseline's
 * `--ap-columns-gap: var(--wp--style--block-gap)` (the global gap). This
 * hands the `left` value to that calc instead, expanded and sanitised
 * through `blockGapValue()` exactly like `columnGap` (F-10). A string
 * `blockGap` needs nothing: it already sets `--wp--style--block-gap`.
 *
 * The calc multiplies the gap, so a bare number can't go in as-is:
 * `calc(52.5% - 0 * 0.475)` is invalid and leaves the Column with no basis.
 * A unitless zero becomes `0px`; any other bare number is no length (so
 * `columnGap` ignores it too) and nothing is emitted.
 *
 * Only the Columns renderer calls this — the property is scoped to that
 * block so it never leaks onto other flex containers.
 *
 * @since 1.15.2
 */
export function applyColumnsGap(attributes: Record<string, unknown>): ColumnsGapStyle {
    const blockGap = attrRecord(attrRecord(attributes.style).spacing).blockGap;

    if (blockGap === null || typeof blockGap !== 'object') {
        return {};
    }

    const left = blockGapValue(attrRecord(blockGap).left);

    if (left === null) {
        return {};
    }

    // #914 — a bare number would make the width calc invalid.
    if (/^[+-]?(?:\d+\.?\d*|\.\d+)$/.test(left)) {
        return Number(left) === 0 ? { '--ap-columns-gap': '0px' } : {};
    }

    return { '--ap-columns-gap': left };
}
