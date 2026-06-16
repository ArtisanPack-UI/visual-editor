/**
 * Photo Grid wrapper helper tests (#594).
 *
 * Mirrors the PHP `PhotoGridSupportTest` assertions so the two
 * serializers stay byte-equivalent across attribute combinations.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

import { describe, expect, it } from 'vitest'

import {
	getPhotoGridWrapperProps,
	normaliseAspectRatio,
} from '../wrapper'

describe( 'getPhotoGridWrapperProps', () => {
	it( 'returns empty result when the attribute is missing', () => {
		expect( getPhotoGridWrapperProps( {} ) ).toEqual( {} )
		expect( getPhotoGridWrapperProps( null ) ).toEqual( {} )
		expect( getPhotoGridWrapperProps( undefined ) ).toEqual( {} )
	} )

	it( 'returns empty result when enabled is false', () => {
		expect(
			getPhotoGridWrapperProps( {
				photoGrid: {
					enabled: false,
					aspectRatio: '1/1',
					objectFit: 'cover',
					objectPosition: '50% 50%',
				},
			} ),
		).toEqual( {} )
	} )

	it( 'emits the class + style props when enabled', () => {
		const result = getPhotoGridWrapperProps( {
			photoGrid: {
				enabled: true,
				aspectRatio: '16/9',
				objectFit: 'cover',
				objectPosition: '50% 50%',
			},
		} )

		expect( result.className ).toBe( 'has-photo-grid' )
		expect( result.style ).toEqual( {
			'--ap-photo-grid-fit': 'cover',
			'--ap-photo-grid-position': '50% 50%',
			'--ap-photo-grid-aspect': '16/9',
		} )
	} )

	it( 'preserves the contain object-fit token', () => {
		const result = getPhotoGridWrapperProps( {
			photoGrid: {
				enabled: true,
				aspectRatio: '1/1',
				objectFit: 'contain',
				objectPosition: '30% 70%',
			},
		} )

		expect( ( result.style as Record< string, string > )[ '--ap-photo-grid-fit' ] ).toBe( 'contain' )
		expect( ( result.style as Record< string, string > )[ '--ap-photo-grid-position' ] ).toBe( '30% 70%' )
	} )

	it( 'omits the aspect var when value is null', () => {
		const result = getPhotoGridWrapperProps( {
			photoGrid: {
				enabled: true,
				aspectRatio: null,
				objectFit: 'cover',
				objectPosition: '50% 50%',
			},
		} )

		expect( result.className ).toBe( 'has-photo-grid' )
		expect( result.style ).not.toHaveProperty( '--ap-photo-grid-aspect' )
	} )

	it( 'drops malformed aspect ratios', () => {
		for ( const bad of [ '16x9', '16 9', '/9', '16/', '-16/9', '0/9', '16/0', 'abc' ] ) {
			const result = getPhotoGridWrapperProps( {
				photoGrid: {
					enabled: true,
					aspectRatio: bad,
					objectFit: 'cover',
					objectPosition: '50% 50%',
				},
			} )

			expect( result.style ).not.toHaveProperty( '--ap-photo-grid-aspect' )
		}
	} )

	it( 'defaults objectPosition to 50% 50% for empty strings', () => {
		const result = getPhotoGridWrapperProps( {
			photoGrid: {
				enabled: true,
				aspectRatio: '1/1',
				objectFit: 'cover',
				objectPosition: '',
			},
		} )

		expect( ( result.style as Record< string, string > )[ '--ap-photo-grid-position' ] ).toBe( '50% 50%' )
	} )

	it.each( [ null, 0, false, [], {} ] as const )(
		'defaults objectPosition to 50%% 50%% for malformed value %p',
		( bad ) => {
			const result = getPhotoGridWrapperProps( {
				photoGrid: {
					enabled: true,
					aspectRatio: '1/1',
					objectFit: 'cover',
					objectPosition: bad as unknown as string,
				},
			} )

			expect(
				( result.style as Record< string, string > )[ '--ap-photo-grid-position' ],
			).toBe( '50% 50%' )
		},
	)

	it.each( [ '50% 50%; color: red', '50% 50%}{background: red', '50%<script>' ] )(
		'rejects CSS-breakout attempts in objectPosition (%s)',
		( bad ) => {
			const result = getPhotoGridWrapperProps( {
				photoGrid: {
					enabled: true,
					aspectRatio: '1/1',
					objectFit: 'cover',
					objectPosition: bad,
				},
			} )

			expect(
				( result.style as Record< string, string > )[ '--ap-photo-grid-position' ],
			).toBe( '50% 50%' )
		},
	)

	it( 'defaults objectFit to cover for unknown tokens', () => {
		const result = getPhotoGridWrapperProps( {
			photoGrid: {
				enabled: true,
				aspectRatio: '1/1',
				objectFit: 'bogus' as unknown as 'cover',
				objectPosition: '50% 50%',
			},
		} )

		expect( ( result.style as Record< string, string > )[ '--ap-photo-grid-fit' ] ).toBe( 'cover' )
	} )
} )

describe( 'normaliseAspectRatio', () => {
	it.each( [
		[ '1/1', '1/1' ],
		[ '16/9', '16/9' ],
		[ '21.5/9', '21.5/9' ],
		[ '3/4', '3/4' ],
	] )( 'accepts %s', ( input, expected ) => {
		expect( normaliseAspectRatio( input ) ).toBe( expected )
	} )

	it.each( [ null, '', 'auto', 'inherit', '16x9', '/9', '16/', '-1/1', '0/1', '1/0', undefined, 42 ] )(
		'rejects %s',
		( input ) => {
			expect( normaliseAspectRatio( input ) ).toBeNull()
		},
	)
} )
