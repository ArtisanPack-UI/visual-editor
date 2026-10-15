/**
 * State-CSS save deprecations (M-5, F-9) — parse → validate → re-save
 * with the real `@wordpress/blocks` parser and serializer.
 *
 * The 1.14 fixtures below are the exact `<style data-ap-state-scope>`
 * strings the 1.14.1 emitter produced for these states (raw
 * `var:preset|…` values, no reduced-motion guard).
 *
 * @since 1.15.0
 */

import { beforeAll, describe, expect, it, vi } from 'vitest'

vi.mock( '@wordpress/blocks', async () =>
	( await import( '../../blocks/navigation/__tests__/wp-blocks-cjs' ) ).requireCjs( '@wordpress/blocks' ),
)
vi.mock( '@wordpress/hooks', async () =>
	( await import( '../../blocks/navigation/__tests__/wp-blocks-cjs' ) ).requireCjs( '@wordpress/hooks' ),
)
vi.mock( '@wordpress/block-editor', async () =>
	( await import( '../../blocks/navigation/__tests__/wp-blocks-cjs' ) ).requireCjs( '@wordpress/block-editor' ),
)

import { getBlockType, parse, registerBlockType, serialize } from '@wordpress/blocks'

import buttonMetadata from '../../blocks/button/block.json'
import buttonDeprecated from '../../blocks/button/deprecated'
import buttonSave from '../../blocks/button/save'
import groupMetadata from '../../blocks/group/block.json'
import groupDeprecated from '../../blocks/group/deprecated'
import groupSave from '../../blocks/group/save'
import { emitStateCss } from '../css-emitter'
import { registerStateAttribute } from '../register-attribute'
import { DEFAULT_STATES, StateRegistry } from '../registry'
import {
	LEGACY_STATE_CSS_SHAPES,
	STATE_CSS_SHAPE_SUPPORT,
	addStateCssDeprecations,
} from '../state-css-deprecations'
import { registerStateStylesFilters } from '../with-state-styles'

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyBlock = any

const GROUP_STATES = {
	_scopeId:                     'g1',
	'style.color.background':     { idle: 'var:preset|color|base', hover: 'var:preset|color|primary' },
	'style.dimensions.transform': { hover: 'translateY(-4px)' },
}

const BUTTON_STATES = {
	_scopeId:             'b1',
	backgroundColor:      { hover: 'var:preset|color|accentDark' },
	'style.border.color': { focus: 'var:preset|color|contrast' },
}

const GROUP_CSS_1_14 = '.ap-state-g1 { background-color: var:preset|color|base !important; transition: all 150ms ease; } '
	+ '@media (hover: hover) { .ap-state-g1:hover, .ap-state-g1:hover :is(a, button) { background-color: var:preset|color|primary !important; transform: translateY(-4px) !important; } }'

const BUTTON_CSS_1_14 = '.ap-state-b1 { transition: all 150ms ease; } '
	+ '.ap-state-b1:focus, .ap-state-b1:focus :is(a, button) { border-color: var:preset|color|contrast !important; } '
	+ '@media (hover: hover) { .ap-state-b1:hover, .ap-state-b1:hover :is(a, button) { background-color: var:preset|color|accentDark !important; } }'

const GROUP_CSS_DEV = '.ap-state-g1 { background-color: var(--wp--preset--color--base) !important; transition: all 150ms ease; } '
	+ '@media (hover: hover) { .ap-state-g1:hover, .ap-state-g1:hover :is(a, button) { background-color: var(--wp--preset--color--primary) !important; transform: translateY(-4px) !important; } }'

const GROUP_CSS_CURRENT = GROUP_CSS_DEV + ' '
	+ '@media (prefers-reduced-motion: reduce) { .ap-state-g1 { transition: none !important; } '
	+ '.ap-state-g1:hover, .ap-state-g1:hover :is(a, button) { transform: none !important; } }'

const BUTTON_CSS_CURRENT = '.ap-state-b1 { transition: all 150ms ease; } '
	+ '.ap-state-b1:focus, .ap-state-b1:focus :is(a, button) { border-color: var(--wp--preset--color--contrast) !important; } '
	+ '@media (hover: hover) { .ap-state-b1:hover, .ap-state-b1:hover :is(a, button) { background-color: var(--wp--preset--color--accent-dark) !important; } } '
	+ '@media (prefers-reduced-motion: reduce) { .ap-state-b1 { transition: none !important; } }'

function groupMarkup( css: string ): string {
	return `<!-- wp:artisanpack/group {"states":${ JSON.stringify( GROUP_STATES ) }} -->\n`
		+ `<div class="wp-block-artisanpack-group wp-block-group ap-state-g1"></div><style data-ap-state-scope="g1">${ css }</style>\n`
		+ '<!-- /wp:artisanpack/group -->'
}

function buttonMarkup( css: string ): string {
	return `<!-- wp:artisanpack/button {"states":${ JSON.stringify( BUTTON_STATES ) }} -->\n`
		+ '<div class="wp-block-artisanpack-button wp-block-button ap-state-b1"><a class="wp-block-button__link wp-element-button">Go</a></div>'
		+ `<style data-ap-state-scope="b1">${ css }</style>\n`
		+ '<!-- /wp:artisanpack/button -->'
}

function withoutScope( states: Record<string, unknown> ): Record<string, unknown> {
	const { _scopeId, ...paths } = states

	return paths
}

beforeAll( () => {
	registerStateAttribute()
	registerStateStylesFilters()

	registerBlockType( groupMetadata as never, { edit: () => null, save: groupSave, deprecated: groupDeprecated } as never )
	registerBlockType( buttonMetadata as never, { edit: () => null, save: buttonSave, deprecated: buttonDeprecated } as never )
} )

