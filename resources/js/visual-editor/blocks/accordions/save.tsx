/**
 * Accordions — save component.
 *
 * The accordion family ships as dynamic (server-rendered) blocks: each
 * renderer reads the persisted inner-block tree and emits the final
 * markup. But the block hosts inner blocks inside a wrapper `<div>` (see
 * `edit.tsx`), so `save()` must reproduce that wrapper — mirroring the
 * editor's `useBlockProps` className — or Gutenberg's validator flags the
 * saved markup as "unexpected or invalid content" (#747).
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function AccordionsSave(): ReactElement {
    const blockProps = (useBlockProps.save as any)({ className: 'ap-accordions' });
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);
    return <div {...innerBlocksProps} />;
}
