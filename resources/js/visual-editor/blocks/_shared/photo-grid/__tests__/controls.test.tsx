/**
 * Photo Grid inspector control tests (#594).
 *
 * Renders the `<PhotoGridControls />` with a stub `useSettings` and
 * confirms it surfaces the toggle, hides the sub-controls until
 * enabled, and propagates theme defaults into the change-handler
 * payload.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'

// `@wordpress/components` SelectControl reaches into `ResizeObserver`
// via the compose package. jsdom doesn't ship it; stub a no-op so the
// expanded panel renders without throwing.
beforeAll( () => {
	if ( typeof globalThis.ResizeObserver === 'undefined' ) {
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		( globalThis as any ).ResizeObserver = class {
			observe(): void {}
			unobserve(): void {}
			disconnect(): void {}
		}
	}
} )

vi.mock( '@wordpress/block-editor', () => ( {
	useSettings: ( ...paths: string[] ): unknown[] =>
		paths.map( ( path ) => {
			switch ( path ) {
				case 'artisanpack.photoGrid.enable':
					return true
				case 'artisanpack.photoGrid.defaultAspectRatio':
					return '4/3'
				case 'artisanpack.photoGrid.defaultObjectFit':
					return 'cover'
				case 'artisanpack.photoGrid.defaultObjectPosition':
					return '50% 50%'
				default:
					return undefined
			}
		} ),
} ) )

vi.mock( '@wordpress/i18n', () => ( {
	__: ( s: string ) => s,
} ) )

import { PhotoGridControls } from '../controls'

/**
 * The Photo Grid `PanelBody` ships collapsed (`initialOpen={false}`).
 * Each spec expands it via the panel toggle button before asserting
 * the inner controls — same shape as the host inspector UX where the
 * author has to expand the panel before seeing anything.
 */
function expandPanel(): void {
	fireEvent.click( screen.getByRole( 'button', { name: /Photo Grid/ } ) )
}

describe( '<PhotoGridControls />', () => {
	it( 'renders the toggle disabled by default', () => {
		render( <PhotoGridControls photoGrid={ null } onChange={ () => {} } /> )
		expandPanel()

		const toggle = screen.getByRole( 'checkbox', { name: /Enable Photo Grid/ } )
		expect( toggle ).toBeInTheDocument()
		expect( toggle ).not.toBeChecked()
	} )

	it( 'does not render the aspect ratio dropdown until enabled', () => {
		render( <PhotoGridControls photoGrid={ null } onChange={ () => {} } /> )
		expandPanel()

		expect( screen.queryByLabelText( /Aspect ratio/ ) ).toBeNull()
	} )

	it( 'reveals the aspect ratio dropdown when enabled', () => {
		render(
			<PhotoGridControls
				photoGrid={ {
					enabled: true,
					aspectRatio: '1/1',
					objectFit: 'cover',
					objectPosition: '50% 50%',
				} }
				onChange={ () => {} }
			/>,
		)
		expandPanel()

		expect( screen.getByLabelText( /Aspect ratio/ ) ).toBeInTheDocument()
	} )

	it( 'seeds onChange with theme defaults when toggling on', () => {
		const onChange = vi.fn()

		render( <PhotoGridControls photoGrid={ null } onChange={ onChange } /> )
		expandPanel()

		const toggle = screen.getByRole( 'checkbox', { name: /Enable Photo Grid/ } )
		fireEvent.click( toggle )

		expect( onChange ).toHaveBeenCalledTimes( 1 )
		const payload = onChange.mock.calls[ 0 ][ 0 ]
		expect( payload.enabled ).toBe( true )
		// Theme default `4/3` from the stubbed `useSettings`.
		expect( payload.aspectRatio ).toBe( '4/3' )
		expect( payload.objectFit ).toBe( 'cover' )
		expect( payload.objectPosition ).toBe( '50% 50%' )
	} )
} )
