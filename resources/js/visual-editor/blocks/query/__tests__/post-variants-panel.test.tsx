/**
 * Tests for the Post Variants panel's `handleAdd` seeding behavior
 * (#604). When the user clicks "Add … variant", the new variant block
 * should be seeded with a deep clone of the post-template's current
 * non-variant inner blocks rather than starting empty.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render } from '@testing-library/react';

interface FakeBlock {
    clientId: string;
    name: string;
    attributes: Record<string, unknown>;
    innerBlocks?: FakeBlock[];
}

interface CreateBlockCall {
    name: string;
    attributes: Record<string, unknown>;
    innerBlocks: FakeBlock[];
}

interface InsertBlockCall {
    block: FakeBlock;
    index: number | undefined;
    rootClientId: string | undefined;
}

const createBlockCalls: CreateBlockCall[] = [];
const insertBlockCalls: InsertBlockCall[] = [];
const selectBlockCalls: string[] = [];

const fakeQueryChildren: FakeBlock[] = [];

let nextClientId = 0;
function newClientId(): string {
    nextClientId += 1;
    return `cid-${ nextClientId }`;
}

vi.mock( '@wordpress/blocks', () => ( {
    createBlock: ( name: string, attributes: Record<string, unknown>, innerBlocks: FakeBlock[] = [] ) => {
        const block: FakeBlock = {
            clientId: newClientId(),
            name,
            attributes,
            innerBlocks,
        };
        createBlockCalls.push( { name, attributes, innerBlocks } );
        return block;
    },
    cloneBlock: ( source: FakeBlock ): FakeBlock => ( {
        clientId: newClientId(),
        name: source.name,
        attributes: { ...source.attributes },
        innerBlocks: ( source.innerBlocks ?? [] ).map( ( child ) => ( {
            clientId: newClientId(),
            name: child.name,
            attributes: { ...child.attributes },
            innerBlocks: child.innerBlocks ?? [],
        } ) ),
    } ),
} ) );

vi.mock( '@wordpress/data', () => {
    const store = {
        getBlocks: () => fakeQueryChildren,
        getBlock: ( clientId: string ): FakeBlock | null => {
            const walk = ( blocks: FakeBlock[] ): FakeBlock | null => {
                for ( const block of blocks ) {
                    if ( block.clientId === clientId ) {
                        return block;
                    }
                    const nested = walk( block.innerBlocks ?? [] );
                    if ( nested !== null ) {
                        return nested;
                    }
                }
                return null;
            };
            return walk( fakeQueryChildren );
        },
    };
    return {
        select: () => store,
        useSelect: ( callback: ( selector: ( name: string ) => typeof store ) => unknown ) =>
            callback( () => store ),
        useDispatch: () => ( {
            insertBlock: ( block: FakeBlock, index?: number, rootClientId?: string ) => {
                insertBlockCalls.push( { block, index, rootClientId } );
            },
            moveBlockToPosition: () => undefined,
            removeBlock: () => undefined,
            selectBlock: ( clientId: string ) => {
                selectBlockCalls.push( clientId );
            },
            updateBlockAttributes: () => undefined,
        } ),
    };
} );

vi.mock( '@wordpress/components', async () => {
    const { createElement } = await import( 'react' );
    return {
        Button: ( props: Record<string, unknown> ) =>
            createElement( 'button', {
                type: 'button',
                onClick: props.onClick,
                'aria-label': props[ 'aria-label' ],
                disabled: props.disabled,
                children: props.children,
            } ),
        PanelBody: ( { children }: { children: React.ReactNode } ) =>
            createElement( 'section', null, children ),
    };
} );

vi.mock( '@wordpress/i18n', () => ( {
    __: ( text: string ) => text,
} ) );

import PostVariantsPanel from '../post-variants-panel';

const QUERY_CLIENT_ID = 'query-1';
const POST_TEMPLATE_CLIENT_ID = 'pt-1';

function setupPostTemplate( children: FakeBlock[] ): void {
    fakeQueryChildren.length = 0;
    fakeQueryChildren.push( {
        clientId: POST_TEMPLATE_CLIENT_ID,
        name: 'artisanpack/post-template',
        attributes: {},
        innerBlocks: children,
    } );
}

beforeEach( () => {
    createBlockCalls.length = 0;
    insertBlockCalls.length = 0;
    selectBlockCalls.length = 0;
    nextClientId = 0;
} );

describe( 'PostVariantsPanel handleAdd seeding (#604)', () => {
    it( 'seeds the new variant with a deep clone of the post-template base children', () => {
        setupPostTemplate( [
            {
                clientId: 'title-1',
                name: 'core/post-title',
                attributes: { level: 2 },
            },
            {
                clientId: 'cover-1',
                name: 'core/cover',
                attributes: { color: 'red' },
                innerBlocks: [
                    { clientId: 'inner-1', name: 'core/heading', attributes: { content: 'Hi' } },
                ],
            },
        ] );

        const { getByText } = render(
            <PostVariantsPanel queryClientId={ QUERY_CLIENT_ID } previewTotal={ 3 } />
        );

        fireEvent.click( getByText( 'Add position variant' ) );

        expect( createBlockCalls ).toHaveLength( 1 );
        const created = createBlockCalls[ 0 ];
        expect( created.name ).toBe( 'artisanpack/post-variant' );
        expect( created.attributes.matcher ).toEqual( { kind: 'position', value: 'first' } );
        // Seeded inner blocks deep-equal the base children by shape.
        expect( created.innerBlocks ).toHaveLength( 2 );
        expect( created.innerBlocks[ 0 ].name ).toBe( 'core/post-title' );
        expect( created.innerBlocks[ 0 ].attributes ).toEqual( { level: 2 } );
        expect( created.innerBlocks[ 1 ].name ).toBe( 'core/cover' );
        expect( created.innerBlocks[ 1 ].attributes ).toEqual( { color: 'red' } );
        // Cloned clientIds are independent from the originals.
        expect( created.innerBlocks[ 0 ].clientId ).not.toBe( 'title-1' );
        expect( created.innerBlocks[ 1 ].clientId ).not.toBe( 'cover-1' );
        // Nested innerBlocks are deep-cloned, not shared by reference.
        const nested = created.innerBlocks[ 1 ].innerBlocks ?? [];
        expect( nested ).toHaveLength( 1 );
        expect( nested[ 0 ].name ).toBe( 'core/heading' );
        expect( nested[ 0 ].attributes ).toEqual( { content: 'Hi' } );
        expect( nested[ 0 ].clientId ).not.toBe( 'inner-1' );
    } );

    it( 'excludes existing post-variant blocks from the seed', () => {
        setupPostTemplate( [
            { clientId: 'title-1', name: 'core/post-title', attributes: {} },
            {
                clientId: 'variant-existing',
                name: 'artisanpack/post-variant',
                attributes: { matcher: { kind: 'position', value: 'last' } },
                innerBlocks: [],
            },
        ] );

        const { getByText } = render(
            <PostVariantsPanel queryClientId={ QUERY_CLIENT_ID } previewTotal={ 3 } />
        );

        fireEvent.click( getByText( 'Add pattern variant' ) );

        const created = createBlockCalls[ 0 ];
        expect( created.innerBlocks.map( ( block ) => block.name ) ).toEqual( [
            'core/post-title',
        ] );
    } );

    it( 'selects the newly inserted variant so the inspector opens immediately', () => {
        setupPostTemplate( [
            { clientId: 'title-1', name: 'core/post-title', attributes: {} },
        ] );

        const { getByText } = render(
            <PostVariantsPanel queryClientId={ QUERY_CLIENT_ID } previewTotal={ 3 } />
        );

        fireEvent.click( getByText( 'Add metadata variant' ) );

        expect( insertBlockCalls ).toHaveLength( 1 );
        const insertedClientId = insertBlockCalls[ 0 ].block.clientId;
        expect( selectBlockCalls ).toContain( insertedClientId );
    } );

    it( 'seeds an empty variant when the post-template currently has no base children', () => {
        setupPostTemplate( [] );

        const { getByText } = render(
            <PostVariantsPanel queryClientId={ QUERY_CLIENT_ID } previewTotal={ 3 } />
        );

        fireEvent.click( getByText( 'Add position variant' ) );

        expect( createBlockCalls[ 0 ].innerBlocks ).toEqual( [] );
    } );
} );
