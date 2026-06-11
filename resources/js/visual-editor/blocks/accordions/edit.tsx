/**
 * Accordions — editor-side component.
 *
 * Parent block in the accordion family (#497). Renders a single block
 * `div` that hosts one or more `artisanpack/accordion` children via
 * `useInnerBlocksProps`. No inspector controls of its own — styling
 * and layout are surfaced through the standard block supports
 * (background, border, spacing, …) declared in `block.json`.
 */

import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED_BLOCKS: string[] = ['artisanpack/accordion'];

const TEMPLATE: [string, Record<string, unknown>][] = [['artisanpack/accordion', {}]];

export default function AccordionsEdit(): ReactElement {
    const blockProps = useBlockProps({ className: 'ap-accordions' });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        allowedBlocks: ALLOWED_BLOCKS,
        template: TEMPLATE,
    });

    return <div {...innerBlocksProps} />;
}
