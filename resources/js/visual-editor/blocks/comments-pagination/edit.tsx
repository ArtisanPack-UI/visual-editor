/**
 * CommentsPagination — edit component.
 *
 * Wrapper around the pagination children (previous / numbers / next).
 * Renders `<InnerBlocks />` with the upstream default template so the
 * editor experience matches the rendered pagination row out of the
 * box. Comments family fork (#519) Pass 2.
 */

import type { ReactElement } from 'react';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

const DEFAULT_TEMPLATE: ReadonlyArray<[string]> = [
    [ 'artisanpack/comments-pagination-previous' ],
    [ 'artisanpack/comments-pagination-numbers' ],
    [ 'artisanpack/comments-pagination-next' ],
];

const ALLOWED_BLOCKS: ReadonlyArray<string> = [
    'artisanpack/comments-pagination-previous',
    'artisanpack/comments-pagination-numbers',
    'artisanpack/comments-pagination-next',
];

export default function CommentsPaginationEdit(): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    return (
        <div { ...blockProps }>
            <InnerBlocks
                template={ [ ...DEFAULT_TEMPLATE ] }
                allowedBlocks={ [ ...ALLOWED_BLOCKS ] }
            />
        </div>
    );
}
