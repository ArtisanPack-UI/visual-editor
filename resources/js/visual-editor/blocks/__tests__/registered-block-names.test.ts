/**
 * Forks must create blocks that are registered (#1063). `createBlock()`
 * with an unregistered name throws, and only the `core/navigation` family
 * and `core/block` are registered from upstream.
 */

import { readdirSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

import { beforeAll, describe, expect, it, vi } from 'vitest';
import { createBlock, getBlockType, pasteHandler, registerBlockType, switchToBlockType, unregisterBlockType } from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';

import { registerArtisanPackBlocks } from '..';
import { TEMPLATE as quoteTemplate } from '../quote/edit';
import { mediaTextTemplate } from '../media-text/constants';
import quoteTransforms from '../quote/transforms';

const BLOCKS_DIR = resolve( __dirname, '..' );

beforeAll( () => {
    vi.spyOn( window.console, 'log' ).mockImplementation( () => undefined );
    registerArtisanPackBlocks();
} );

describe( 'Quote', () => {
    const prefix = quoteTransforms.from.find( ( { type } ) => 'prefix' === type ) as { transform: ( content: string ) => BlockInstance };

    it( 'runs the prefix shortcut against the registered blocks', () => {
        const quote = prefix.transform( 'Some text' );

        expect( quote.name ).toBe( 'artisanpack/quote' );
        expect( quote.innerBlocks.map( ( { name } ) => name ) ).toEqual( [ 'artisanpack/paragraph' ] );
        expect( String( quote.innerBlocks[ 0 ].attributes.content ) ).toBe( 'Some text' );
    } );

    it( 'converts a Verse', () => {
        const [ quote ] = switchToBlockType( createBlock( 'artisanpack/verse', { content: 'A line' } ), 'artisanpack/quote' ) as BlockInstance[];

        expect( quote.innerBlocks[ 0 ].name ).toBe( 'artisanpack/paragraph' );
        expect( String( quote.innerBlocks[ 0 ].attributes.content ) ).toBe( 'A line' );
    } );

    it( 'converts a Pullquote', () => {
        const [ quote ] = switchToBlockType( createBlock( 'artisanpack/pullquote', { value: 'Quoted', citation: 'Me' } ), 'artisanpack/quote' ) as BlockInstance[];

        expect( String( quote.attributes.citation ) ).toBe( 'Me' );
        expect( String( quote.innerBlocks[ 0 ].attributes.content ) ).toBe( 'Quoted' );
    } );

    it( 'uses a registered block in its template', () => {
        for ( const [ name ] of quoteTemplate ) {
            expect( getBlockType( name ) ).toBeDefined();
        }
    } );
} );

describe( 'forks', () => {
    /** Each transforms file's `from` section, which is what can actually run. */
    const fromSections = readdirSync( BLOCKS_DIR, { withFileTypes: true } )
        .filter( ( entry ) => entry.isDirectory() && ! entry.name.startsWith( '_' ) && '__tests__' !== entry.name )
        .flatMap( ( { name } ) => {
            try {
                const source = readFileSync( join( BLOCKS_DIR, name, 'transforms.ts' ), 'utf8' );

                return [ [ name, source.split( /\n\s*to:\s*\[/ )[ 0 ] ] as const ];
            } catch {
                return [];
            }
        } );

    it.each( fromSections )( '%s creates only registered blocks in its from transforms', ( _name, source ) => {
        const created = [ ...source.matchAll( /createBlock\(\s*'([a-z-]+\/[a-z-]+)'/g ) ].map( ( match ) => match[ 1 ] );

        expect( created.filter( ( name ) => undefined === getBlockType( name ) ) ).toEqual( [] );
    } );
} );

describe( 'media transforms', () => {
    it( 'makes a Group of a Cover and back without an extra wrapper', () => {
        const group = createBlock( 'artisanpack/group', {}, [ createBlock( 'artisanpack/paragraph', { content: 'Text' } ) ] );
        const [ cover ] = switchToBlockType( group, 'artisanpack/cover' ) as BlockInstance[];

        expect( cover.innerBlocks[ 0 ].name ).toBe( 'artisanpack/group' );

        const [ back ] = switchToBlockType( cover, 'artisanpack/group' ) as BlockInstance[];

        expect( back.name ).toBe( 'artisanpack/group' );
        expect( back.innerBlocks.map( ( { name } ) => name ) ).toEqual( [ 'artisanpack/paragraph' ] );
    } );

    it.each( [
        [ 'artisanpack/image', { url: '/a.png' } ],
        [ 'artisanpack/video', { src: '/a.mp4' } ],
        [ 'artisanpack/audio', { src: '/a.mp3' } ],
    ] )( 'turns %s into a File block', ( name, attributes ) => {
        const blocks = switchToBlockType( createBlock( name, { ...attributes, id: 1 } ), 'artisanpack/file' );

        expect( blocks?.[ 0 ].name ).toBe( 'artisanpack/file' );
    } );

    it( 'keeps an image with only an id when making a Gallery', () => {
        const [ gallery ] = switchToBlockType(
            [ createBlock( 'artisanpack/image', { id: 5 } ), createBlock( 'artisanpack/image', { url: '/b.png' } ) ],
            'artisanpack/gallery',
        ) as BlockInstance[];

        expect( gallery.innerBlocks ).toHaveLength( 2 );
    } );
} );

describe( 'templates', () => {
    it.each( [
        [ 'Media & Text', mediaTextTemplate ],
    ] )( '%s uses registered blocks', ( _label, template ) => {
        for ( const [ name ] of template() ) {
            expect( getBlockType( name ) ).toBeDefined();
        }
    } );
} );

describe( 'unmatched paste nodes (#1067)', () => {
    it( 'registers Custom HTML, hidden from the inserter without the ability', () => {
        expect( getBlockType( 'core/html' )?.supports?.inserter ).toBe( false );
    } );

    // The schema step runs `isMatch` itself, so this needs a selector that
    // matches before the schema strips the class it reads, and not after.
    it( 'pastes a node that stops matching its transform as Custom HTML', () => {
        registerBlockType( 'acme/widget', {
            title: 'Widget',
            category: 'widgets',
            attributes: {},
            transforms: {
                from: [ {
                    type: 'raw',
                    schema: { section: { children: { '#text': {} } } },
                    selector: 'section.acme',
                } ],
            },
            edit: () => null,
            save: () => null,
        } as never );

        const blocks = pasteHandler( { HTML: '<section class="acme">Kept</section>', mode: 'BLOCKS' } as never ) as BlockInstance[];

        unregisterBlockType( 'acme/widget' );

        expect( blocks.map( ( { name } ) => name ) ).toEqual( [ 'core/html' ] );
        expect( blocks[ 0 ].attributes.content ).toBe( '<section>Kept</section>' );
    } );
} );
