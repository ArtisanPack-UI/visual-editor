import { act, fireEvent, render, waitFor } from '@testing-library/react'
import { useState } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const blockTypes = new Map<string, { attributes?: Record<string, unknown> }>()

vi.mock( '@wordpress/blocks', () => ( {
	getBlockType: ( name: string ) => blockTypes.get( name ),
	__setBlockType: ( name: string, attributes: Record<string, unknown> ) => {
		blockTypes.set( name, { attributes } )
	},
	__clearBlockTypes: () => {
		blockTypes.clear()
	},
} ) )

vi.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: ( { children }: { children: React.ReactNode } ) => (
		<div data-testid="inspector">{ children }</div>
	),
} ) )

vi.mock( '@wordpress/i18n', () => ( {
	__: ( text: string ) => text,
} ) )

vi.mock( '@wordpress/components', () => ( {
	PanelBody:     ( { children, title }: { children: React.ReactNode; title: string } ) => (
		<section data-testid="panel" aria-label={ title }>{ children }</section>
	),
	PanelRow:      ( { children }: { children: React.ReactNode } ) => <div>{ children }</div>,
	ToggleControl: ( { label, checked, onChange }: { label: string; checked: boolean; onChange: ( v: boolean ) => void } ) => (
		<button
			type="button"
			data-testid={ `toggle:${ label }` }
			data-checked={ checked }
			onClick={ () => onChange( ! checked ) }
		>{ label }</button>
	),
	SelectControl: ( { label, value, onChange }: { label: string; value: string; onChange: ( v: string ) => void } ) => (
		<input
			data-testid={ `select:${ label }` }
			value={ value }
			onChange={ ( e ) => onChange( ( e.target as HTMLInputElement ).value ) }
		/>
	),
	TextControl:   ( { label, value, onChange }: { label: string; value: string; onChange: ( v: string ) => void } ) => (
		<input
			data-testid={ `text:${ label }` }
			value={ value }
			onChange={ ( e ) => onChange( ( e.target as HTMLInputElement ).value ) }
		/>
	),
} ) )

vi.mock( '@wordpress/compose', () => ( {
	createHigherOrderComponent: ( fn: ( c: unknown ) => unknown ) => fn,
} ) )

vi.mock( '@wordpress/hooks', () => ( {
	addFilter: () => undefined,
} ) )

vi.mock( '../api', () => ( {
	fetchBindingSources: vi.fn( async () => [
		{ name: 'custom_field' },
		{ name: 'post_core' },
	] ),
	fetchBindingFields:  vi.fn( async () => [] ),
	resolveBindings:      vi.fn( async () => ( {} ) ),
	resolveBindingsBatch: vi.fn( async () => [] ),
} ) )

vi.mock( '../../editor/content-type-registry', () => ( {
	getContentTypes: () => [
		{ slug: 'page', plural: 'pages', label: 'Page' },
		{ slug: 'package', plural: 'packages', label: 'Package' },
	],
} ) )

import { __setBlockType, __clearBlockTypes } from '@wordpress/blocks'

import { fetchBindingFields, resolveBindingsBatch } from '../api'
import { BoundAttributesContext, useBoundAttributes } from '../bound-attributes-context'
import { clearResolvedBindingsCache } from '../resolve-batcher'
import { resetBindingSourcesCache } from '../use-binding-sources'
import { setBindingsResourceContext, withBindingsPanel } from '../with-bindings-panel'

interface CapturedProps {
	name: string
	attributes: Record<string, unknown>
	setAttributes: ( updates: Record<string, unknown> ) => void
	context?: Record<string, unknown>
}

let captured: CapturedProps | null = null

function StubEdit( props: CapturedProps ): JSX.Element {
	captured = props
	return <div data-testid="stub">stub</div>
}

const Wrapped = withBindingsPanel( StubEdit as never ) as React.ComponentType<CapturedProps>

beforeEach( () => {
	captured = null
	resetBindingSourcesCache()
	__clearBlockTypes()
} )

afterEach( () => {
	__clearBlockTypes()
} )

