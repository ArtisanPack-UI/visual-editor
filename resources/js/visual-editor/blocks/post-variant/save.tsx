/**
 * Post Variant — save component.
 *
 * Dynamic-only: the server-side `QueryInliner` reads the variant's
 * serialized `innerBlocks` and clones them per matching post. The
 * client save emits a wrapper so the parent post-template's saved
 * tree can round-trip the variant's children verbatim.
 */

import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function PostVariantSave(): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any ).save();
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const innerBlocksProps = ( useInnerBlocksProps as any ).save( blockProps );

    return <div { ...innerBlocksProps } />;
}
