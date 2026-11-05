/**
 * Importing pasted external images (#1066).
 */

import { afterEach, describe, expect, it, vi } from 'vitest';

const { speak } = vi.hoisted( () => ( { speak: vi.fn() } ) );

vi.mock( '@wordpress/a11y', () => ( { speak } ) );

import { setMediaImportAccess } from '../media-import-access';
import {
    findExternalImages,
    importExternalImages,
    isExternalImageUrl,
    offerPastedImageImport,
    requestImport,
} from '../pasted-image-import';

const ORIGIN = 'https://site.test';

function makeRegistry( blocks: Record< string, { name: string; attributes: Record< string, unknown > } > ) {
    const updateBlockAttributes = vi.fn( ( clientId: string, attributes: Record< string, unknown > ) => {
        Object.assign( blocks[ clientId ].attributes, attributes );
    } );
    const notices = {
        createInfoNotice: vi.fn(),
        createSuccessNotice: vi.fn(),
        createWarningNotice: vi.fn(),
    };

    const registry = {
        select: () => ( {
            getClientIdsWithDescendants: () => Object.keys( blocks ),
            getBlock: ( clientId: string ) => blocks[ clientId ] ?? null,
        } ),
        dispatch: () => ( { updateBlockAttributes, ...notices } ),
    };

    return { registry: registry as never, updateBlockAttributes, notices };
}

const image = ( url: string, extra: Record< string, unknown > = {} ) => ( {
    name: 'artisanpack/image',
    attributes: { url, alt: 'Alt', ...extra },
} );

const respond = ( status: number, body: unknown ) =>
    Promise.resolve( new Response( JSON.stringify( body ), { status } ) );

afterEach( () => setMediaImportAccess( false ) );

describe( 'isExternalImageUrl', () => {
    it.each( [
        [ 'https://cdn.example.com/a.png', true ],
        [ 'http://cdn.example.com/a.png', true ],
        [ 'https://site.test/a.png', false ],
        [ '/a.png', false ],
        [ 'blob:https://site.test/1', false ],
        [ 'data:image/png;base64,AAAA', false ],
        [ undefined, false ],
    ] )( 'reads %s', ( url, expected ) => {
        expect( isExternalImageUrl( url, ORIGIN ) ).toBe( expected );
    } );
} );

describe( 'findExternalImages', () => {
    it( 'finds only unattached external images', () => {
        const { registry } = makeRegistry( {
            a: image( 'https://cdn.example.com/a.png' ),
            b: image( 'https://cdn.example.com/b.png', { id: 5 } ),
            c: image( 'https://site.test/c.png' ),
            d: { name: 'artisanpack/paragraph', attributes: { url: 'https://cdn.example.com/d.png' } },
        } );

        expect( findExternalImages( registry, [ 'a', 'b', 'c', 'd', 'gone' ], ORIGIN ) ).toEqual( [
            { clientId: 'a', url: 'https://cdn.example.com/a.png', alt: 'Alt' },
        ] );
    } );
} );

describe( 'requestImport', () => {
    it( 'posts the URL and alt text with the CSRF token', async () => {
        document.head.innerHTML = '<meta name="csrf-token" content="token">';

        const fetchImpl = vi.fn( () => respond( 201, { id: 1, url: '/m/a.png' } ) );

        await expect( requestImport( { url: 'https://x.test/a.png', alt: 'Alt' }, fetchImpl as never ) ).resolves.toEqual( { id: 1, url: '/m/a.png' } );

        const [ url, init ] = fetchImpl.mock.calls[ 0 ] as unknown as [ string, RequestInit ];

        expect( url ).toBe( '/visual-editor/api/media/import' );
        expect( init.headers ).toMatchObject( { 'X-CSRF-TOKEN': 'token' } );
        expect( JSON.parse( init.body as string ) ).toEqual( { url: 'https://x.test/a.png', alt: 'Alt' } );
    } );

    it( 'resolves a protocol-relative URL and trims long alt text', async () => {
        const fetchImpl = vi.fn( () => respond( 201, { id: 1, url: '/m/a.png' } ) );

        await requestImport( { url: '//cdn.x.test/a.png', alt: 'a'.repeat( 1200 ) }, fetchImpl as never );

        const [ , init ] = fetchImpl.mock.calls[ 0 ] as unknown as [ string, RequestInit ];
        const body       = JSON.parse( init.body as string );

        expect( body.url ).toBe( `${ window.location.protocol }//cdn.x.test/a.png` );
        expect( body.alt ).toHaveLength( 1000 );
    } );

    it( 'throws on a refused import', async () => {
        await expect( requestImport( { url: 'x', alt: '' }, ( () => respond( 422, {} ) ) as never ) ).rejects.toThrow();
    } );
} );

