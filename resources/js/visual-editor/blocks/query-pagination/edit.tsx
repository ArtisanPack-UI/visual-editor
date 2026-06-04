/**
 * QueryPagination — edit component.
 *
 * Wrapper around the pagination children (previous / numbers / next).
 * Renders `<InnerBlocks />` with the upstream default template so the
 * editor experience matches the rendered pagination row out of the box.
 * Phase I-Block-Fork query family (#521).
 */

import type { ReactElement } from 'react';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

const DEFAULT_TEMPLATE: ReadonlyArray<[ string ]> = [
    [ 'artisanpack/query-pagination-previous' ],
    [ 'artisanpack/query-pagination-numbers' ],
    [ 'artisanpack/query-pagination-next' ],
];

const ALLOWED_BLOCKS: ReadonlyArray<string> = [
    'artisanpack/query-pagination-previous',
    'artisanpack/query-pagination-numbers',
    'artisanpack/query-pagination-next',
];

export default function QueryPaginationEdit(): ReactElement {
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
