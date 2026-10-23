/**
 * Registered-set icon SVG fetch, shared by the Icon block's canvas
 * preview and the inline-icon RichText format (#717).
 *
 * The icon picker hands back only a `{ set, name }` reference, so both
 * consumers resolve the SVG through `/visual-editor/api/icons/svg`. The
 * server render-time hydrator re-resolves the reference at render, so
 * this preview is a convenience, not the source of truth.
 *
 * Lookups are memoized per editor session (#1004): the in-flight promise
 * is cached by `apiBase` + `set:name`, so N block instances showing the
 * same icon share a single request instead of each booting the app.
 * Empty results (failures, unknown icons) are evicted once they settle so
 * a transient error doesn't pin the placeholder for the whole session.
 *
 * Cache misses are queued for one microtask and flushed together (#1012):
 * a lone miss uses the `?set=&name=` form, and several distinct misses go
 * out as one `?icons=set:name,…` request per API base (chunked at the
 * server's cap), so inserting a pattern with many different icons costs
 * one request instead of one per icon.
 */

/**
 * Matches `IconSvgController::MAX_BATCH`.
 */
const MAX_BATCH = 100;

interface PendingIcon {
    readonly set: string;
    readonly name: string;
    readonly resolve: ( svg: string ) => void;
}

interface PendingGroup {
    readonly fetchImpl: typeof fetch;
    readonly apiBase: string;
    readonly icons: Map< string, PendingIcon >;
}

const svgCache = new Map< string, Promise< string > >();
let pendingGroups: PendingGroup[] = [];
let flushScheduled = false;

/**
 * Drop every memoized SVG lookup. Exposed for tests.
 *
 * @since 1.16.0
 */
export function clearIconSvgCache(): void {
    svgCache.clear();
    pendingGroups  = [];
    flushScheduled = false;
}

function readMetaCsrfToken(): string | null {
    if ( 'undefined' === typeof document ) {
        return null;
    }
    const meta = document.querySelector< HTMLMetaElement >( 'meta[name="csrf-token"]' );
    return meta ? meta.content : null;
}

function readXsrfCookie(): string | null {
    if ( 'undefined' === typeof document ) {
        return null;
    }
    const match = document.cookie.match( /(?:^|;\s*)XSRF-TOKEN=([^;]*)/ );
    return match ? decodeURIComponent( match[ 1 ] ) : null;
}

function buildHeaders(): Record< string, string > {
    const headers: Record< string, string > = { Accept: 'application/json' };
    const csrf = readMetaCsrfToken();
    const xsrf = readXsrfCookie();
    if ( csrf ) {
        headers[ 'X-CSRF-TOKEN' ] = csrf;
    }
    if ( xsrf ) {
        headers[ 'X-XSRF-TOKEN' ] = xsrf;
    }
    return headers;
}

/**
 * Fetch the SVG markup for a registered-set icon. Returns an empty string
 * on any non-OK response so the caller can still insert the reference
 * span (the server hydrator fills it in at render time). Concurrent and
 * later calls for the same icon reuse the first successful request, and
 * distinct icons requested in the same tick share one batched request.
 */
export function fetchIconSvg(
    set: string,
    name: string,
    fetchImpl: typeof fetch = fetch,
    apiBase = '/visual-editor/api',
): Promise< string > {
    const key    = `${ apiBase }|${ set }:${ name }`;
    const cached = svgCache.get( key );
    if ( cached ) {
        return cached;
    }

    const pending: Promise< string > = new Promise< string >( ( resolve ) => {
        enqueue( fetchImpl, apiBase, { set, name, resolve } );
    } ).then( ( svg ) => {
        if ( '' === svg && pending === svgCache.get( key ) ) {
            svgCache.delete( key );
        }
        return svg;
    } );
    svgCache.set( key, pending );

    return pending;
}

function enqueue( fetchImpl: typeof fetch, apiBase: string, icon: PendingIcon ): void {
    let group = pendingGroups.find(
        ( candidate ) => candidate.fetchImpl === fetchImpl && candidate.apiBase === apiBase
    );
    if ( ! group ) {
        group = { fetchImpl, apiBase, icons: new Map() };
        pendingGroups.push( group );
    }
    group.icons.set( `${ icon.set }:${ icon.name }`, icon );

    if ( ! flushScheduled ) {
        flushScheduled = true;
        queueMicrotask( flush );
    }
}

function flush(): void {
    const groups   = pendingGroups;
    pendingGroups  = [];
    flushScheduled = false;

    for ( const group of groups ) {
        const icons = [ ...group.icons.values() ];

        for ( let offset = 0; offset < icons.length; offset += MAX_BATCH ) {
            void flushChunk( group, icons.slice( offset, offset + MAX_BATCH ) );
        }
    }
}

async function flushChunk( group: PendingGroup, icons: ReadonlyArray< PendingIcon > ): Promise< void > {
    if ( 1 === icons.length ) {
        const [ icon ] = icons;
        icon.resolve( await requestIconSvg( icon.set, icon.name, group.fetchImpl, group.apiBase ) );
        return;
    }

    const found = await requestIconSvgBatch( icons, group.fetchImpl, group.apiBase );
    for ( const icon of icons ) {
        icon.resolve( found.get( `${ icon.set }:${ icon.name }` ) ?? '' );
    }
}

async function requestJson( url: string, fetchImpl: typeof fetch ): Promise< unknown > {
    let response: Response;
    try {
        response = await fetchImpl( url, {
            headers: buildHeaders(),
            credentials: 'include',
        } );
    } catch {
        return null;
    }

    if ( ! response.ok ) {
        return null;
    }

    // `response.json()` rejects on an empty or non-JSON body; callers
    // await this without a try/catch, so swallow it and fall back to the
    // documented empty-string result (the server hydrator fills the icon
    // in at render time regardless).
    try {
        return await response.json();
    } catch {
        return null;
    }
}

async function requestIconSvg(
    set: string,
    name: string,
    fetchImpl: typeof fetch,
    apiBase: string,
): Promise< string > {
    const params = new URLSearchParams( { set, name } );
    const body   = ( await requestJson( `${ apiBase }/icons/svg?${ params.toString() }`, fetchImpl ) ) as {
        svg?: unknown;
    } | null;

    return body && 'string' === typeof body.svg ? body.svg : '';
}

async function requestIconSvgBatch(
    icons: ReadonlyArray< PendingIcon >,
    fetchImpl: typeof fetch,
    apiBase: string,
): Promise< Map< string, string > > {
    // Sorted so the same set of icons always produces the same URL and
    // the browser's HTTP cache can serve it across sessions.
    const keys   = icons.map( ( icon ) => `${ icon.set }:${ icon.name }` ).sort();
    const params = new URLSearchParams( { icons: keys.join( ',' ) } );
    const body   = await requestJson( `${ apiBase }/icons/svg?${ params.toString() }`, fetchImpl );

    const found = new Map< string, string >();
    if ( null === body || 'object' !== typeof body || Array.isArray( body ) ) {
        return found;
    }

    for ( const [ key, svg ] of Object.entries( body as Record< string, unknown > ) ) {
        if ( 'string' === typeof svg && '' !== svg ) {
            found.set( key, svg );
        }
    }
    return found;
}
