/**
 * Inline-icon RichText format — set-icon SVG fetch (#717).
 *
 * The icon picker hands back only a `{ set, name }` reference, so when an
 * author picks a registered-set icon we fetch its SVG once (via the same
 * `/visual-editor/api/icons/svg` endpoint the Icon block's canvas preview
 * uses) to embed as the editor preview. The server render-time hydrator
 * re-resolves the reference at render, so this preview is a convenience,
 * not the source of truth.
 */

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
 * span (the server hydrator fills it in at render time).
 */
export async function fetchIconSvg(
    set: string,
    name: string,
    fetchImpl: typeof fetch = fetch,
    apiBase = '/visual-editor/api',
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

    const body = ( await response.json() ) as { svg?: string };
    return 'string' === typeof body.svg ? body.svg : '';
}
