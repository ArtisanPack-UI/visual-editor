/**
 * Session-wide batcher for the `bindings/resolve` endpoint (#1009).
 *
 * Every bound block used to POST its own request, so inserting a
 * pattern with dozens of bound blocks sent dozens of requests at once,
 * each a full app boot resolving the same record. Blocks now queue
 * their payload here instead; one shared debounce flushes the queue as
 * a single batch request (split into chunks of {@see MAX_BATCH_ITEMS})
 * and each block's values are routed back to it.
 *
 * Results are cached by input hash for the editor session, so
 * re-renders, remounts and undo/redo don't refetch. Identical payloads
 * that are queued or in flight share one entry. Failed lookups are not
 * cached so they can be retried.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.17.0
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';
import { resolveBindingsBatch } from './api';
import type { ResolveBindingsPayload } from './api';
import { getBindingsApiConfig } from './config';

/**
 * How long the queue waits for more blocks before it flushes.
 *
 * @since 1.17.0
 */
export const BATCH_DEBOUNCE_MS = 250;

/**
 * Longest a queued block waits for its flush. The debounce restarts on
 * every queued block, so without a ceiling one block whose binding is
 * being typed into would hold back every other block's request.
 *
 * @since 1.17.0
 */
export const BATCH_MAX_WAIT_MS = 1000;

/**
 * Most items sent in one request; matches the server's cap.
 *
 * @since 1.17.0
 */
export const MAX_BATCH_ITEMS = 100;

const MAX_CACHE_ENTRIES = 500;

type ResolvedValues = Record<string, unknown>;

interface Waiter {
	resolve: ( values: ResolvedValues ) => void;
	reject: ( error: Error ) => void;
}

interface Entry {
	payload: ResolveBindingsPayload;
	waiters: Set<Waiter>;
}

const cache = new Map<string, ResolvedValues>();
const queued = new Map<string, Entry>();
const inFlight = new Map<string, Entry>();
let flushTimer: ReturnType<typeof setTimeout> | null = null;
let firstQueuedAt: number | null = null;

function hashPayload( payload: ResolveBindingsPayload ): string {
	return JSON.stringify( [ getBindingsApiConfig().apiBase, payload ] );
}

function remember( hash: string, values: ResolvedValues ): void {
	cache.delete( hash );
	cache.set( hash, values );

	if ( cache.size > MAX_CACHE_ENTRIES ) {
		const oldest = cache.keys().next().value;

		if ( undefined !== oldest ) {
			cache.delete( oldest );
		}
	}
}

function abortError(): Error {
	const error = new Error( 'The binding resolve was aborted.' );
	error.name = 'AbortError';

	return error;
}

function settle( entry: Entry, outcome: ResolvedValues | Error ): void {
	for ( const waiter of entry.waiters ) {
		if ( outcome instanceof Error ) {
			waiter.reject( outcome );
		} else {
			waiter.resolve( outcome );
		}
	}

	entry.waiters.clear();
}

async function sendChunk( chunk: Array<[ string, Entry ]> ): Promise<void> {
	const items = chunk.map( ( [ , entry ], index ) => ( {
		key: String( index ),
		...entry.payload,
	} ) );

	try {
		const results = await resolveBindingsBatch( items );
		const byKey = new Map( results.map( ( result ) => [ String( result.key ), result ] ) );

		chunk.forEach( ( [ hash, entry ], index ) => {
			inFlight.delete( hash );

			const result = byKey.get( String( index ) );

			if ( result && ! result.error ) {
				const values = result.values ?? {};
				remember( hash, values );
				settle( entry, values );

				return;
			}

			settle(
				entry,
				new Error( result?.message ?? __( 'Failed to resolve bindings.', TEXT_DOMAIN ) ),
			);
		} );
	} catch ( error ) {
		const failure = error instanceof Error
			? error
			: new Error( __( 'Failed to resolve bindings.', TEXT_DOMAIN ) );

		for ( const [ hash, entry ] of chunk ) {
			inFlight.delete( hash );
			settle( entry, failure );
		}
	}
}

function flush(): void {
	flushTimer = null;
	firstQueuedAt = null;

	const entries = Array.from( queued.entries() );
	queued.clear();

	for ( const [ hash, entry ] of entries ) {
		inFlight.set( hash, entry );
	}

	for ( let start = 0; start < entries.length; start += MAX_BATCH_ITEMS ) {
		void sendChunk( entries.slice( start, start + MAX_BATCH_ITEMS ) );
	}
}

function scheduleFlush(): void {
	if ( null !== flushTimer ) {
		clearTimeout( flushTimer );
	}

	const now = Date.now();
	firstQueuedAt ??= now;

	const untilDeadline = firstQueuedAt + BATCH_MAX_WAIT_MS - now;

	flushTimer = setTimeout( flush, Math.max( 0, Math.min( BATCH_DEBOUNCE_MS, untilDeadline ) ) );
}

/**
 * The cached values for a payload this session has already resolved,
 * or `undefined` when it hasn't.
 *
 * @since 1.17.0
 */
export function getCachedResolvedBindings(
	payload: ResolveBindingsPayload,
): ResolvedValues | undefined {
	return cache.get( hashPayload( payload ) );
}

/**
 * Queue a block's bindings for the next batch flush and resolve with
 * its values. A cached payload resolves straight away. Aborting
 * `signal` rejects with an `AbortError` and, if no other block is
 * waiting on the same payload, drops it from the queue.
 *
 * @since 1.17.0
 */
export function queueResolveBindings(
	payload: ResolveBindingsPayload,
	signal?: AbortSignal,
): Promise<ResolvedValues> {
	const hash = hashPayload( payload );
	const cached = cache.get( hash );

	if ( cached ) {
		return Promise.resolve( cached );
	}

	if ( signal?.aborted ) {
		return Promise.reject( abortError() );
	}

	return new Promise<ResolvedValues>( ( resolve, reject ) => {
		let entry = inFlight.get( hash ) ?? queued.get( hash );

		if ( ! entry ) {
			entry = { payload, waiters: new Set() };
			queued.set( hash, entry );
		}

		const target = entry;
		const waiter: Waiter = { resolve, reject };
		target.waiters.add( waiter );

		if ( queued.get( hash ) === target ) {
			scheduleFlush();
		}

		signal?.addEventListener(
			'abort',
			() => {
				if ( ! target.waiters.delete( waiter ) ) {
					return;
				}

				if ( 0 === target.waiters.size && queued.get( hash ) === target ) {
					queued.delete( hash );
				}

				reject( abortError() );
			},
			{ once: true },
		);
	} );
}

/**
 * Drop the session cache and any queued, unsent payloads. Exposed for
 * tests.
 *
 * @since 1.17.0
 */
export function clearResolvedBindingsCache(): void {
	if ( null !== flushTimer ) {
		clearTimeout( flushTimer );
		flushTimer = null;
	}

	firstQueuedAt = null;
	cache.clear();
	queued.clear();
	inFlight.clear();
}