describe( 'importExternalImages', () => {
    it( 'announces the import before the first download finishes', async () => {
        speak.mockClear();

        const blocks = { a: image( 'https://x.test/a.png' ), b: image( 'https://x.test/b.png' ) };
        const { registry } = makeRegistry( blocks );
        let spokenBeforeFirstFetch: boolean | undefined;

        const fetchImpl = vi.fn( () => {
            if ( undefined === spokenBeforeFirstFetch ) {
                spokenBeforeFirstFetch = speak.mock.calls.some( ( [ message ] ) => 'Importing 2 images…' === message );
            }

            return respond( 201, { id: 1, url: '/m/x.png' } );
        } );

        await importExternalImages( registry, [
            { clientId: 'a', url: 'https://x.test/a.png', alt: 'Alt' },
            { clientId: 'b', url: 'https://x.test/b.png', alt: 'Alt' },
        ], fetchImpl as never );

        expect( spokenBeforeFirstFetch ).toBe( true );
        expect( speak ).toHaveBeenCalledWith( 'Importing 2 images…' );
    } );

    it( 'counts only the images still waiting to be imported', async () => {
        speak.mockClear();

        const blocks = { a: image( 'https://x.test/a.png' ), b: image( '/m/already-local.png' ) };
        const { registry } = makeRegistry( blocks );

        await importExternalImages( registry, [
            { clientId: 'a', url: 'https://x.test/a.png', alt: 'Alt' },
            { clientId: 'b', url: 'https://x.test/b.png', alt: 'Alt' },
            { clientId: 'gone', url: 'https://x.test/c.png', alt: 'Alt' },
        ], ( () => respond( 201, { id: 1, url: '/m/x.png' } ) ) as never );

        expect( speak ).toHaveBeenCalledWith( 'Importing 1 image…' );
    } );

    it( 'says nothing when every image has changed or gone', async () => {
        speak.mockClear();

        await importExternalImages( makeRegistry( { a: image( '/m/local.png' ) } ).registry, [
            { clientId: 'a', url: 'https://x.test/a.png', alt: 'Alt' },
        ], vi.fn() as never );

        expect( speak ).not.toHaveBeenCalled();
    } );

    it( 'says nothing when there is nothing to import', async () => {
        speak.mockClear();

        await importExternalImages( makeRegistry( {} ).registry, [], vi.fn() as never );

        expect( speak ).not.toHaveBeenCalled();
    } );

    it( 'swaps in the local URL, leaving the alt text', async () => {
        const blocks = { a: image( 'https://x.test/a.png' ) };
        const { registry, notices } = makeRegistry( blocks );

        await importExternalImages( registry, [ { clientId: 'a', url: 'https://x.test/a.png', alt: 'Alt' } ], ( () => respond( 201, { id: 9, url: '/m/a.png' } ) ) as never );

        expect( blocks.a.attributes ).toEqual( { url: '/m/a.png', id: 9, alt: 'Alt' } );
        expect( notices.createSuccessNotice ).toHaveBeenCalledOnce();
        expect( notices.createWarningNotice ).not.toHaveBeenCalled();
    } );

    it( 'keeps the external URL when an import fails, and carries on', async () => {
        const blocks = { a: image( 'https://x.test/a.png' ), b: image( 'https://x.test/b.png' ) };
        const { registry, notices } = makeRegistry( blocks );

        const fetchImpl = vi.fn()
            .mockImplementationOnce( () => respond( 422, { message: 'No' } ) )
            .mockImplementationOnce( () => respond( 201, { id: 2, url: '/m/b.png' } ) );

        await importExternalImages( registry, [
            { clientId: 'a', url: 'https://x.test/a.png', alt: 'Alt' },
            { clientId: 'b', url: 'https://x.test/b.png', alt: 'Alt' },
        ], fetchImpl as never );

        expect( blocks.a.attributes.url ).toBe( 'https://x.test/a.png' );
        expect( blocks.b.attributes.url ).toBe( '/m/b.png' );
        expect( notices.createWarningNotice ).toHaveBeenCalledOnce();
        expect( notices.createSuccessNotice ).toHaveBeenCalledOnce();
    } );

    it( 'keeps the external URL when the network fails', async () => {
        const blocks = { a: image( 'https://x.test/a.png' ) };
        const { registry, notices } = makeRegistry( blocks );

        await importExternalImages( registry, [ { clientId: 'a', url: 'https://x.test/a.png', alt: '' } ], ( () => Promise.reject( new Error( 'offline' ) ) ) as never );

        expect( blocks.a.attributes.url ).toBe( 'https://x.test/a.png' );
        expect( notices.createWarningNotice ).toHaveBeenCalledOnce();
    } );

    it( 'leaves a block the author has since changed', async () => {
        const blocks = { a: image( 'https://x.test/other.png' ) };
        const { registry, updateBlockAttributes } = makeRegistry( blocks );

        const fetchImpl = vi.fn();

        await importExternalImages( registry, [ { clientId: 'a', url: 'https://x.test/a.png', alt: '' } ], fetchImpl as never );

        expect( fetchImpl ).not.toHaveBeenCalled();
        expect( updateBlockAttributes ).not.toHaveBeenCalled();
    } );

    it( 'skips a block that has gone, and reports nothing', async () => {
        const { registry, notices } = makeRegistry( {} );
        const fetchImpl = vi.fn();

        await importExternalImages( registry, [ { clientId: 'gone', url: 'https://x.test/a.png', alt: '' } ], fetchImpl as never );

        expect( fetchImpl ).not.toHaveBeenCalled();
        expect( notices.createSuccessNotice ).not.toHaveBeenCalled();
        expect( notices.createWarningNotice ).not.toHaveBeenCalled();
    } );

    it( 'sends the alt text from when the import starts', async () => {
        const blocks = { a: image( 'https://x.test/a.png', { alt: 'Edited' } ) };
        const { registry } = makeRegistry( blocks );
        const fetchImpl = vi.fn( () => respond( 201, { id: 2, url: '/m/a.png' } ) );

        await importExternalImages( registry, [ { clientId: 'a', url: 'https://x.test/a.png', alt: 'Stale' } ], fetchImpl as never );

        const [ , init ] = fetchImpl.mock.calls[ 0 ] as unknown as [ string, RequestInit ];

        expect( JSON.parse( init.body as string ).alt ).toBe( 'Edited' );
    } );

    it( 'leaves a block given a media item during the upload', async () => {
        const blocks = { a: image( 'https://x.test/a.png' ) };
        const { registry, updateBlockAttributes } = makeRegistry( blocks );

        const fetchImpl = vi.fn( () => {
            blocks.a.attributes.id = 99;

            return respond( 201, { id: 2, url: '/m/a.png' } );
        } );

        await importExternalImages( registry, [ { clientId: 'a', url: 'https://x.test/a.png', alt: '' } ], fetchImpl as never );

        expect( updateBlockAttributes ).not.toHaveBeenCalled();
    } );

    it( 'does not count a block removed while its image downloaded', async () => {
        const blocks: Record< string, ReturnType< typeof image > > = { a: image( 'https://x.test/a.png' ) };
        const { registry, notices } = makeRegistry( blocks );

        const fetchImpl = vi.fn( () => {
            delete blocks.a;

            return respond( 201, { id: 2, url: '/m/a.png' } );
        } );

        await importExternalImages( registry, [ { clientId: 'a', url: 'https://x.test/a.png', alt: '' } ], fetchImpl as never );

        expect( notices.createSuccessNotice ).not.toHaveBeenCalled();
        expect( notices.createWarningNotice ).not.toHaveBeenCalled();
    } );
} );

