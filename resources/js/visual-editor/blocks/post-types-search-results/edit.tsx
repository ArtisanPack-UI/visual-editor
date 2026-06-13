/**
 * Post Types Search Results — editor-side component (#502).
 *
 * Wrapper that accepts one or more
 * `artisanpack/single-post-types-search-results` children. Each child
 * owns its own post-type filter so the same parent can host result
 * sections for multiple post types.
 */

import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED_BLOCKS: string[] = ['artisanpack/single-post-types-search-results'];

const TEMPLATE: [string, Record<string, unknown>][] = [
    ['artisanpack/single-post-types-search-results', { postType: 'all' }],
];

export default function PostTypesSearchResultsEdit(): ReactElement {
    const blockProps = useBlockProps({ className: 'ap-post-types-search-results' });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        allowedBlocks: ALLOWED_BLOCKS,
        template: TEMPLATE,
    });

    return <div {...innerBlocksProps} />;
}