describe( 'withBindingsPanel', () => {
	it( 'returns the inner edit unchanged when the block has no scalar attributes', () => {
		__setBlockType( 'demo/empty', {} )

		const setAttributes = vi.fn()
		const { queryByTestId } = render(
			<Wrapped
				name="demo/empty"
				attributes={ {} }
				setAttributes={ setAttributes }
			/>,
		)

		expect( queryByTestId( 'panel' ) ).toBeNull()
		expect( queryByTestId( 'stub' ) ).not.toBeNull()
	} )

	it( 'renders the bindings panel when the block has scalar attributes', () => {
		__setBlockType( 'demo/box', {
			icon:  { type: 'string' },
			count: { type: 'number' },
		} )

		const { getByTestId } = render(
			<Wrapped
				name="demo/box"
				attributes={ { icon: 'static', count: 0 } }
				setAttributes={ vi.fn() }
			/>,
		)

		expect( getByTestId( 'panel' ) ).toBeTruthy()
		expect( getByTestId( 'toggle:icon (string)' ) ).toBeTruthy()
		expect( getByTestId( 'toggle:count (number)' ) ).toBeTruthy()
	} )

	it( 'exposes rich-text + source-typed attributes (Heading content) as bindable', () => {
		// Matches the actual shape in heading/block.json + paragraph/block.json
		__setBlockType( 'core/heading', {
			content: {
				type:     'rich-text',
				source:   'rich-text',
				selector: 'h1,h2,h3,h4,h5,h6',
				role:     'content',
			},
			level:   { type: 'number' },
		} )

		const { getByTestId } = render(
			<Wrapped
				name="core/heading"
				attributes={ {} }
				setAttributes={ vi.fn() }
			/>,
		)

		expect( getByTestId( 'toggle:content (rich-text)' ) ).toBeTruthy()
		expect( getByTestId( 'toggle:level (number)' ) ).toBeTruthy()
	} )

	it( 'never lists its own bindings sidecar as a bindable attribute', () => {
		__setBlockType( 'demo/box', {
			bindings: { type: 'object' },
			icon:     { type: 'string' },
		} )

		const { getByTestId, queryByTestId } = render(
			<Wrapped
				name="demo/box"
				attributes={ {} }
				setAttributes={ vi.fn() }
			/>,
		)

		expect( getByTestId( 'toggle:icon (string)' ) ).toBeTruthy()
		expect( queryByTestId( 'toggle:bindings (object)' ) ).toBeNull()
	} )

	it( 'exposes object-typed attributes (icon block iconRef) as bindable', () => {
		__setBlockType( 'artisanpack/icon', {
			iconRef:   { type: 'object', default: null },
			customSvg: { type: 'string', default: '' },
			size:      { type: 'number', default: 32 },
		} )

		const { getByTestId } = render(
			<Wrapped
				name="artisanpack/icon"
				attributes={ {} }
				setAttributes={ vi.fn() }
			/>,
		)

		expect( getByTestId( 'toggle:iconRef (object)' ) ).toBeTruthy()
		expect( getByTestId( 'toggle:customSvg (string)' ) ).toBeTruthy()
		expect( getByTestId( 'toggle:size (number)' ) ).toBeTruthy()
	} )

	describe( 'bound attribute lock (#995)', () => {
		function LockProbe(): JSX.Element {
			return <pre data-testid="bound">{ JSON.stringify( useBoundAttributes() ) }</pre>
		}

		const ProbeWrapped = withBindingsPanel( LockProbe as never ) as React.ComponentType<CapturedProps>

		function Harness( { initial }: { initial: Record<string, unknown> } ): JSX.Element {
			const [ attributes, setAttrs ] = useState( initial )

			return (
				<ProbeWrapped
					name="artisanpack/terminal"
					attributes={ attributes }
					setAttributes={ ( updates ) => setAttrs( ( prev ) => ( { ...prev, ...updates } ) ) }
				/>
			)
		}

		beforeEach( () => {
			__setBlockType( 'artisanpack/terminal', {
				lines: { type: 'string' },
				title: { type: 'string' },
			} )
		} )

		it( 'reports bound attributes (source + key) to the inner edit', () => {
			const { getByTestId } = render(
				<Harness
					initial={ {
						lines:    'static',
						bindings: { lines: { source: 'package', args: { key: 'terminal' } } },
					} }
				/>,
			)

			expect( JSON.parse( getByTestId( 'bound' ).textContent ?? '' ) ).toEqual( {
				lines: { source: 'package', key: 'terminal' },
			} )
		} )

		it( 'falls back to the free-form path when the binding has no key', () => {
			const { getByTestId } = render(
				<Harness
					initial={ { bindings: { title: { source: 'post_core', args: { path: 'author.name' } } } } }
				/>,
			)

			expect( JSON.parse( getByTestId( 'bound' ).textContent ?? '' ) ).toEqual( {
				title: { source: 'post_core', key: 'author.name' },
			} )
		} )

		it( 'does not leak a parent block\'s bound map into a nested block with no bindable attributes', () => {
			__setBlockType( 'demo/empty', {} )

			const { getByTestId } = render(
				<BoundAttributesContext.Provider value={ { lines: { source: 'package', key: 'terminal' } } }>
					<ProbeWrapped name="demo/empty" attributes={ {} } setAttributes={ vi.fn() } />
				</BoundAttributesContext.Provider>,
			)

			expect( JSON.parse( getByTestId( 'bound' ).textContent ?? '' ) ).toEqual( {} )
		} )

		it( 'does not lock a binding with no source picked yet', () => {
			const { getByTestId } = render(
				<Harness initial={ { bindings: { lines: { source: '', args: {} } } } } />,
			)

			expect( JSON.parse( getByTestId( 'bound' ).textContent ?? '' ) ).toEqual( {} )
		} )

		it( 'unlocks the attribute once it is disconnected in the Bindings panel', () => {
			const { getByTestId } = render(
				<Harness
					initial={ {
						lines:    'static',
						bindings: { lines: { source: 'package', args: { key: 'terminal' } } },
					} }
				/>,
			)

			expect( JSON.parse( getByTestId( 'bound' ).textContent ?? '' ) ).toHaveProperty( 'lines' )

			act( () => {
				fireEvent.click( getByTestId( 'toggle:lines (string)' ) )
			} )

			expect( JSON.parse( getByTestId( 'bound' ).textContent ?? '' ) ).toEqual( {} )
		} )
	} )

	describe( 'binding target (#1041)', () => {
		const PREVIEW_KEY = 'artisanpack/postPreview'
		const SAVED       = { title: 'v1.2.0', bindings: { title: { source: 'custom_field', args: { key: 'version' } } } }

		interface BatchItem {
			key: string
			context: { resource: string | null; id: number | string | null }
		}

		const batchMock = vi.mocked( resolveBindingsBatch )

		function sentContexts(): BatchItem[ 'context' ][] {
			return batchMock.mock.calls.flatMap( ( [ items ] ) => items.map( ( item ) => item.context ) )
		}

		beforeEach( () => {
			clearResolvedBindingsCache()
			batchMock.mockReset()
			vi.mocked( fetchBindingFields ).mockClear()
			batchMock.mockImplementation( async ( items ) => items.map( ( item ) => ( {
				key: item.key,
				values: { title: `${ item.context.resource }:${ item.context.id }` },
			} ) ) )
			__setBlockType( 'demo/text', { title: { type: 'string' } } )
			setBindingsResourceContext( 'pages', 7 )
		} )

		afterEach( () => {
			setBindingsResourceContext( null, null )
		} )

		function renderBlock( context?: Record<string, unknown> ) {
			const setAttributes = vi.fn()
			const element       = ( next?: Record<string, unknown> ): JSX.Element => (
				<Wrapped
					name="demo/text"
					attributes={ SAVED }
					setAttributes={ setAttributes }
					context={ next }
				/>
			)
			const view = render( element( context ) )

			return { setAttributes, update: ( next: Record<string, unknown> ) => view.rerender( element( next ) ) }
		}

		it( 'resolves a top-level block against the page being edited', async () => {
			renderBlock( { postType: 'page', postId: 7 } )

			await waitFor( () => expect( captured?.attributes.title ).toBe( 'pages:7' ) )
			expect( sentContexts() ).toEqual( [ { resource: 'pages', id: 7 } ] )
		} )

		it( 'resolves a block in Single Content against the picked record', async () => {
			renderBlock( { postType: 'package', postId: 12, [ PREVIEW_KEY ]: null } )

			await waitFor( () => expect( captured?.attributes.title ).toBe( 'packages:12' ) )
			expect( sentContexts() ).toEqual( [ { resource: 'packages', id: 12 } ] )
		} )

		it( 'updates the bound value when the picked record changes', async () => {
			const { update } = renderBlock( { postType: 'package', postId: 12, [ PREVIEW_KEY ]: { id: 12 } } )

			await waitFor( () => expect( captured?.attributes.title ).toBe( 'packages:12' ) )

			update( { postType: 'package', postId: 13, [ PREVIEW_KEY ]: { id: 13 } } )

			await waitFor( () => expect( captured?.attributes.title ).toBe( 'packages:13' ) )
		} )

		it( 'resolves each Query Loop row against its own record', async () => {
			const rows = [ 1, 2, 3 ].map( ( postId ) => (
				<Wrapped
					key={ postId }
					name="demo/text"
					attributes={ SAVED }
					setAttributes={ vi.fn() }
					context={ { postType: 'package', postId, [ PREVIEW_KEY ]: { id: postId } } }
				/>
			) )

			render( <>{ rows }</> )

			await waitFor( () => expect( batchMock ).toHaveBeenCalled() )
			expect( sentContexts() ).toEqual( [
				{ resource: 'packages', id: 1 },
				{ resource: 'packages', id: 2 },
				{ resource: 'packages', id: 3 },
			] )
		} )

		it( 'never writes the resolved value back to the saved attributes', async () => {
			const { setAttributes } = renderBlock( { postType: 'package', postId: 12, [ PREVIEW_KEY ]: null } )

			await waitFor( () => expect( captured?.attributes.title ).toBe( 'packages:12' ) )

			expect( setAttributes ).not.toHaveBeenCalled()
			expect( SAVED.title ).toBe( 'v1.2.0' )
		} )

		it( 'lists the picked record\'s fields in the Bindings panel', async () => {
			renderBlock( { postType: 'package', postId: 12, [ PREVIEW_KEY ]: null } )

			await waitFor( () => expect( fetchBindingFields ).toHaveBeenCalled() )

			expect( vi.mocked( fetchBindingFields ).mock.calls.at( -1 )?.[ 1 ] ).toBe( 'packages' )
		} )
	} )
} )
