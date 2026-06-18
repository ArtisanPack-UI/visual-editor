/**
 * Tests for the shared `<QueryPreviewIterations>` multi-post renderer
 * (#599). Covers: multi-post rendering, the perPage cap, the read-only
 * state of non-first iterations, and the zero-result fallback.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';

interface FakeStoreState {
    innerBlocks: Array<{
        name: string;
        clientId?: string;
        attributes?: Record<string, unknown>;
        innerBlocks?: unknown[];
    }>;
    attributes: Record<string, unknown>;
    selectedClientId?: string | null;
    blockNames?: Record<string, string>;
    blockParents?: Record<string, string[]>;
}

const fakeStoreState: FakeStoreState = {
    innerBlocks: [
        { name: 'core/post-title', attributes: {}, innerBlocks: [] },
        { name: 'core/post-excerpt', attributes: {}, innerBlocks: [] },
    ],
    attributes: {},
    selectedClientId: null,
    blockNames: {},
    blockParents: {},
};

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
                {
                    'data-testid': 'ghost-preview-content',
                    'data-ghost-block-names': Array.isArray( opts.blocks )
                        ? opts.blocks
                              .map( ( block ) => ( block as { name?: string } ).name ?? '' )
                              .join( ',' )
                        : '',
                },
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
                innerBlocks: fakeStoreState.innerBlocks,
                attributes: fakeStoreState.attributes,
            } ),
            getSelectedBlockClientId: () => fakeStoreState.selectedClientId ?? null,
            getBlockName: ( clientId: string ) => fakeStoreState.blockNames?.[ clientId ],
            getBlockParents: ( clientId: string ) => fakeStoreState.blockParents?.[ clientId ] ?? [],
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

beforeEach( () => {
    fakeStoreState.innerBlocks = [
        { name: 'core/post-title', attributes: {}, innerBlocks: [] },
        { name: 'core/post-excerpt', attributes: {}, innerBlocks: [] },
    ];
    fakeStoreState.attributes = {};
    fakeStoreState.selectedClientId = null;
    fakeStoreState.blockNames = {};
    fakeStoreState.blockParents = {};
} );

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

    // ---------- Per-iteration variant resolution (#604) ----------

    it( 'renders a position:first variant only on iteration #1; other iterations render base', () => {
        fakeStoreState.innerBlocks = [
            { name: 'core/post-title', attributes: {}, innerBlocks: [] },
            { name: 'core/post-excerpt', attributes: {}, innerBlocks: [] },
            {
                name: 'artisanpack/post-variant',
                clientId: 'variant-1',
                attributes: { matcher: { kind: 'position', value: 'first' }, priority: 10 },
                innerBlocks: [
                    { name: 'core/heading', attributes: { content: 'testing' }, innerBlocks: [] },
                    { name: 'core/cover', attributes: {}, innerBlocks: [] },
                ],
            },
        ];

        const posts = [ makePost( 1 ), makePost( 2 ), makePost( 3 ) ];
        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview( { posts, total: 3, perPage: 3 } ) }
                postType="post"
            />
        );

        // Iteration 1 is editable; iterations 2 and 3 are ghosts.
        const ghosts = Array.from(
            container.querySelectorAll( '[data-query-iteration="preview"]' )
        );
        expect( ghosts ).toHaveLength( 2 );

        // Ghosts (#2 and #3) should preview the base children, NOT the variant content.
        for ( const ghost of ghosts ) {
            const blockNames = ghost
                .querySelector( '[data-ghost-block-names]' )
                ?.getAttribute( 'data-ghost-block-names' );
            expect( blockNames ).toBe( 'core/post-title,core/post-excerpt' );
            expect( ghost.getAttribute( 'data-resolved-variant-order' ) ).toBe( 'base' );
        }

        // The editable iteration #1 resolves to the variant.
        const editable = container.querySelector( '[data-query-iteration="editable"]' );
        expect( editable?.getAttribute( 'data-resolved-variant-order' ) ).toBe( '0' );
    } );

    it( 'renders a pattern:odd variant on iterations 1, 3, 5; base on 2, 4', () => {
        fakeStoreState.innerBlocks = [
            { name: 'core/post-title', attributes: {}, innerBlocks: [] },
            {
                name: 'artisanpack/post-variant',
                clientId: 'variant-odd',
                attributes: { matcher: { kind: 'pattern', value: 'odd' }, priority: 10 },
                innerBlocks: [
                    { name: 'core/heading', attributes: {}, innerBlocks: [] },
                ],
            },
        ];

        const posts = Array.from( { length: 5 }, ( _v, i ) => makePost( i + 1 ) );
        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview( { posts, total: 5, perPage: 5 } ) }
                postType="post"
            />
        );

        const iterations = Array.from(
            container.querySelectorAll( '[data-query-iteration]' )
        );
        const resolved = iterations.map( ( node ) =>
            node.getAttribute( 'data-resolved-variant-order' )
        );
        // Iteration 1 is editable; 2-5 are ghosts. All odd-positioned
        // iterations (1, 3, 5) resolve to the variant (order 0);
        // even-positioned (2, 4) resolve to base.
        expect( resolved ).toEqual( [ '0', 'base', '0', 'base', '0' ] );
    } );

    it( 'renders a meta:has-featured-image variant only on posts with a featured image', () => {
        fakeStoreState.innerBlocks = [
            { name: 'core/post-title', attributes: {}, innerBlocks: [] },
            {
                name: 'artisanpack/post-variant',
                clientId: 'variant-meta',
                attributes: {
                    matcher: { kind: 'meta', value: 'has-featured-image' },
                    priority: 10,
                },
                innerBlocks: [
                    { name: 'core/image', attributes: {}, innerBlocks: [] },
                ],
            },
        ];

        const posts: QueryPreviewPost[] = [
            { id: 1, title: 'Post 1' },
            {
                id: 2,
                title: 'Post 2',
                featuredImage: { url: 'https://example.com/img.jpg' },
            },
            { id: 3, title: 'Post 3' },
        ];

        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview( { posts, total: 3, perPage: 3 } ) }
                postType="post"
            />
        );

        const iterations = Array.from(
            container.querySelectorAll( '[data-query-iteration]' )
        );
        const resolved = iterations.map( ( node ) =>
            node.getAttribute( 'data-resolved-variant-order' )
        );
        // Post 2 has a featured image → variant; 1 and 3 → base.
        expect( resolved ).toEqual( [ 'base', '0', 'base' ] );
    } );

    it( 'honors compiled instance map from _compiledVariantMap', () => {
        fakeStoreState.innerBlocks = [
            { name: 'core/post-title', attributes: {}, innerBlocks: [] },
            {
                name: 'artisanpack/post-variant',
                clientId: 'variant-instance',
                attributes: {
                    matcher: { kind: 'position', value: 'instance:2' },
                    priority: 10,
                },
                innerBlocks: [ { name: 'core/heading', attributes: {}, innerBlocks: [] } ],
            },
        ];
        fakeStoreState.attributes = { _compiledVariantMap: { 1: 0 } };

        const posts = [ makePost( 1 ), makePost( 2 ), makePost( 3 ) ];
        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview( { posts, total: 3, perPage: 3 } ) }
                postType="post"
            />
        );

        const resolved = Array.from(
            container.querySelectorAll( '[data-query-iteration]' )
        ).map( ( node ) => node.getAttribute( 'data-resolved-variant-order' ) );
        // Static map pins iteration index 1 (i.e. post #2) to variant order 0.
        expect( resolved ).toEqual( [ 'base', '0', 'base' ] );
    } );

    it( 'auto-jumps the editable iteration to the first post matching the selected variant', () => {
        fakeStoreState.innerBlocks = [
            { name: 'core/post-title', attributes: {}, innerBlocks: [] },
            {
                name: 'artisanpack/post-variant',
                clientId: 'variant-last',
                attributes: { matcher: { kind: 'position', value: 'last' }, priority: 10 },
                innerBlocks: [],
            },
        ];
        fakeStoreState.selectedClientId = 'variant-last';
        fakeStoreState.blockNames = { 'variant-last': 'artisanpack/post-variant' };

        const posts = [ makePost( 1 ), makePost( 2 ), makePost( 3 ) ];
        const { container } = render(
            <QueryPreviewIterations
                clientId="abc"
                preview={ makePreview( { posts, total: 3, perPage: 3 } ) }
                postType="post"
            />
        );

        // `position:last` on a 3-post loop matches iteration #3 → post id 3.
        const editable = container.querySelector( '[data-query-iteration="editable"]' );
        expect( editable?.closest( '[data-block-context]' )?.getAttribute( 'data-block-context' ) )
            .toContain( '"postId":3' );
    } );
} );
