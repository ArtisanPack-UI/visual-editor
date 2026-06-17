/**
 * QueryPagination — edit component.
 *
 * Wrapper around the pagination children (previous / numbers / next).
 * Uses `useInnerBlocksProps` so the editor doesn't insert an extra
 * `block-editor-block-list__layout` wrapper between the flex container
 * (`wp-block-query-pagination`) and its children — without that, the
 * pagination row collapses into a left-aligned stack in the editor
 * because the flex children of the wrapper are an unrelated layout
 * div, not the pagination leaves (#599). Phase I-Block-Fork query
 * family (#521).
 */

import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

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
    // Add the upstream-compatible `wp-block-query-pagination` class
    // alongside the auto-generated `wp-block-artisanpack-query-pagination`.
    // `useBlockProps` only auto-injects the namespaced flavour; the
    // server-side renderers (Blade / React / Vue) emit the unprefixed
    // class so the front-end stylesheet targets that. Keeping both on
    // the editor wrapper lets the same `query-pagination.css`
    // selectors style the canvas and the public page (#599).
    const blockProps = ( useBlockProps as unknown as (
        props?: Record<string, unknown>
    ) => Record<string, unknown> )( { className: 'wp-block-query-pagination' } );
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const innerBlocksProps = ( useInnerBlocksProps as any )( blockProps, {
        template: [ ...DEFAULT_TEMPLATE ],
        allowedBlocks: [ ...ALLOWED_BLOCKS ],
    } );

    return <div { ...innerBlocksProps } />;
}
