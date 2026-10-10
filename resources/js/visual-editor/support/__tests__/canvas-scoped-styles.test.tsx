/**
 * Tests for the shared canvas style hosts (FE-6) and the position /
 * box-shadow canvas filters that publish into them.
 *
 * @since 1.13.0
 */

import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render } from '@testing-library/react';

vi.mock( '@wordpress/blocks', () => ( {
	getBlockType: () => ( {
		supports: { position: true, border: { radius: true } },
	} ),
} ) );

import {
	findBlockDocument,
	getCanvasScopedStyleElement,
	setCanvasScopedStyle,
} from '../canvas-scoped-styles';
import { POSITION_STYLE_CHANNEL, withPositionStyles } from '../../positioning/with-position-styles';
import { BOX_SHADOW_STYLE_CHANNEL, withBoxShadowStyles } from '../../box-shadows/with-box-shadow-styles';

const Block = ( props: { wrapperProps?: { className?: string } } ) => (
	<div data-testid="block" className={ props.wrapperProps?.className ?? '' } />
);

afterEach( () => {
	cleanup();
} );

describe( 'setCanvasScopedStyle', () => {
	it( 'collects every owner into one head <style> per channel', () => {
		const a = {};
		const b = {};

		const removeA = setCanvasScopedStyle( 'test', a, '.a{}' );
		const removeB = setCanvasScopedStyle( 'test', b, '.b{}' );

		const host = getCanvasScopedStyleElement( 'test' );

		expect( host?.parentElement ).toBe( document.head );
		expect( host?.getAttribute( 'data-ap-canvas-styles' ) ).toBe( 'test' );
		expect( host?.textContent ).toBe( '.a{}\n.b{}' );

		setCanvasScopedStyle( 'test', a, '.a2{}' );
		expect( host?.textContent ).toBe( '.a2{}\n.b{}' );

		removeA();
		expect( host?.textContent ).toBe( '.b{}' );

		removeB();
		expect( getCanvasScopedStyleElement( 'test' ) ).toBeNull();
		expect( host?.isConnected ).toBe( false );
	} );

	it( 'treats empty CSS as a removal', () => {
		const owner = {};

		setCanvasScopedStyle( 'empty', owner, '.x{}' );
		setCanvasScopedStyle( 'empty', owner, '' );

		expect( getCanvasScopedStyleElement( 'empty' ) ).toBeNull();
	} );
} );

describe( 'findBlockDocument', () => {
	it( 'falls back to the main document for an unknown block', () => {
		expect( findBlockDocument( 'missing' ) ).toBe( document );
	} );

	it( 'finds a block rendered inside a same-origin iframe', () => {
		const frame = document.createElement( 'iframe' );
		document.body.appendChild( frame );
		const frameDocument = frame.contentDocument as Document;
		const wrapper       = frameDocument.createElement( 'div' );
		wrapper.setAttribute( 'data-block', 'in-frame' );
		frameDocument.body.appendChild( wrapper );

		expect( findBlockDocument( 'in-frame' ) ).toBe( frameDocument );

		frame.remove();
	} );
} );

describe( 'canvas filters publish into the shared host', () => {
	it( 'withPositionStyles renders no <style> sibling before the block', () => {
		const Wrapped = withPositionStyles( Block );

		const { getByTestId, unmount } = render(
			<div>
				<Wrapped
					name="core/group"
					clientId="pos-1"
					attributes={ { style: { position: { value: 'sticky', offsets: { top: { value: 0, unit: 'px' } }, _positionScopeId: 'p1' } } } }
					setAttributes={ () => undefined }
				/>
			</div>,
		);

		expect( getByTestId( 'block' ).previousElementSibling ).toBeNull();
		expect( getByTestId( 'block' ).className ).toContain( 've-pos-p1' );
		expect( getCanvasScopedStyleElement( POSITION_STYLE_CHANNEL )?.textContent ).toContain( '.ve-pos-p1' );

		unmount();
		expect( getCanvasScopedStyleElement( POSITION_STYLE_CHANNEL ) ).toBeNull();
	} );

	it( 'withBoxShadowStyles renders no <style> sibling before the block', () => {
		const Wrapped = withBoxShadowStyles( Block );

		const { getByTestId, unmount } = render(
			<div>
				<Wrapped
					name="core/group"
					clientId="bs-1"
					attributes={ { style: { shadow: { offsetX: '2px', offsetY: '4px', blur: '8px', color: '#000', _shadowScopeId: 's1' } } } }
					setAttributes={ () => undefined }
				/>
			</div>,
		);

		expect( getByTestId( 'block' ).previousElementSibling ).toBeNull();
		expect( getCanvasScopedStyleElement( BOX_SHADOW_STYLE_CHANNEL )?.textContent ).toContain( '.ve-bs-s1' );

		unmount();
		expect( getCanvasScopedStyleElement( BOX_SHADOW_STYLE_CHANNEL ) ).toBeNull();
	} );
} );
