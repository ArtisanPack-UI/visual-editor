/**
 * Gallery — block-gap style override.
 *
 * Ported from `@wordpress/block-library/src/gallery/gap-styles.js`
 * (v9.43.0). Emits a per-instance `--wp--style--unstable-gallery-gap`
 * + `gap` declaration based on the block's `style.spacing.blockGap`.
 */

import type { ReactElement } from 'react';
import {
    __experimentalGetGapCSSValue as getGapCSSValue,
    useStyleOverride,
} from '@wordpress/block-editor';

type BlockGap =
    | string
    | undefined
    | {
          readonly top?: string;
          readonly left?: string;
      };

interface GapStylesProps {
    readonly blockGap: BlockGap;
    readonly clientId: string;
}

export default function GapStyles({
    blockGap,
    clientId,
}: GapStylesProps): ReactElement | null {
    // --gallery-block--gutter-size is deprecated. Themes setting a default
    // gap on the gallery should use --wp--style--gallery-gap-default.
    const fallbackValue =
        'var( --wp--style--gallery-gap-default, var( --gallery-block--gutter-size, var( --wp--style--block-gap, 0.5em ) ) )';
    let gapValue: string = fallbackValue;
    let column: string = fallbackValue;
    let row: string | undefined;

    if (!!blockGap) {
        row =
            typeof blockGap === 'string'
                ? (getGapCSSValue(blockGap) as string)
                : (getGapCSSValue(blockGap?.top) as string) || fallbackValue;
        column =
            typeof blockGap === 'string'
                ? (getGapCSSValue(blockGap) as string)
                : (getGapCSSValue(blockGap?.left) as string) || fallbackValue;
        gapValue = row === column ? row : `${row} ${column}`;
    }

    const gap = `#block-${clientId} {
        --wp--style--unstable-gallery-gap: ${column === '0' ? '0px' : column};
        gap: ${gapValue}
    }`;

    (useStyleOverride as (override: { css: string }) => void)({ css: gap });

    return null;
}
