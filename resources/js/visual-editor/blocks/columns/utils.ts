/**
 * Columns — width math utilities.
 *
 * Ported from `@wordpress/block-library/src/columns/utils.js` (v9.43.0).
 * Behaviour unchanged; types added for TypeScript consumption.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

interface WPBlockLike {
    clientId?: string;
    attributes: Record<string, any>;
}

export const toWidthPrecision = (value: unknown): number | undefined => {
    const unitlessValue = parseFloat(value as string);
    return Number.isFinite(unitlessValue)
        ? parseFloat(unitlessValue.toFixed(2))
        : undefined;
};

export function getEffectiveColumnWidth(
    block: WPBlockLike,
    totalBlockCount: number
): number {
    const { width = 100 / totalBlockCount } = block.attributes;
    return toWidthPrecision(width) ?? 0;
}

export function getTotalColumnsWidth(
    blocks: WPBlockLike[],
    totalBlockCount: number = blocks.length
): number {
    return blocks.reduce(
        (sum, block) => sum + getEffectiveColumnWidth(block, totalBlockCount),
        0
    );
}

export function getColumnWidths(
    blocks: WPBlockLike[],
    totalBlockCount: number = blocks.length
): Record<string, number> {
    return blocks.reduce<Record<string, number>>((accumulator, block) => {
        const width = getEffectiveColumnWidth(block, totalBlockCount);
        if (block.clientId) {
            accumulator[block.clientId] = width;
        }
        return accumulator;
    }, {});
}

export function getRedistributedColumnWidths(
    blocks: WPBlockLike[],
    availableWidth: number,
    totalBlockCount: number = blocks.length
): Record<string, number> {
    const totalWidth = getTotalColumnsWidth(blocks, totalBlockCount);
    return Object.fromEntries(
        Object.entries(getColumnWidths(blocks, totalBlockCount)).map(
            ([clientId, width]) => {
                const newWidth = (availableWidth * width) / totalWidth;
                return [clientId, toWidthPrecision(newWidth) ?? 0];
            }
        )
    );
}

export function hasExplicitPercentColumnWidths(blocks: WPBlockLike[]): boolean {
    return blocks.every((block) => {
        const blockWidth = block.attributes.width;
        return Number.isFinite(
            typeof blockWidth === 'string' && blockWidth.endsWith('%')
                ? parseFloat(blockWidth)
                : blockWidth
        );
    });
}

export function getMappedColumnWidths(
    blocks: WPBlockLike[],
    widths: Record<string, number>
): WPBlockLike[] {
    return blocks.map((block) => ({
        ...block,
        attributes: {
            ...block.attributes,
            width: `${widths[block.clientId ?? '']}%`,
        },
    }));
}

export function getWidths(
    blocks: WPBlockLike[],
    withParsing: boolean = true
): Array<number | string> {
    return blocks.map((innerColumn) => {
        const innerColumnWidth =
            innerColumn.attributes.width || 100 / blocks.length;
        return withParsing ? parseFloat(innerColumnWidth) : innerColumnWidth;
    });
}

export function getWidthWithUnit(width: string | number, unit: string): string {
    let w: string | number = width;
    if (0 > parseFloat(w as string)) {
        w = '0';
    }
    if (isPercentageUnit(unit)) {
        w = Math.min(parseFloat(w as string), 100);
    }
    return `${w}${unit}`;
}

export function isPercentageUnit(unit: string): boolean {
    return unit === '%';
}
