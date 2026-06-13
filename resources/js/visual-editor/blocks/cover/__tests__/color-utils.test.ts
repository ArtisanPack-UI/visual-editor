import { afterEach, describe, expect, it, vi } from 'vitest'

import {
	DEFAULT_BACKGROUND_COLOR,
	getMediaColor,
	retrieveFastAverageColor,
} from '../edit/color-utils'

describe( 'getMediaColor', () => {
	afterEach( () => {
		vi.restoreAllMocks()
	} )

	it( 'returns the default background colour when given an empty url', async () => {
		const color = await getMediaColor( undefined )
		expect( color ).toBe( DEFAULT_BACKGROUND_COLOR )
	} )

	it( 'falls back to the default background colour when getColorAsync rejects', async () => {
		vi.spyOn( retrieveFastAverageColor(), 'getColorAsync' ).mockRejectedValueOnce(
			new Error( 'CORS-tainted canvas' )
		)

		const color = await getMediaColor( 'https://example.test/img-' + Math.random() + '.jpg' )
		expect( color ).toBe( DEFAULT_BACKGROUND_COLOR )
	} )

	it( 'falls back to the default colour when the underlying load never resolves (5 s timeout)', async () => {
		vi.useFakeTimers()

		// Return a promise that never resolves — simulates a stalled `<img>`
		// request that never fires load/error/abort.
		vi.spyOn( retrieveFastAverageColor(), 'getColorAsync' ).mockReturnValueOnce(
			new Promise( () => undefined ) as unknown as ReturnType<
				typeof retrieveFastAverageColor.prototype.getColorAsync
			>
		)

		const url = 'https://example.test/stalled-' + Math.random() + '.jpg'
		const pending = getMediaColor( url )

		vi.advanceTimersByTime( 5_000 )

		const color = await pending
		expect( color ).toBe( DEFAULT_BACKGROUND_COLOR )

		vi.useRealTimers()
	} )
} )
