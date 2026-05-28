/**
 * Table — rowspan/colspan normalizer.
 *
 * Ported from `@wordpress/block-library/src/table/utils.js` (v9.43.0).
 * Returns undefined for non-positive numbers or the default value (1).
 */

export function normalizeRowColSpan(
    rowColSpan: string | number | null | undefined
): string | undefined {
    const parsedValue = parseInt(String(rowColSpan ?? ''), 10);
    if (!Number.isInteger(parsedValue)) {
        return undefined;
    }
    // Treat zero as "no span" so an explicit colspan="0"/rowspan="0"
    // doesn't get serialized into saved markup; upstream filters on the
    // same condition.
    return parsedValue <= 0 || parsedValue === 1
        ? undefined
        : parsedValue.toString();
}
