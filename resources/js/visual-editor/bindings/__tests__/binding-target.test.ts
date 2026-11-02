import { describe, expect, it, vi } from 'vitest'

vi.mock( '@wordpress/block-editor', () => ( {} ) )

vi.mock( '../../editor/content-type-registry', () => ( {
	getContentTypes: () => [
		{ slug: 'post', plural: 'posts', label: 'Post' },
		{ slug: 'page', plural: 'pages', label: 'Page' },
		{ slug: 'package', plural: 'packages', label: 'Package' },
		{ slug: 'person', plural: 'people', label: 'Person' },
	],
} ) )

import { PREVIEW_CONTEXT_KEY } from '../../blocks/_shared/entity-placeholder-edit'
import { resolveBindingTarget } from '../binding-target'
import { BINDING_CONTEXT_KEYS } from '../register-attribute'

const PREVIEW_KEY = 'artisanpack/postPreview'
const PAGE_TARGET = { resource: 'pages', id: 7 }

describe( 'resolveBindingTarget', () => {
	it( 'declares the preview key the target reads', () => {
		expect( BINDING_CONTEXT_KEYS ).toContain( PREVIEW_CONTEXT_KEY )
	} )

	it( 'uses the page when a top-level block only sees the page in context', () => {
		expect(
			resolveBindingTarget( { postType: 'page', postId: 7 }, PAGE_TARGET ),
		).toBe( PAGE_TARGET )
	} )

	it( 'uses the page when the block has no context at all', () => {
		expect( resolveBindingTarget( undefined, PAGE_TARGET ) ).toBe( PAGE_TARGET )
		expect( resolveBindingTarget( {}, PAGE_TARGET ) ).toBe( PAGE_TARGET )
	} )

	it( 'uses the picked record once its preview has loaded', () => {
		expect(
			resolveBindingTarget(
				{ postType: 'package', postId: 12, [ PREVIEW_KEY ]: { id: 12 } },
				PAGE_TARGET,
			),
		).toEqual( { resource: 'packages', id: 12 } )
	} )

	it( 'uses the picked record while its preview is still loading', () => {
		expect(
			resolveBindingTarget(
				{ postType: 'package', postId: 12, [ PREVIEW_KEY ]: null },
				PAGE_TARGET,
			),
		).toEqual( { resource: 'packages', id: 12 } )
	} )

	it( 'normalises the post type to the registered resource slug', () => {
		const targetFor = ( postType: string ) => resolveBindingTarget(
			{ postType, postId: 3, [ PREVIEW_KEY ]: null },
			PAGE_TARGET,
		).resource

		expect( targetFor( 'post' ) ).toBe( 'posts' )
		expect( targetFor( 'posts' ) ).toBe( 'posts' )
		expect( targetFor( 'Package' ) ).toBe( 'packages' )
		expect( targetFor( 'person' ) ).toBe( 'people' )
		expect( targetFor( 'people' ) ).toBe( 'people' )
	} )

	it( 'accepts a numeric string id', () => {
		expect(
			resolveBindingTarget(
				{ postType: 'post', postId: '42', [ PREVIEW_KEY ]: null },
				PAGE_TARGET,
			),
		).toEqual( { resource: 'posts', id: 42 } )
	} )

	it( 'uses the page for a Single Content block with no entry picked', () => {
		expect(
			resolveBindingTarget( { postType: 'post', postId: 0 }, PAGE_TARGET ),
		).toBe( PAGE_TARGET )
	} )

	it( 'uses the page when a preview has no usable post identity', () => {
		// The site editor's template preview provides a record with no
		// `postId` / `postType` beside it.
		expect(
			resolveBindingTarget( { [ PREVIEW_KEY ]: { id: 9 } }, PAGE_TARGET ),
		).toBe( PAGE_TARGET )
	} )
} )
