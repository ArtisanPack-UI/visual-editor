/**
 * Hover → Glow color control (M-9, R-24, R-25).
 *
 * @since 1.15.0
 */

import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const settings: Record<string, unknown> = {};

vi.mock( '@wordpress/block-editor', () => ( {
	useSettings: ( ...paths: string[] ) => paths.map( ( path ) => settings[ path ] ),
} ) );

import { AnimationPanel, glowPalette } from '../AnimationPanel';
import { AnimationRegistry } from '../registry';

const THEME   = [ { slug: 'brand', name: 'Brand', color: '#112233' } ];
const DEFAULT = [ { slug: 'vivid-red', name: 'Vivid red', color: '#cf2e2e' } ];
const CUSTOM  = [ { slug: 'mine', name: 'Mine', color: '#abcdef' } ];

function renderGlowPanel(): ReturnType<typeof vi.fn> {
	const onChange = vi.fn();

	render(
		<AnimationPanel
			registry={ new AnimationRegistry() }
			value={ { hover: { name: 'glow' } } }
			onChange={ onChange }
		/>,
	);

	const toggles = screen.getAllByRole( 'button', { name: 'Hover animation' } );

	fireEvent.click( toggles[ toggles.length - 1 ] );

	return onChange;
}

beforeEach( () => {
	settings[ 'color.palette.theme' ]   = THEME;
	settings[ 'color.palette.default' ] = DEFAULT;
	settings[ 'color.palette.custom' ]  = CUSTOM;
	settings[ 'color.defaultPalette' ]  = true;
} );

describe( 'GlowColorControl accessibility (M-9)', () => {
	it( 'names the swatch listbox "Glow color"', () => {
		renderGlowPanel();

		expect( screen.getByRole( 'listbox', { name: 'Glow color' } ) ).toBeTruthy();
	} );

	it( 'labels and describes the picker group, with no dangling label', () => {
		renderGlowPanel();

		const group = screen.getByRole( 'group', { name: 'Glow color' } );
		const help  = document.getElementById( group.getAttribute( 'aria-describedby' ) ?? '' );

		expect( help?.textContent ).toBe( 'Leave unset to use the theme’s glow color.' );
		expect( document.querySelector( 'label[for^="ap-animation-glow-color"]' ) ).toBeNull();
	} );

	it( 'gives each mounted control its own ids', () => {
		renderGlowPanel();
		renderGlowPanel();

		const groups = screen.getAllByRole( 'group', { name: 'Glow color' } );

		expect( groups ).toHaveLength( 2 );
		expect( groups[ 0 ].id ).not.toBe( groups[ 1 ].id );
	} );
} );

describe( 'Glow palette (R-25)', () => {
	it( 'offers the theme, default and custom palettes', () => {
		renderGlowPanel();

		const listbox = screen.getByRole( 'listbox', { name: 'Glow color' } );

		expect( listbox.querySelectorAll( '[role="option"]' ) ).toHaveLength( 3 );
	} );

	it( 'drops the default palette when color.defaultPalette is false', () => {
		settings[ 'color.defaultPalette' ] = false;
		renderGlowPanel();

		const options = screen.getByRole( 'listbox', { name: 'Glow color' } ).querySelectorAll( '[role="option"]' );

		expect( options ).toHaveLength( 2 );
	} );

	it( 'stores a custom-palette pick as a preset reference', () => {
		const onChange = renderGlowPanel();

		fireEvent.click( screen.getByRole( 'option', { name: 'Mine' } ) );

		expect( onChange ).toHaveBeenCalledWith( { hover: { name: 'glow', color: 'var:preset|color|mine' } } );
	} );

	it( 'orders theme, default, custom', () => {
		expect( glowPalette( THEME, DEFAULT, CUSTOM, undefined ).map( ( c ) => c.slug ) ).toEqual( [ 'brand', 'vivid-red', 'mine' ] );
		expect( glowPalette( undefined, DEFAULT, undefined, false ) ).toEqual( [] );
	} );
} );
