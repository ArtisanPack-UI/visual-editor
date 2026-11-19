/**
 * Carousel slide — save component (#1127).
 *
 * Dynamic block: the renderers walk the persisted inner-block tree. The
 * slide hosts its blocks inside a wrapper `<div>` (see `edit.tsx`), so
 * `save()` reproduces that wrapper or Gutenberg's validator flags the
 * saved markup as invalid (#747).
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function CarouselSlideSave(): ReactElement {
    const blockProps = (useBlockProps.save as any)({ className: 'ap-carousel-slide' });
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);

    return <div {...innerBlocksProps} />;
}
