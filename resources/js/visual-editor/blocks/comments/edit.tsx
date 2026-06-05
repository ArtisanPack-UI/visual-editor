/**
 * Comments — edit component.
 *
 * Wrapper around the comments block tree. Renders `<InnerBlocks />`
 * with a default template containing `artisanpack/comment-template`
 * so the editor experience matches the rendered tree out of the box.
 * Comments family fork (#519).
 */

import type { ReactElement } from 'react';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

const DEFAULT_TEMPLATE: ReadonlyArray<[string, Record<string, unknown>, ReadonlyArray<unknown>?]> = [
    [
        'artisanpack/comment-template',
        {},
        [
            [ 'artisanpack/comment-author-name' ],
            [ 'artisanpack/comment-date' ],
            [ 'artisanpack/comment-content' ],
            [ 'artisanpack/comment-reply-link' ],
        ],
    ] as [string, Record<string, unknown>, ReadonlyArray<unknown>],
];

export default function CommentsEdit(): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    return (
        <div { ...blockProps }>
            <InnerBlocks template={ DEFAULT_TEMPLATE as unknown as Array<[string]> } />
        </div>
    );
}
