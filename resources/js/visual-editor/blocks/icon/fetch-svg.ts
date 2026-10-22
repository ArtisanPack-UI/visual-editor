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
 */

const svgCache = new Map< string, Promise< string > >();

/**
 * Drop every memoized SVG lookup. Exposed for tests.
 *
 * @since 1.16.0
 */
export function clearIconSvgCache(): void {
    svgCache.clear();
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
 * later calls for the same icon reuse the first successful request.
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

    const pending: Promise< string > = requestIconSvg( set, name, fetchImpl, apiBase ).then( ( svg ) => {
        if ( '' === svg && pending === svgCache.get( key ) ) {
            svgCache.delete( key );
        }
        return svg;
    } );
    svgCache.set( key, pending );

    return pending;
}

async function requestIconSvg(
    set: string,
    name: string,
    fetchImpl: typeof fetch,
    apiBase: string,
): Promise< string > {
    const params = new URLSearchParams( { set, name } );

    let response: Response;
    try {
        response = await fetchImpl( `${ apiBase }/icons/svg?${ params.toString() }`, {
            headers: buildHeaders(),
            credentials: 'include',
        } );
    } catch {
        return '';
    }

    if ( ! response.ok ) {
        return '';
    }

    // `response.json()` rejects on an empty or non-JSON body; the caller
    // awaits this without a try/catch, so swallow it and fall back to the
    // documented empty-string result (the server hydrator fills the icon
    // in at render time regardless).
    try {
        const body = ( await response.json() ) as { svg?: string };
        return 'string' === typeof body.svg ? body.svg : '';
    } catch {
        return '';
    }
}
