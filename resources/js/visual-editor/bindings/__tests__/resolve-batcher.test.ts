import { act, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock( '@wordpress/i18n', () => ( {
	__: ( text: string ) => text,
} ) )

import { resetBindingsApiConfig } from '../config'
import {
	BATCH_DEBOUNCE_MS,
	BATCH_MAX_WAIT_MS,
	MAX_BATCH_ITEMS,
	clearResolvedBindingsCache,
	getCachedResolvedBindings,
	queueResolveBindings,
} from '../resolve-batcher'
import { useResolvedBindings } from '../use-resolved-bindings'
import type { ResolveBindingsPayload } from '../api'

interface SentItem {
	key: string
	attrs: Record<string, unknown>
	bindings: Record<string, { source: string; args?: Record<string, unknown> }>
	context: { resource: string | null; id: number | string | null }
}

let fetchMock: ReturnType<typeof vi.fn>

/**
 * Answer every batch by echoing each item's bound `args.key` back as
 * its value, unless `respond` overrides the result for an item.
 */
function installFetch(
	respond?: ( item: SentItem ) => Record<string, unknown> | undefined,
): void {
	fetchMock = vi.fn( async ( _url: string, init: RequestInit ) => {
		const { items } = JSON.parse( init.body as string ) as { items: SentItem[] }

		const results = items.map( ( item ) => {
			const override = respond?.( item )

			if ( override ) {
				return { key: item.key, ...override }
			}

			const values: Record<string, unknown> = {}

			for ( const [ attribute, binding ] of Object.entries( item.bindings ) ) {
				values[ attribute ] = `${ String( binding.args?.key ) }@${ String( item.context.id ) }`
			}

			return { key: item.key, values }
		} )

		return new Response( JSON.stringify( { results } ), { status: 200 } )
	} )

	vi.stubGlobal( 'fetch', fetchMock )
}

function payload( field: string, id: number | string = 1 ): ResolveBindingsPayload {
	return {
		attrs: { label: 'STATIC' },
		bindings: { label: { source: 'custom_field', args: { key: field } } },
		context: { resource: 'posts', id },
	}
}

function sentItems( call = 0 ): SentItem[] {
	return ( JSON.parse( fetchMock.mock.calls[ call ][ 1 ].body as string ) as { items: SentItem[] } ).items
}

beforeEach( () => {
	vi.useFakeTimers()
	resetBindingsApiConfig()
	clearResolvedBindingsCache()
	installFetch()
} )

afterEach( () => {
	clearResolvedBindingsCache()
	vi.unstubAllGlobals()
	vi.useRealTimers()
} )

describe( 'queueResolveBindings', () => {
	it( 'sends every block queued within the debounce as one request', async () => {
		const pending = Array.from( { length: 21 }, ( _, i ) => queueResolveBindings( payload( `field_${ i }` ) ) )

		expect( fetchMock ).not.toHaveBeenCalled()

		await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )

		const values = await Promise.all( pending )

		expect( fetchMock ).toHaveBeenCalledTimes( 1 )
		expect( fetchMock.mock.calls[ 0 ][ 0 ] ).toBe( '/visual-editor/api/bindings/resolve' )
		expect( sentItems() ).toHaveLength( 21 )
		expect( values[ 0 ] ).toEqual( { label: 'field_0@1' } )
		expect( values[ 20 ] ).toEqual( { label: 'field_20@1' } )
	} )

	it( 'routes results back by key across mixed contexts', async () => {
		const first  = queueResolveBindings( payload( 'title', 1 ) )
		const second = queueResolveBindings( payload( 'title', 2 ) )

		await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )

		await expect( first ).resolves.toEqual( { label: 'title@1' } )
		await expect( second ).resolves.toEqual( { label: 'title@2' } )
	} )

	it( 'splits batches over the item cap into several requests', async () => {
		const pending = Array.from(
			{ length: MAX_BATCH_ITEMS + 5 },
			( _, i ) => queueResolveBindings( payload( `field_${ i }` ) ),
		)

		await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )
		await Promise.all( pending )

		expect( fetchMock ).toHaveBeenCalledTimes( 2 )
		expect( sentItems( 0 ) ).toHaveLength( MAX_BATCH_ITEMS )
		expect( sentItems( 1 ) ).toHaveLength( 5 )
	} )

	it( 'shares one item between identical queued payloads', async () => {
		const a = queueResolveBindings( payload( 'title' ) )
		const b = queueResolveBindings( payload( 'title' ) )

		await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )

		await expect( a ).resolves.toEqual( { label: 'title@1' } )
		await expect( b ).resolves.toEqual( { label: 'title@1' } )
		expect( sentItems() ).toHaveLength( 1 )
	} )

	it( 'joins a request already in flight instead of sending another', async () => {
		let release: ( () => void ) | null = null

		fetchMock.mockImplementationOnce( async () => {
			await new Promise<void>( ( done ) => {
				release = done
			} )

			return new Response( JSON.stringify( { results: [ { key: '0', values: { label: 'late' } } ] } ), { status: 200 } )
		} )

		const first = queueResolveBindings( payload( 'title' ) )
		await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )

		const second = queueResolveBindings( payload( 'title' ) )
		await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )

		release!()

		await expect( first ).resolves.toEqual( { label: 'late' } )
		await expect( second ).resolves.toEqual( { label: 'late' } )
		expect( fetchMock ).toHaveBeenCalledTimes( 1 )
	} )

	it( 'serves resolved payloads from the session cache', async () => {
		const first = queueResolveBindings( payload( 'title' ) )
		await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )
		await first

		expect( getCachedResolvedBindings( payload( 'title' ) ) ).toEqual( { label: 'title@1' } )

		await expect( queueResolveBindings( payload( 'title' ) ) ).resolves.toEqual( { label: 'title@1' } )
		await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )

		expect( fetchMock ).toHaveBeenCalledTimes( 1 )
	} )

	it( 'rejects only the item the server reports an error for, and does not cache it', async () => {
		installFetch( ( item ) => (
			2 === item.context.id ? { error: 'forbidden', message: 'Nope.' } : undefined
		) )

		const allowed   = queueResolveBindings( payload( 'title', 1 ) )
		const forbidden = queueResolveBindings( payload( 'title', 2 ) ).catch( ( error: Error ) => error )

		await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )

		await expect( allowed ).resolves.toEqual( { label: 'title@1' } )
		expect( ( await forbidden ) ).toMatchObject( { message: 'Nope.' } )
		expect( getCachedResolvedBindings( payload( 'title', 2 ) ) ).toBeUndefined()
	} )

	it( 'rejects every item when the request fails, so they can be retried', async () => {
		fetchMock.mockResolvedValueOnce( new Response( '', { status: 500 } ) )

		const caught = queueResolveBindings( payload( 'title' ) ).catch( ( error: Error ) => error )

		await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )

		expect( ( await caught ) ).toBeInstanceOf( Error )

		const retry = queueResolveBindings( payload( 'title' ) )
		await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )

		await expect( retry ).resolves.toEqual( { label: 'title@1' } )
		expect( fetchMock ).toHaveBeenCalledTimes( 2 )
	} )

	it( 'flushes by the max wait even while blocks keep queueing', async () => {
		const first = queueResolveBindings( payload( 'first' ) )
		const steps = Math.ceil( BATCH_MAX_WAIT_MS / ( BATCH_DEBOUNCE_MS - 50 ) ) + 1

		for ( let i = 0; i < steps && 0 === fetchMock.mock.calls.length; i++ ) {
			await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS - 50 )
			void queueResolveBindings( payload( `typing_${ i }` ) )
		}

		expect( fetchMock ).toHaveBeenCalledTimes( 1 )
		await expect( first ).resolves.toEqual( { label: 'first@1' } )

		await vi.advanceTimersByTimeAsync( BATCH_MAX_WAIT_MS )
	} )

	it( 'drops an aborted payload from the queue', async () => {
		const controller = new AbortController()
		const aborted    = queueResolveBindings( payload( 'stale' ), controller.signal )
		const kept       = queueResolveBindings( payload( 'fresh' ) )

		controller.abort()

		await expect( aborted ).rejects.toMatchObject( { name: 'AbortError' } )

		await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )
		await kept

		expect( sentItems().map( ( item ) => item.bindings.label.args?.key ) ).toEqual( [ 'fresh' ] )
	} )
} )

