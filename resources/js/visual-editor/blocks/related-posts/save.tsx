/**
 * Related Posts — save component.
 *
 * Dynamic block: the server-side `QueryInliner` clones the persisted
 * inner-block tree once per related post and emits the final markup.
 * But the block hosts inner blocks inside a wrapper `<div>` (see
 * `edit.tsx`), so `save()` must reproduce that exact wrapper —
 * mirroring the editor's `useBlockProps` className — or Gutenberg's
 * validator flags the saved markup as "unexpected or invalid content"
 * (#747). Returning `<InnerBlocks.Content />` alone is not enough: the
 * wrapper div is part of the serialized markup that the theme's
 * `single-post` template persists, so it must round-trip too. (#501)
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function RelatedPostsSave(): ReactElement {
    const blockProps = (useBlockProps.save as any)({
        className: 'ap-related-posts',
    });
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);
    return <div {...innerBlocksProps} />;
}
