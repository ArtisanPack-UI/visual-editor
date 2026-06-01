import { describe, expect, it } from 'vitest'

import {
	deepMerge,
	diffPaths,
	pathMatchesAnyRoot,
	readPath,
	setPath,
} from '../attribute-paths'

describe( 'readPath', () => {
	it( 'reads a top-level value', () => {
		expect( readPath( { foo: 1 }, 'foo' ) ).toBe( 1 )
	} )

	it( 'reads a nested value through dotted segments', () => {
		expect( readPath( { a: { b: { c: 'leaf' } } }, 'a.b.c' ) ).toBe( 'leaf' )
	} )

	it( 'returns undefined for a missing segment', () => {
		expect( readPath( { a: { b: 1 } }, 'a.b.c' ) ).toBeUndefined()
	} )

	it( 'returns undefined for a non-object source', () => {
		expect( readPath( null, 'a' ) ).toBeUndefined()
		expect( readPath( 4, 'a' ) ).toBeUndefined()
	} )

	it( 'does not descend into arrays', () => {
		expect( readPath( { arr: [ 1, 2 ] }, 'arr.0' ) ).toBeUndefined()
	} )
} )

describe( 'setPath', () => {
	it( 'sets a top-level value without mutating the input', () => {
		const source = { foo: 1 }
		const next   = setPath( source, 'foo', 2 )

		expect( next ).toEqual( { foo: 2 } )
		expect( source ).toEqual( { foo: 1 } )
	} )

	it( 'creates intermediate objects when missing', () => {
		expect( setPath( {}, 'a.b.c', 'leaf' ) ).toEqual( {
			a: { b: { c: 'leaf' } },
		} )
	} )

	it( 'merges into existing objects rather than replacing them', () => {
		const source = { a: { b: { keep: true } } }

		expect( setPath( source, 'a.b.c', 'leaf' ) ).toEqual( {
			a: { b: { keep: true, c: 'leaf' } },
		} )
	} )

	it( 'replaces a scalar with an object when the path traverses it', () => {
		expect( setPath( { a: 'string' }, 'a.b', 'leaf' ) ).toEqual( {
			a: { b: 'leaf' },
		} )
	} )

	it( 'deletes a leaf when value is undefined and prunes empty parents', () => {
		expect( setPath( { a: { b: { c: 'leaf' } } }, 'a.b.c', undefined ) ).toEqual( {} )
	} )

	it( 'preserves sibling values when deleting one leaf', () => {
		expect( setPath( { a: { b: 1, c: 2 } }, 'a.b', undefined ) ).toEqual( {
			a: { c: 2 },
		} )
	} )
} )

describe( 'diffPaths', () => {
	it( 'returns an empty list when nothing changed', () => {
		expect( diffPaths( { a: 1 }, { a: 1 } ) ).toEqual( [] )
	} )

	it( 'flags a single top-level change', () => {
		expect( diffPaths( { a: 2 }, { a: 1 } ) ).toEqual( [
			{ path: 'a', value: 2 },
		] )
	} )

	it( 'recurses into nested objects', () => {
		expect( diffPaths( { a: { b: 5 } }, { a: { b: 4 } } ) ).toEqual( [
			{ path: 'a.b', value: 5 },
		] )
	} )

	it( 'treats arrays as leaves and replaces them wholesale', () => {
		const result = diffPaths( { items: [ 1, 2 ] }, { items: [ 1 ] } )
		expect( result ).toEqual( [ { path: 'items', value: [ 1, 2 ] } ] )
	} )

	it( 'detects a leaf change inside a deep object', () => {
		const updates = { style: { spacing: { padding: '2rem', margin: '1rem' } } }
		const prev    = { style: { spacing: { padding: '1rem', margin: '1rem' } } }

		expect( diffPaths( updates, prev ) ).toEqual( [
			{ path: 'style.spacing.padding', value: '2rem' },
		] )
	} )
} )

describe( 'pathMatchesAnyRoot', () => {
	it( 'matches exact equality', () => {
		expect( pathMatchesAnyRoot( 'spacing', [ 'spacing' ] ) ).toBe( true )
	} )

	it( 'matches a prefix on a segment boundary', () => {
		expect( pathMatchesAnyRoot( 'spacing.padding', [ 'spacing' ] ) ).toBe( true )
	} )

	it( 'matches a root that appears as a middle segment', () => {
		expect( pathMatchesAnyRoot( 'style.spacing.padding', [ 'spacing' ] ) ).toBe( true )
	} )

	it( 'matches a root that appears as the last segment', () => {
		expect( pathMatchesAnyRoot( 'style.spacing', [ 'spacing' ] ) ).toBe( true )
	} )

	it( 'does not match a partial-word prefix', () => {
		expect( pathMatchesAnyRoot( 'spacingScale', [ 'spacing' ] ) ).toBe( false )
		expect( pathMatchesAnyRoot( 'lineSpacing', [ 'spacing' ] ) ).toBe( false )
	} )

	it( 'matches any root in the list', () => {
		expect( pathMatchesAnyRoot( 'columnCount', [ 'spacing', 'columnCount' ] ) ).toBe( true )
	} )
} )

describe( 'deepMerge', () => {
	it( 'returns base when overlay is null/undefined', () => {
		expect( deepMerge( { a: 1 }, null ) ).toEqual( { a: 1 } )
		expect( deepMerge( { a: 1 }, undefined ) ).toEqual( { a: 1 } )
	} )

	it( 'overrides scalars with overlay values', () => {
		expect( deepMerge( { a: 1, b: 2 }, { a: 10 } ) ).toEqual( {
			a: 10,
			b: 2,
		} )
	} )

	it( 'merges nested objects recursively', () => {
		expect(
			deepMerge(
				{ style: { spacing: { padding: '1rem', margin: '0.5rem' } } },
				{ style: { spacing: { padding: '2rem' } } },
			),
		).toEqual( {
			style: { spacing: { padding: '2rem', margin: '0.5rem' } },
		} )
	} )

	it( 'replaces arrays wholesale instead of concatenating', () => {
		expect( deepMerge( { tags: [ 'a' ] }, { tags: [ 'b', 'c' ] } ) ).toEqual( {
			tags: [ 'b', 'c' ],
		} )
	} )

	it( 'never mutates the base', () => {
		const base = { a: { b: 1 } }
		deepMerge( base, { a: { c: 2 } } )

		expect( base ).toEqual( { a: { b: 1 } } )
	} )
} )
