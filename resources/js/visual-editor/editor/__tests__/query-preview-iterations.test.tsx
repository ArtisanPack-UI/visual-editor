/**
 * Tests for the shared `<QueryPreviewIterations>` multi-post renderer
 * (#599). Covers: multi-post rendering, the perPage cap, the read-only
 * state of non-first iterations, and the zero-result fallback.
 */

import { describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock( '@wordpress/block-editor', async () => {
    const { createElement } = await import( 'react' );
    return {
        BlockContextProvider: ( {
            value,
            children,
        }: {
            value: Record<string, unknown>;
            children: React.ReactNode;
        } ) =>
            createElement(
                'div',
                {
                    'data-block-context': JSON.stringify( {
                        postType: value.postType,
                        postId: value.postId,
                    } ),
                },
                children
            ),
        InnerBlocks: () => createElement( 'div', { 'data-testid': 'inner-blocks' }, 'Editable inner blocks' ),
        useBlockProps: Object.assign(
            ( props?: Record<string, unknown> ) => ( {
                className: typeof props?.className === 'string' ? props.className : 'wp-block',
            } ),
            { save: () => ( {} ) }
        ),
        store: 'block-editor-store',
        // Stub the live preview hook to return predictable props so we
        // can assert iterations rendered without spinning up the
        // nested editor scope the real hook uses.
        __experimentalUseBlockPreview: ( opts: { blocks: unknown[]; props?: Record<string, unknown> } ) => ( {
            ...( opts.props ?? {} ),
            className: 'block-editor-block-preview__live-content',
            children: createElement(
                'div',
                { 'data-testid': 'ghost-preview-content' },
                `Preview of ${ Array.isArray( opts.blocks ) ? opts.blocks.length : 0 } block(s)`
            ),
        } ),
    };
} );

vi.mock( '@wordpress/blocks', () => ( {} ) );

vi.mock( '@wordpress/data', () => ( {
    useSelect: ( callback: ( select: ( storeName: unknown ) => unknown ) => unknown ) => {
        const fakeStore = {
            getBlock: () => ( {
                innerBlocks: [
                    { name: 'core/post-title', attributes: {}, innerBlocks: [] },
                    { name: 'core/post-excerpt', attributes: {}, innerBlocks: [] },
                ],
            } ),
        };
        return callback( () => fakeStore );
    },
} ) );

vi.mock( '@wordpress/components', async () => {
    const { createElement } = await import( 'react' );
    return {
        Notice: ( { children }: { children: React.ReactNode } ) =>
            createElement( 'div', { role: 'note' }, children ),
    };
} );

vi.mock( '@wordpress/i18n', () => ( {
    __: ( text: string ) => text,
} ) );

import { QueryPreviewIterations } from '../query-preview-iterations';
import type { QueryPreviewContextValue } from '../query-preview-context';
import type { QueryPreviewPost } from '../use-query-preview';

function makePost( id: number ): QueryPreviewPost {
    return { id, title: `Post ${ id }` };
}

function makePreview( overrides: Partial<QueryPreviewContextValue> = {} ): QueryPreviewContextValue {
    return {
        posts: [ makePost( 1 ), makePost( 2 ), makePost( 3 ) ],
        total: 3,
        currentPage: 1,
        queryTitle: '',
        perPage: 3,
        status: 'ready',
        ...overrides,
    };
}

describe( 'QueryPreviewIterations', () => {
    it( 'renders one editable iteration plus N-1 read-only ghosts for a 3-post query', () => {
        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview() }
                postType="post"
            />
        );

        const iterations = container.querySelectorAll( '[data-query-iteration]' );
        expect( iterations ).toHaveLength( 3 );

        const editable = container.querySelectorAll( '[data-query-iteration="editable"]' );
        const ghosts = container.querySelectorAll( '[data-query-iteration="preview"]' );
        expect( editable ).toHaveLength( 1 );
        expect( ghosts ).toHaveLength( 2 );
    } );

    it( 'wraps each iteration in its own BlockContextProvider keyed by post id', () => {
        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview() }
                postType="page"
            />
        );

        const providers = container.querySelectorAll( '[data-block-context]' );
        expect( providers ).toHaveLength( 3 );

        const postIds = Array.from( providers ).map( ( node ) => {
            const ctx = JSON.parse( ( node.getAttribute( 'data-block-context' ) ?? '{}' ) ) as {
                postType: string;
                postId: number;
            };
            return { postType: ctx.postType, postId: ctx.postId };
        } );

        expect( postIds ).toEqual( [
            { postType: 'page', postId: 1 },
            { postType: 'page', postId: 2 },
            { postType: 'page', postId: 3 },
        ] );
    } );

    it( 'renders InnerBlocks only inside the editable iteration', () => {
        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview() }
                postType="post"
            />
        );

        const innerBlocks = container.querySelectorAll( '[data-testid="inner-blocks"]' );
        expect( innerBlocks ).toHaveLength( 1 );

        const editable = container.querySelector( '[data-query-iteration="editable"]' );
        expect( editable?.querySelector( '[data-testid="inner-blocks"]' ) ).not.toBeNull();
    } );

    it( 'exposes non-first iterations as clickable buttons that switch the editable iteration', () => {
        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview() }
                postType="post"
            />
        );

        const ghosts = container.querySelectorAll( '[data-query-iteration="preview"]' );

        for ( const ghost of Array.from( ghosts ) ) {
            expect( ghost.getAttribute( 'role' ) ).toBe( 'button' );
            expect( ghost.getAttribute( 'tabindex' ) ).toBe( '0' );
            // `useBlockPreview` applies its preview-content class so
            // CSS targeting the disabled preview surface still works.
            expect( ghost.getAttribute( 'class' ) ).toContain( 'block-editor-block-preview__live-content' );
        }
    } );

    it( 'renders all iterations as <li> elements inside a <ul> by default', () => {
        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview() }
                postType="post"
            />
        );

        const list = container.querySelector( 'ul' );
        expect( list ).not.toBeNull();

        const items = container.querySelectorAll( 'li' );
        expect( items.length ).toBe( 3 );
    } );

    it( 'promotes a clicked ghost iteration to the editable iteration', async () => {
        const { fireEvent } = await import( '@testing-library/react' );
        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview() }
                postType="post"
            />
        );

        // Initially the first iteration is editable.
        let editable = container.querySelector( '[data-query-iteration="editable"]' );
        expect( editable?.closest( '[data-block-context]' )?.getAttribute( 'data-block-context' ) )
            .toContain( '"postId":1' );

        // Click the second ghost — it should become editable.
        const ghosts = container.querySelectorAll( '[data-query-iteration="preview"]' );
        fireEvent.click( ghosts[ 0 ] );

        editable = container.querySelector( '[data-query-iteration="editable"]' );
        expect( editable?.closest( '[data-block-context]' )?.getAttribute( 'data-block-context' ) )
            .toContain( '"postId":2' );
    } );

    it( 'caps iterations at the lower of perPage or the hard cap (12)', () => {
        const posts = Array.from( { length: 20 }, ( _value, index ) => makePost( index + 1 ) );
        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview( { posts, total: 20, perPage: 20 } ) }
                postType="post"
            />
        );

        const iterations = container.querySelectorAll( '[data-query-iteration]' );
        expect( iterations.length ).toBe( 12 );
    } );

    it( 'caps at perPage when perPage is smaller than the resolved post count', () => {
        const posts = Array.from( { length: 8 }, ( _value, index ) => makePost( index + 1 ) );
        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview( { posts, total: 8, perPage: 3 } ) }
                postType="post"
            />
        );

        const iterations = container.querySelectorAll( '[data-query-iteration]' );
        expect( iterations.length ).toBe( 3 );
    } );

    it( 'falls back to a single editable iteration + notice when no posts resolved', () => {
        const { container, getByRole } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview( { posts: [], total: 0 } ) }
                postType="post"
            />
        );

        const innerBlocks = container.querySelectorAll( '[data-testid="inner-blocks"]' );
        expect( innerBlocks ).toHaveLength( 1 );
        expect( getByRole( 'note' ) ).not.toBeNull();
    } );

    it( 'falls back without a notice when the preview is still loading (no resolved data yet)', () => {
        const { container, queryByRole } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview( { posts: [], total: 0, status: 'loading' } ) }
                postType="post"
            />
        );

        expect( container.querySelectorAll( '[data-testid="inner-blocks"]' ) ).toHaveLength( 1 );
        // No notice during loading — we don't know yet whether the query
        // actually resolves to zero.
        expect( queryByRole( 'note' ) ).toBeNull();
    } );

    it( 'falls back gracefully when preview is null (block placed outside a query)', () => {
        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ null }
                postType="post"
            />
        );

        expect( container.querySelectorAll( '[data-testid="inner-blocks"]' ) ).toHaveLength( 1 );
    } );

    it( 'surfaces a cap notice when perPage exceeds the hard cap', () => {
        const posts = Array.from( { length: 15 }, ( _value, index ) => makePost( index + 1 ) );
        const { getByRole } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview( { posts, total: 50, perPage: 50 } ) }
                postType="post"
            />
        );

        expect( getByRole( 'note' ) ).not.toBeNull();
    } );
} );
