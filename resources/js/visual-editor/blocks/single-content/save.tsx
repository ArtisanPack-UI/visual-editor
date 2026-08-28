/**
 * Single Content — save component.
 *
 * Dynamic block: the server-side `QueryInliner` re-resolves the persisted
 * inner-block tree against the chosen post. But the block hosts inner
 * blocks inside a wrapper `<div>` (see `edit.tsx`), so `save()` must
 * reproduce that wrapper — mirroring the editor's `useBlockProps`
 * className — or Gutenberg's validator flags the saved markup as
 * "unexpected or invalid content" (#747). (#501)
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function SingleContentSave(): ReactElement {
    const blockProps = (useBlockProps.save as any)({
        className: 'ap-single-content',
    });
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);
    return <div {...innerBlocksProps} />;
}