describe( 'emitStateCss legacy shapes', () => {
	const registry = new StateRegistry( DEFAULT_STATES )

	it( 'reproduces the stored 1.14 output with expandPresets and the guard off', () => {
		const legacy = { expandPresets: false, reducedMotionGuard: false }

		expect( emitStateCss( '.ap-state-g1', withoutScope( GROUP_STATES ) as never, registry, legacy ) ).toBe( GROUP_CSS_1_14 )
		expect( emitStateCss( '.ap-state-b1', withoutScope( BUTTON_STATES ) as never, registry, legacy ) ).toBe( BUTTON_CSS_1_14 )
	} )

	it( 'emits the current shape by default', () => {
		expect( emitStateCss( '.ap-state-g1', withoutScope( GROUP_STATES ) as never, registry ) ).toBe( GROUP_CSS_CURRENT )
		expect( emitStateCss( '.ap-state-b1', withoutScope( BUTTON_STATES ) as never, registry ) ).toBe( BUTTON_CSS_CURRENT )
	} )
} )

describe( 'state-CSS save deprecations', () => {
	it.each( [
		[ 'group', () => groupMarkup( GROUP_CSS_1_14 ), () => groupMarkup( GROUP_CSS_CURRENT ), GROUP_STATES ],
		[ 'button', () => buttonMarkup( BUTTON_CSS_1_14 ), () => buttonMarkup( BUTTON_CSS_CURRENT ), BUTTON_STATES ],
	] )( 'validates 1.14 %s markup and re-saves it in the current shape', ( _name, legacy, current, states ) => {
		const [ block ] = parse( legacy() ) as AnyBlock[]

		expect( block.isValid ).toBe( true )
		expect( block.validationIssues ).toEqual( [] )
		expect( block.attributes.states ).toEqual( states )
		expect( serialize( [ block ] ) ).toBe( current() )
	} )

	it( 'validates group markup saved by a 1.15 development build (expanded presets, no guard)', () => {
		const [ block ] = parse( groupMarkup( GROUP_CSS_DEV ) ) as AnyBlock[]

		expect( block.isValid ).toBe( true )
		expect( serialize( [ block ] ) ).toBe( groupMarkup( GROUP_CSS_CURRENT ) )
	} )

	it( 'keeps current markup valid', () => {
		const [ group ]  = parse( groupMarkup( GROUP_CSS_CURRENT ) ) as AnyBlock[]
		const [ button ] = parse( buttonMarkup( BUTTON_CSS_CURRENT ) ) as AnyBlock[]

		expect( group.isValid ).toBe( true )
		expect( button.isValid ).toBe( true )
	} )

	it( 'still flags state CSS that matches no known shape', () => {
		const [ block ] = parse( groupMarkup( '.ap-state-g1 { color: red; }' ) ) as AnyBlock[]

		expect( block.isValid ).toBe( false )
	} )

	it( 'prepends one deprecation per legacy shape ahead of the block\'s own', () => {
		const deprecated = ( getBlockType( 'artisanpack/group' ) as AnyBlock ).deprecated

		expect( deprecated.slice( 0, 2 ).map( ( entry: AnyBlock ) => entry.supports[ STATE_CSS_SHAPE_SUPPORT ] ) )
			.toEqual( LEGACY_STATE_CSS_SHAPES )
		expect( deprecated ).toHaveLength( LEGACY_STATE_CSS_SHAPES.length + groupDeprecated.length )
		expect( deprecated[ 0 ].attributes.states ).toBeDefined()
	} )
} )

describe( 'addStateCssDeprecations', () => {
	const save = (): null => null

	it( 'leaves blocks without artisanpackStates alone', () => {
		const settings = { supports: { color: true }, save, deprecated: [ { save } ] }

		expect( addStateCssDeprecations( settings ) ).toBe( settings )
	} )

	it( 'leaves deprecation entries alone', () => {
		const settings = { supports: { artisanpackStates: true }, save }

		expect( addStateCssDeprecations( settings, 'x/y', { save } ) ).toBe( settings )
	} )

	it( 'prepends to an existing deprecated array and keeps its entries in order', () => {
		const first  = { save, supports: {} }
		const second = { save, supports: {} }
		const result = addStateCssDeprecations( {
			apiVersion: 3,
			attributes: { a: { type: 'string' } },
			supports:   { artisanpackStates: true },
			save,
			deprecated: [ first, second ],
		} )

		expect( result.deprecated ).toHaveLength( 4 )
		expect( result.deprecated?.[ 0 ] ).toMatchObject( {
			apiVersion: 3,
			attributes: { a: { type: 'string' } },
			save,
			supports:   { artisanpackStates: true, [ STATE_CSS_SHAPE_SUPPORT ]: LEGACY_STATE_CSS_SHAPES[ 0 ] },
		} )
		expect( result.deprecated?.slice( 2 ) ).toEqual( [ first, second ] )
	} )

	it( 'adds legacy variants of older saves that support states', () => {
		const older  = { save, supports: { artisanpackStates: true }, migrate: ( a: unknown ) => a }
		const result = addStateCssDeprecations( { supports: { artisanpackStates: true }, save, deprecated: [ older ] } )

		expect( result.deprecated ).toHaveLength( 5 )
		expect( result.deprecated?.[ 2 ] ).toMatchObject( {
			save,
			migrate:  older.migrate,
			supports: { artisanpackStates: true, [ STATE_CSS_SHAPE_SUPPORT ]: LEGACY_STATE_CSS_SHAPES[ 0 ] },
		} )
		expect( result.deprecated?.[ 4 ] ).toBe( older )
	} )

	it( 'is idempotent', () => {
		const once = addStateCssDeprecations( { supports: { artisanpackStates: true }, save } )

		expect( addStateCssDeprecations( once ) ).toBe( once )
	} )
} )
