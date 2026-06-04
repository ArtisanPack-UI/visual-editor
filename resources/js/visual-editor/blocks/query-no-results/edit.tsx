/**
 * QueryNoResults — edit component.
 *
 * Wrapper block whose inner template is shown when the surrounding
 * `artisanpack/query` resolves to zero results. The editor renders the
 * configured inner blocks as a regular `<InnerBlocks />` tree so authors
 * can style the empty state alongside the rest of the loop; the front
 * end only emits the wrapper when `_resolvedTotal === 0`. Phase I-Block-Fork
 * query family (#521).
 */

import type { ReactElement } from 'react';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

const DEFAULT_TEMPLATE: ReadonlyArray<[ string, Record<string, unknown> ]> = [
    [
        'artisanpack/paragraph',
        {
            placeholder: __(
                'Add text or blocks that will display when the query returns no results.',
                TEXT_DOMAIN
            ),
        },
    ],
];

export default function QueryNoResultsEdit(): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    return (
        <div { ...blockProps }>
            <InnerBlocks
                template={ [ ...DEFAULT_TEMPLATE ] }
            />
        </div>
    );
}
