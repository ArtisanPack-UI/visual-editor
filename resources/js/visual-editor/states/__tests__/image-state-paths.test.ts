import { describe, expect, it } from 'vitest'

import { splitImageStatePaths } from '../image-state-paths'

const paths = {
	'border.color': { hover: '#ff0000' },
	shadow: { hover: 'var:preset|shadow|deep' },
	transition: { hover: 'all 200ms ease' },
	'dimensions.transform': { hover: 'scale(1.1)' },
}

describe( 'splitImageStatePaths (#1052)', () => {
	it.each( [ 'core/image', 'artisanpack/image' ] )( 'moves border and shadow to the img of %s', ( name ) => {
		const { wrapper, image } = splitImageStatePaths( name, {}, paths )

		expect( Object.keys( image ) ).toEqual( [ 'border.color', 'shadow', 'transition' ] )
		expect( Object.keys( wrapper ) ).toEqual( [ 'transition', 'dimensions.transform' ] )
	} )

	it( 'leaves other blocks alone', () => {
		expect( splitImageStatePaths( 'core/group', {}, paths ) ).toEqual( { wrapper: paths, image: {} } )
	} )

	it( 'leaves the border on the wrapper for a gradient border', () => {
		const { image } = splitImageStatePaths( 'core/image', {
			style: { border: { gradient: 'linear-gradient(red, blue)' } },
		}, paths )

		expect( Object.keys( image ) ).toEqual( [ 'shadow', 'transition' ] )
	} )

	it( 'leaves the shadow on the wrapper for a gradient shadow', () => {
		const { image } = splitImageStatePaths( 'core/image', {
			style: { shadow: { gradient: 'linear-gradient(red, blue)' } },
		}, paths )

		expect( Object.keys( image ) ).toEqual( [ 'border.color', 'transition' ] )
	} )

	it( 'moves nothing when both are gradients', () => {
		const { wrapper, image } = splitImageStatePaths( 'core/image', {
			style: {
				border: { gradient: 'linear-gradient(red, blue)' },
				shadow: { gradient: 'linear-gradient(red, blue)' },
			},
		}, paths )

		expect( image ).toEqual( {} )
		expect( wrapper ).toEqual( paths )
	} )
} )
