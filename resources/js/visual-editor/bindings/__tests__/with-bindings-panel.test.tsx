import { render } from '@testing-library/react'
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
	resolveBindings:     vi.fn( async () => ( {} ) ),
} ) )

import { __setBlockType, __clearBlockTypes } from '@wordpress/blocks'

import { resetBindingSourcesCache } from '../use-binding-sources'
import { withBindingsPanel } from '../with-bindings-panel'

interface CapturedProps {
	name: string
	attributes: Record<string, unknown>
	setAttributes: ( updates: Record<string, unknown> ) => void
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
} )
