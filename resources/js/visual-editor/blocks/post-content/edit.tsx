/**
 * Post Content — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolved*` attributes.
 * The editor shows a read-only, text-only preview (#992). Resolution
 * priority, highest first:
 *
 *  1. Stamped `_resolvedContent` attribute.
 *  2. `artisanpack/postPreview` context — the query loop's resolved post
 *     (#483) or the site editor's template preview entry (#993).
 *  3. The entry being edited (`postType` / `postId` block context, no
 *     `queryId`): its live block tree from the canvas, so the preview
 *     tracks unsaved edits for posts, pages and custom content types
 *     alike. Recursion guard: every Post Content block is dropped from
 *     the tree before it is serialized, so the block never previews
 *     itself (or another copy of the entry's content).
 *  4. The live entity's server-rendered `content.rendered`.
 *  5. Clearly-labelled placeholder when there's genuinely no content.
 *
 * Phase I5 entity cluster (#413).
 */

import { useEffect, useState, type ReactElement } from 'react';
import {
    store as blockEditorStore,
    useBlockProps,
} from '@wordpress/block-editor';
import { serialize } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    createEntityPlaceholderEdit,
    htmlToText,
    isInQueryLoop,
    readPostEntityIdentity,
    readQueryPreviewPost,
} from '../_shared/entity-placeholder-edit';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyProps = Record<string, any>;

interface BlockLike {
    readonly name?: unknown;
    readonly innerBlocks?: readonly BlockLike[];
}

const POST_CONTENT_BLOCK_NAMES: ReadonlySet<string> = new Set( [
    'artisanpack/post-content',
    'core/post-content',
] );

const label = (): string => __( 'Post Content', TEXT_DOMAIN );

/**
 * Reads the HTML string out of an entity `content` field — the WP
 * envelope's `rendered` (the server-rendered body), else a plain string.
 * `raw` is skipped on purpose: the adapters ship it empty.
 */
function readRenderedContent( value: unknown ): string {
    if ( typeof value === 'string' ) {
        return value;
    }

    if ( value !== null && typeof value === 'object' ) {
        const rendered = ( value as { rendered?: unknown } ).rendered;

        if ( typeof rendered === 'string' ) {
            return rendered;
        }
    }

    return '';
}

const ReadonlyPostContentEdit = createEntityPlaceholderEdit( {
    label,
    resolvedKey: '_resolvedContent',
    kind: 'html',
    fromQueryPreview: ( post ) =>
        typeof post.content === 'string' && post.content !== ''
            ? { text: post.content }
            : null,
    fromPostEntity: ( entity ) => {
        const content = readRenderedContent( entity.content );
        return content !== '' ? { text: content } : null;
    },
} );

// No live sources: the current-entry path falls back to the bare chip
// rather than the stale saved `content.rendered`.
const PlaceholderPostContentEdit = createEntityPlaceholderEdit( {
    label,
    resolvedKey: '_resolvedContent',
    kind: 'html',
} );

/**
 * Drops every Post Content block from a block tree (recursively) — the
 * recursion guard for the current-entry preview.
 *
 * @since 1.15.3
 */
export function withoutPostContentBlocks<T extends BlockLike>( blocks: readonly T[] ): T[] {
    return blocks
        .filter( ( block ) => ! ( typeof block.name === 'string' && POST_CONTENT_BLOCK_NAMES.has( block.name ) ) )
        .map( ( block ) =>
            Array.isArray( block.innerBlocks ) && block.innerBlocks.length > 0
                ? { ...block, innerBlocks: withoutPostContentBlocks( block.innerBlocks ) }
                : block
        );
}

/**
 * Plain-text preview of the entry's live block tree, minus its Post
 * Content blocks. Returns '' when nothing is left or serialization
 * fails (e.g. an unregistered block type).
 *
 * @since 1.15.3
 */
export function entryContentPreviewText( blocks: readonly BlockLike[] ): string {
    const tree = withoutPostContentBlocks( blocks );

    if ( tree.length === 0 ) {
        return '';
    }

    try {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        return htmlToText( serialize( tree as any ) ).trim();
    } catch {
        return '';
    }
}

/** Pause after the last canvas edit before the preview text refreshes. */
const PREVIEW_DEBOUNCE_MS = 400;

function LivePostContentPreview( { text }: { readonly text: string } ): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    return <div { ...blockProps }>{ text }</div>;
}

function CurrentEntryPostContent( { blockEditProps }: { readonly blockEditProps: AnyProps } ): ReactElement {
    const rootBlocks = useSelect(
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        ( select: any ) => ( select( blockEditorStore )?.getBlocks?.() ?? [] ) as BlockLike[],
        []
    );
    // The canvas tree changes on every keystroke; recomputing the preview
    // (serialize + parse of the whole entry) is deferred until editing
    // pauses so long entries don't lag while typing.
    const [ text, setText ] = useState( () => entryContentPreviewText( rootBlocks ) );

    useEffect( () => {
        const timer = setTimeout( () => {
            setText( entryContentPreviewText( rootBlocks ) );
        }, PREVIEW_DEBOUNCE_MS );

        return () => clearTimeout( timer );
    }, [ rootBlocks ] );

    if ( text === '' ) {
        return <PlaceholderPostContentEdit { ...blockEditProps } />;
    }

    return <LivePostContentPreview text={ text } />;
}

export default function PostContentEdit( props: AnyProps ): ReactElement {
    const attributes = props.attributes ?? {};
    const context = props.context ?? {};

    const isCurrentEntry =
        readPostEntityIdentity( context ) !== null
        && ! isInQueryLoop( context )
        && readQueryPreviewPost( context ) === null
        && ( typeof attributes._resolvedContent !== 'string' || attributes._resolvedContent === '' );

    if ( isCurrentEntry ) {
        return <CurrentEntryPostContent blockEditProps={ props } />;
    }

    return <ReadonlyPostContentEdit { ...props } />;
}
