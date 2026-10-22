/**
 * Template-part Position + Effects supports (#976).
 *
 * A sticky site header has to be sticky on the template-part wrapper
 * itself — a sticky block inside the part is held by a wrapper exactly
 * its own height, so it never sticks. The fork therefore has to pass the
 * gates that mount the Position panel (`with-position-control.tsx`) and
 * the Effects panel's z-index (`with-effects.tsx`), and get
 * `style.position` routed through the responsive bag like every other
 * positionable block.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.2
 */

import { describe, expect, it, vi } from 'vitest';

vi.mock( '@wordpress/hooks', () => ( {
    addFilter: vi.fn(),
} ) );

import { addFilter } from '@wordpress/hooks';

import metadata from '../block.json';
import { positionEnabled, registerPositionSupportsExtension } from '../../../positioning/extend-supports';
import { effectsEnabled } from '../../../effects/register-attribute';

type RegisterFilter = ( settings: Record<string, unknown> ) => Record<string, unknown>;

describe( 'artisanpack/template-part supports', () => {
    it( 'opts into the Position panel', () => {
        expect( positionEnabled( metadata.supports ) ).toBe( true );
    } );

    it( 'takes the Effects attribute (z-index)', () => {
        expect( effectsEnabled( { supports: metadata.supports } ) ).toBe( true );
    } );

    it( 'routes style.position through the responsive bag', () => {
        vi.mocked( addFilter ).mockClear();
        delete ( globalThis as unknown as Record<symbol, unknown> )[
            Symbol.for( 'artisanpack-ui.visual-editor.position-supports.registered' )
        ];

        registerPositionSupportsExtension();

        const filter   = vi.mocked( addFilter ).mock.calls[ 0 ][ 2 ] as RegisterFilter;
        const filtered = filter( { ...metadata, supports: { ...metadata.supports } } );

        expect( ( filtered.supports as Record<string, unknown> ).artisanpackResponsive ).toEqual( {
            attributes: [ 'style.position' ],
        } );
    } );
} );