describe( 'offerPastedImageImport', () => {
    const connect = () => setMediaImportAccess( true );

    it( 'prompts for new external images and downloads nothing until asked', () => {
        connect();

        const fetchSpy = vi.spyOn( globalThis, 'fetch' );
        const { registry, notices } = makeRegistry( { old: image( 'https://x.test/old.png' ), fresh: image( 'https://x.test/new.png' ) } );

        offerPastedImageImport( registry, new Set( [ 'old' ] ), ORIGIN );

        expect( notices.createInfoNotice ).toHaveBeenCalledOnce();
        expect( notices.createInfoNotice.mock.calls[ 0 ][ 0 ] ).toContain( '1 pasted image' );
        expect( fetchSpy ).not.toHaveBeenCalled();

        fetchSpy.mockRestore();
    } );

    it( 'imports the new images when the notice action is clicked', async () => {
        connect();

        const fetchSpy = vi.spyOn( globalThis, 'fetch' ).mockImplementation( () => respond( 201, { id: 3, url: '/m/new.png' } ) );
        const { registry, notices, updateBlockAttributes } = makeRegistry( { fresh: image( 'https://x.test/new.png' ) } );

        offerPastedImageImport( registry, new Set(), ORIGIN );

        notices.createInfoNotice.mock.calls[ 0 ][ 1 ].actions[ 0 ].onClick();

        await vi.waitFor( () => expect( updateBlockAttributes ).toHaveBeenCalledOnce() );

        expect( fetchSpy.mock.calls[ 0 ][ 0 ] ).toBe( '/visual-editor/api/media/import' );

        fetchSpy.mockRestore();
    } );

    it( 'stays quiet with no new external images', () => {
        connect();

        const { registry, notices } = makeRegistry( { old: image( 'https://x.test/old.png' ), local: image( '/a.png' ) } );

        offerPastedImageImport( registry, new Set( [ 'old' ] ), ORIGIN );

        expect( notices.createInfoNotice ).not.toHaveBeenCalled();
    } );

    it( 'stays quiet when the server can not import', () => {
        const { registry, notices } = makeRegistry( { fresh: image( 'https://x.test/new.png' ) } );

        offerPastedImageImport( registry, new Set(), ORIGIN );

        expect( notices.createInfoNotice ).not.toHaveBeenCalled();
    } );
} );
