/**
 * Comment Template — edit component.
 *
 * Per-comment loop wrapper. Renders `<InnerBlocks />` with a default
 * template of comment-author-name + comment-date + comment-content +
 * comment-reply-link so authors get a useful starting layout. The
 * server-side `CommentInliner` clones the saved template once per
 * resolved comment and stamps each iteration with the per-comment
 * `_resolved*` attributes through `CommentResolver`.
 *
 * Comments family fork (#519).
 */

import type { ReactElement } from 'react';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

const DEFAULT_TEMPLATE: ReadonlyArray<[string]> = [
    [ 'artisanpack/comment-author-name' ],
    [ 'artisanpack/comment-date' ],
    [ 'artisanpack/comment-content' ],
    [ 'artisanpack/comment-reply-link' ],
];

export default function CommentTemplateEdit(): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )( {
        className: 'wp-block-comment-template',
    } );

    return (
        <ol { ...blockProps }>
            <li>
                <InnerBlocks template={ [ ...DEFAULT_TEMPLATE ] } />
            </li>
        </ol>
    );
}
