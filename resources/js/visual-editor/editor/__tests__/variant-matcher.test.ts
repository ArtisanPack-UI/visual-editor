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
    it( 'returns an empty map when total is 0', () => {
        const variants: VariantDescriptor[] = [
            { order: 0, priority: 10, matcher: { kind: 'position', value: 'last' } },
            { order: 1, priority: 10, matcher: { kind: 'pattern', value: 'odd' } },
            { order: 2, priority: 10, matcher: { kind: 'position', value: 'instance:1' } },
        ];
        expect( compileStaticMap( variants, 0 ) ).toEqual( {} );
    } );

    it( 'only compiles instance:<n1> matchers — position / pattern resolve at render time', () => {
        const variants: VariantDescriptor[] = [
            { order: 0, priority: 10, matcher: { kind: 'position', value: 'first' } },
            { order: 1, priority: 10, matcher: { kind: 'pattern', value: 'odd' } },
            { order: 2, priority: 10, matcher: { kind: 'position', value: 'last' } },
            { order: 3, priority: 10, matcher: { kind: 'position', value: 'instance:2' } },
        ];
        const map = compileStaticMap( variants, 4 );
        expect( map ).toEqual( { 1: 3 } );
    } );

    it( 'leaves meta / custom matchers out of the static map', () => {
        const variants: VariantDescriptor[] = [
            { order: 0, priority: 10, matcher: { kind: 'meta', value: 'sticky' } },
            { order: 1, priority: 10, matcher: { kind: 'custom', value: 'callback:x' } },
        ];
        expect( compileStaticMap( variants, 3 ) ).toEqual( {} );
    } );
} );

describe( 'resolveVariant', () => {
    it( 'returns the static-map hit when present', () => {
        const map = { 0: 1 };
        // Five variants present, map points at order 1 — in range, returns 1.
        const variants: VariantDescriptor[] = [
            { order: 0, priority: 10, matcher: { kind: 'position', value: 'first' } },
            { order: 1, priority: 10, matcher: { kind: 'position', value: 'last' } },
            { order: 2, priority: 10, matcher: { kind: 'pattern', value: 'odd' } },
            { order: 3, priority: 10, matcher: { kind: 'pattern', value: 'even' } },
            { order: 4, priority: 10, matcher: { kind: 'meta', value: 'sticky' } },
        ];
        expect( resolveVariant( 0, 3, {}, variants, map ) ).toBe( 1 );
    } );

    it( 'returns null when the static-map points at a deleted variant (stale map)', () => {
        // Map was stamped when 3 variants existed; one was deleted.
        // Out-of-range entries must yield null instead of falling through
        // to matcher evaluation, to keep parity with the PHP resolver.
        const map = { 0: 2 };
        const variants: VariantDescriptor[] = [
            { order: 0, priority: 10, matcher: { kind: 'position', value: 'first' } },
        ];
        expect( resolveVariant( 0, 3, {}, variants, map ) ).toBe( null );
    } );

    it( 'walks position matchers against the live total when the static map missed', () => {
        const variants: VariantDescriptor[] = [
            { order: 0, priority: 10, matcher: { kind: 'position', value: 'last' } },
        ];
        // total=3 → "last" matches index 2
        expect( resolveVariant( 2, 3, {}, variants, {} ) ).toBe( 0 );
        // total=5 → "last" matches index 4, not 2
        expect( resolveVariant( 2, 5, {}, variants, {} ) ).toBe( null );
    } );

    it( 'walks pattern matchers when the static map missed', () => {
        const variants: VariantDescriptor[] = [
            { order: 1, priority: 10, matcher: { kind: 'pattern', value: 'odd' } },
        ];
        expect( resolveVariant( 0, 5, {}, variants, {} ) ).toBe( 1 );
        expect( resolveVariant( 1, 5, {}, variants, {} ) ).toBe( null );
    } );

    it( 'walks meta matchers when the static map missed', () => {
        const variants: VariantDescriptor[] = [
            { order: 2, priority: 10, matcher: { kind: 'meta', value: 'sticky' } },
        ];
        expect( resolveVariant( 1, 3, { sticky: true }, variants, {} ) ).toBe( 2 );
        expect( resolveVariant( 1, 3, { sticky: false }, variants, {} ) ).toBe( null );
    } );

    it( 'honors precedence when multiple matchers would hit at render time', () => {
        const variants: VariantDescriptor[] = [
            { order: 0, priority: 10, matcher: { kind: 'meta', value: 'sticky' } },
            { order: 1, priority: 10, matcher: { kind: 'position', value: 'first' } },
        ];
        // First post is sticky AND in position 1 — position wins (higher tier).
        expect( resolveVariant( 0, 3, { sticky: true }, variants, {} ) ).toBe( 1 );
    } );

    it( 'never resolves custom matchers client-side', () => {
        const variants: VariantDescriptor[] = [
            { order: 0, priority: 10, matcher: { kind: 'custom', value: 'callback:x' } },
        ];
        expect( resolveVariant( 0, 1, {}, variants, {} ) ).toBe( null );
    } );
} );
