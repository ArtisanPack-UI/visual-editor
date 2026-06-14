/**
 * Thin fetch wrappers for the `/visual-editor/api/bindings/*`
 * endpoints (#504). Mirrors the conventions used by
 * `editor/api-client.ts`: `same-origin` credentials, the session CSRF
 * token on mutating calls, normalized error throws.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

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

interface ResolveContextPayload {
	resource: string | null;
	id: number | string | null;
	draft?: Record<string, unknown>;
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
		'Failed to load binding sources.'
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
		`Failed to load fields for source "${ source }".`
	);

	return body.fields ?? [];
}

export async function resolveBindings(
	payload: {
		attrs: Record<string, unknown>;
		bindings: BindingsMap;
		context: ResolveContextPayload;
	},
	signal?: AbortSignal
): Promise<Record<string, unknown>> {
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

	const response = await fetch( `${ apiBase }/bindings/resolve`, {
		method: 'POST',
		credentials: 'same-origin',
		headers,
		body: JSON.stringify( payload ),
		signal,
	} );

	const body = await jsonOrThrow<ResolveResponse>(
		response,
		'Failed to resolve bindings.'
	);

	return body.values ?? {};
}
