import { describe, expect, it } from 'vitest';

import {
    type VariantDescriptor,
    compileStaticMap,
    matchMeta,
    matchPattern,
    matchPosition,
    resolveVariant,
    sortVariants,
} from '../variant-matcher';

describe( 'matchPosition', () => {
    it( 'matches first / last / nth / range', () => {
        expect( matchPosition( { kind: 'position', value: 'first' }, 0, 5 ) ).toBe( true );
        expect( matchPosition( { kind: 'position', value: 'first' }, 1, 5 ) ).toBe( false );
        expect( matchPosition( { kind: 'position', value: 'last' }, 4, 5 ) ).toBe( true );
        expect( matchPosition( { kind: 'position', value: 'last' }, 3, 5 ) ).toBe( false );
        expect( matchPosition( { kind: 'position', value: 'nth:3' }, 2, 5 ) ).toBe( true );
        expect( matchPosition( { kind: 'position', value: 'range:2-4' }, 1, 5 ) ).toBe( true );
        expect( matchPosition( { kind: 'position', value: 'range:2-4' }, 4, 5 ) ).toBe( false );
    } );

    it( 'ignores instance: matchers (handled in static-map compile)', () => {
        expect( matchPosition( { kind: 'position', value: 'instance:abc' }, 0, 5 ) ).toBe( false );
    } );
} );

describe( 'matchPattern', () => {
    it( 'matches odd / even / every-nth', () => {
        expect( matchPattern( { kind: 'pattern', value: 'odd' }, 0 ) ).toBe( true );
        expect( matchPattern( { kind: 'pattern', value: 'odd' }, 1 ) ).toBe( false );
        expect( matchPattern( { kind: 'pattern', value: 'even' }, 1 ) ).toBe( true );
        expect( matchPattern( { kind: 'pattern', value: 'every-nth:3' }, 2 ) ).toBe( true );
        expect( matchPattern( { kind: 'pattern', value: 'every-nth:3:start:2' }, 1 ) ).toBe( true );
        expect( matchPattern( { kind: 'pattern', value: 'every-nth:3:start:2' }, 4 ) ).toBe( true );
    } );
} );

describe( 'matchMeta', () => {
    it( 'matches sticky / featured / has-featured-image / author / taxonomy', () => {
        expect( matchMeta( { kind: 'meta', value: 'sticky' }, { sticky: true } ) ).toBe( true );
        expect( matchMeta( { kind: 'meta', value: 'sticky' }, { sticky: false } ) ).toBe( false );
        expect( matchMeta( { kind: 'meta', value: 'featured' }, { featured: true } ) ).toBe( true );
        expect( matchMeta( { kind: 'meta', value: 'has-featured-image' }, { hasFeaturedImage: true } ) ).toBe( true );
        expect( matchMeta( { kind: 'meta', value: 'author:42' }, { authorId: 42 } ) ).toBe( true );
        expect( matchMeta( { kind: 'meta', value: 'author:42' }, { authorId: 7 } ) ).toBe( false );
        expect(
            matchMeta(
                { kind: 'meta', value: 'taxonomy:category:news' },
                { taxonomies: { category: [ 'news', 'tech' ] } }
            )
        ).toBe( true );
        expect(
            matchMeta(
                { kind: 'meta', value: 'taxonomy:category:news' },
                { taxonomies: { category: [ 'tech' ] } }
            )
        ).toBe( false );
    } );
} );

describe( 'sortVariants precedence', () => {
    it( 'sorts instance > position > pattern > meta > custom', () => {
        const variants: VariantDescriptor[] = [
            { order: 0, priority: 10, matcher: { kind: 'meta', value: 'sticky' } },
            { order: 1, priority: 10, matcher: { kind: 'pattern', value: 'odd' } },
            { order: 2, priority: 10, matcher: { kind: 'custom', value: 'callback:x' } },
            { order: 3, priority: 10, matcher: { kind: 'position', value: 'first' } },
            { order: 4, priority: 10, matcher: { kind: 'position', value: 'instance:1' } },
        ];
        const sorted = sortVariants( variants ).map( ( v ) => v.matcher.kind + ':' + v.matcher.value );
        expect( sorted ).toEqual( [
            'position:instance:1',
            'position:first',
            'pattern:odd',
            'meta:sticky',
            'custom:callback:x',
        ] );
    } );

    it( 'breaks ties on priority then document order', () => {
        const variants: VariantDescriptor[] = [
            { order: 1, priority: 10, matcher: { kind: 'position', value: 'first' } },
            { order: 2, priority: 5, matcher: { kind: 'position', value: 'first' } },
            { order: 0, priority: 10, matcher: { kind: 'position', value: 'first' } },
        ];
        const sorted = sortVariants( variants ).map( ( v ) => v.order );
        expect( sorted ).toEqual( [ 2, 0, 1 ] );
    } );
} );

describe( 'compileStaticMap', () => {
    it( 'compiles position + pattern rules into an index → order map', () => {
        const variants: VariantDescriptor[] = [
            { order: 0, priority: 10, matcher: { kind: 'position', value: 'first' } },
            { order: 1, priority: 10, matcher: { kind: 'pattern', value: 'odd' } },
        ];
        const map = compileStaticMap( variants, 4 );
        // index 0 is "first" AND "odd" — position wins (higher tier).
        expect( map ).toEqual( { 0: 0, 2: 1 } );
    } );

    it( 'leaves meta / custom matchers out of the static map', () => {
        const variants: VariantDescriptor[] = [
            { order: 0, priority: 10, matcher: { kind: 'meta', value: 'sticky' } },
            { order: 1, priority: 10, matcher: { kind: 'custom', value: 'callback:x' } },
            { order: 2, priority: 10, matcher: { kind: 'position', value: 'last' } },
        ];
        const map = compileStaticMap( variants, 3 );
        expect( map ).toEqual( { 2: 2 } );
    } );

    it( 'compiles instance:<n1> as fixed-position match', () => {
        const variants: VariantDescriptor[] = [
            { order: 0, priority: 10, matcher: { kind: 'position', value: 'instance:2' } },
            { order: 1, priority: 10, matcher: { kind: 'position', value: 'first' } },
        ];
        const map = compileStaticMap( variants, 3 );
        expect( map ).toEqual( { 0: 1, 1: 0 } );
    } );
} );

describe( 'resolveVariant', () => {
    it( 'returns the static-map hit when present', () => {
        const map = { 0: 4 };
        expect( resolveVariant( 0, 3, {}, [], map ) ).toBe( 4 );
    } );

    it( 'walks meta matchers when the static map missed', () => {
        const variants: VariantDescriptor[] = [
            { order: 2, priority: 10, matcher: { kind: 'meta', value: 'sticky' } },
        ];
        expect( resolveVariant( 1, 3, { sticky: true }, variants, {} ) ).toBe( 2 );
        expect( resolveVariant( 1, 3, { sticky: false }, variants, {} ) ).toBe( null );
    } );

    it( 'never resolves custom matchers client-side', () => {
        const variants: VariantDescriptor[] = [
            { order: 0, priority: 10, matcher: { kind: 'custom', value: 'callback:x' } },
        ];
        expect( resolveVariant( 0, 1, {}, variants, {} ) ).toBe( null );
    } );
} );
