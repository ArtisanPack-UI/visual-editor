/**
 * Thin fetch wrappers for the `/visual-editor/api/bindings/*`
 * endpoints (#504). Mirrors the conventions used by
 * `editor/api-client.ts`: `same-origin` credentials, the session CSRF
 * token on mutating calls, normalized error throws.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { __, sprintf } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';
import { getBindingsApiConfig } from './config';
import type {
	BindingFieldDefinition,
	BindingsMap,
	BindingSourceSummary,
} from './types';

function readCsrfToken(): string | null {
	if ( typeof document === 'undefined' ) {
		return null;
	}

	const meta = document.querySelector( 'meta[name="csrf-token"]' );
	const content = meta?.getAttribute( 'content' );

	return content && content.length > 0 ? content : null;
}

interface SourcesResponse {
	sources: BindingSourceSummary[];
}

interface FieldsResponse {
	source: string;
	resource: string;
	fields: BindingFieldDefinition[];
}

interface ResolveResponse {
	values: Record<string, unknown>;
}

export interface ResolveContextPayload {
	resource: string | null;
	id: number | string | null;
	draft?: Record<string, unknown>;
}

export interface ResolveBindingsPayload {
	attrs: Record<string, unknown>;
	bindings: BindingsMap;
	context: ResolveContextPayload;
}

export interface ResolveBindingsBatchItem extends ResolveBindingsPayload {
	key: string;
}

/**
 * One entry of a batch resolve response: the item's resolved `values`,
 * or an `error` code when the item was malformed or its record is
 * forbidden.
 */
export interface ResolveBindingsBatchResult {
	key: string | number | null;
	values?: Record<string, unknown>;
	error?: string;
	message?: string;
}

interface ResolveBatchResponse {
	results: ResolveBindingsBatchResult[];
}

async function jsonOrThrow<T>(
	response: Response,
	fallbackMessage: string
): Promise<T> {
	if ( ! response.ok ) {
		throw new Error( `${ fallbackMessage } (HTTP ${ response.status })` );
	}

	return ( await response.json() ) as T;
}

export async function fetchBindingSources(
	signal?: AbortSignal
): Promise<BindingSourceSummary[]> {
	const { apiBase } = getBindingsApiConfig();

	const response = await fetch( `${ apiBase }/bindings/sources`, {
		method: 'GET',
		credentials: 'same-origin',
		headers: {
			Accept: 'application/json',
			'X-Requested-With': 'XMLHttpRequest',
		},
		signal,
	} );

	const body = await jsonOrThrow<SourcesResponse>(
		response,
		__( 'Failed to load binding sources.', TEXT_DOMAIN )
	);

	return body.sources ?? [];
}

export async function fetchBindingFields(
	source: string,
	resource: string | null | undefined,
	signal?: AbortSignal
): Promise<BindingFieldDefinition[]> {
	const { apiBase } = getBindingsApiConfig();

	const url = new URL(
		`${ apiBase }/bindings/sources/${ encodeURIComponent( source ) }/fields`,
		window.location.origin
	);

	if ( resource ) {
		url.searchParams.set( 'resource', resource );
	}

	const response = await fetch( url.toString(), {
		method: 'GET',
		credentials: 'same-origin',
		headers: {
			Accept: 'application/json',
			'X-Requested-With': 'XMLHttpRequest',
		},
		signal,
	} );

	const body = await jsonOrThrow<FieldsResponse>(
		response,
		sprintf(
			/* translators: %s: binding source name. */
			__( 'Failed to load fields for source "%s".', TEXT_DOMAIN ),
			source
		)
	);

	return body.fields ?? [];
}

function postResolve(
	payload: unknown,
	signal?: AbortSignal
): Promise<Response> {
	const { apiBase } = getBindingsApiConfig();
	const csrfToken = readCsrfToken();

	const headers: Record<string, string> = {
		Accept: 'application/json',
		'Content-Type': 'application/json',
		'X-Requested-With': 'XMLHttpRequest',
	};

	if ( csrfToken ) {
		headers[ 'X-CSRF-TOKEN' ] = csrfToken;
	}

	return fetch( `${ apiBase }/bindings/resolve`, {
		method: 'POST',
		credentials: 'same-origin',
		headers,
		body: JSON.stringify( payload ),
		signal,
	} );
}

export async function resolveBindings(
	payload: ResolveBindingsPayload,
	signal?: AbortSignal
): Promise<Record<string, unknown>> {
	const response = await postResolve( payload, signal );

	const body = await jsonOrThrow<ResolveResponse>(
		response,
		__( 'Failed to resolve bindings.', TEXT_DOMAIN )
	);

	return body.values ?? {};
}

/**
 * Resolve several blocks' bindings in one request (#1009). Results
 * come back in request order, one per item, each carrying the item's
 * `key`. Per-item failures arrive as `error` entries rather than a
 * rejected promise; only a failed request rejects.
 *
 * @since 1.17.0
 */
export async function resolveBindingsBatch(
	items: ResolveBindingsBatchItem[],
	signal?: AbortSignal
): Promise<ResolveBindingsBatchResult[]> {
	const response = await postResolve( { items }, signal );

	const body = await jsonOrThrow<ResolveBatchResponse>(
		response,
		__( 'Failed to resolve bindings.', TEXT_DOMAIN )
	);

	return Array.isArray( body.results ) ? body.results : [];
}