describe( 'useResolvedBindings', () => {
	const bindings = { label: { source: 'custom_field', args: { key: 'title' } } }

	it( 'resolves many bound blocks with one request', async () => {
		const hooks = Array.from( { length: 10 }, ( _, i ) => renderHook( () => useResolvedBindings(
			{ label: { source: 'custom_field', args: { key: `field_${ i }` } } },
			{ label: 'STATIC' },
			'posts',
			1,
		) ) )

		await act( async () => {
			await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )
		} )

		expect( fetchMock ).toHaveBeenCalledTimes( 1 )
		expect( hooks[ 3 ].result.current.values ).toEqual( { label: 'field_3@1' } )
		expect( hooks[ 3 ].result.current.loading ).toBe( false )
	} )

	it( 'reads a remounted block straight from the cache', async () => {
		const first = renderHook( () => useResolvedBindings( bindings, { label: 'STATIC' }, 'posts', 1 ) )

		await act( async () => {
			await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )
		} )

		first.unmount()

		const second = renderHook( () => useResolvedBindings( bindings, { label: 'STATIC' }, 'posts', 1 ) )

		expect( second.result.current ).toEqual( { values: { label: 'title@1' }, loading: false, error: null } )
		expect( fetchMock ).toHaveBeenCalledTimes( 1 )
	} )

	it( 'does not request anything without active bindings or a record', async () => {
		renderHook( () => useResolvedBindings( {}, { label: 'STATIC' }, 'posts', 1 ) )
		renderHook( () => useResolvedBindings( bindings, { label: 'STATIC' }, null, null ) )

		await act( async () => {
			await vi.advanceTimersByTimeAsync( BATCH_DEBOUNCE_MS )
		} )

		expect( fetchMock ).not.toHaveBeenCalled()
	} )
} )
