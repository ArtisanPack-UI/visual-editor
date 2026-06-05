/**
 * Query Loop — save component.
 *
 * Ported from `@wordpress/block-library/src/query/save.js` (v9.43.0): the
 * saved markup is the configured `tagName` wrapper around the serialized
 * inner blocks (the `artisanpack/post-template` shell). The server-side
 * `QueryInliner` expands that shell once per resolved post on the
 * public-render path. Phase I6 loop / feed cluster (#414).
 */

import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

interface QuerySaveProps {
    attributes: { tagName?: string };
}

export default function QuerySave( { attributes }: QuerySaveProps ): ReactElement {
    const Tag = ( attributes.tagName ?? 'div' ) as keyof JSX.IntrinsicElements;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any ).save();
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const innerBlocksProps = ( useInnerBlocksProps as any ).save( blockProps );

    return <Tag { ...innerBlocksProps } />;
}
