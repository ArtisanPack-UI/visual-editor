/**
 * Carousel slide — editor-side component (#1127).
 *
 * Child of `artisanpack/carousel`. A plain container: it accepts any
 * blocks and starts with an empty Group. The parent lays the slides out
 * and the renderers add each slide's "n of N" label at render time.
 */

import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const TEMPLATE: [string, Record<string, unknown>][] = [['artisanpack/group', {}]];

export default function CarouselSlideEdit(): ReactElement {
    const blockProps = useBlockProps({ className: 'ap-carousel-slide' });
    const innerBlocksProps = useInnerBlocksProps(blockProps, { template: TEMPLATE });

    return <div {...innerBlocksProps} />;
}
