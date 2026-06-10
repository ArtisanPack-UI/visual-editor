/**
 * Icon block — custom-SVG paste/upload helpers.
 *
 * Phase 5 (#556). The authoritative sanitizer is the server-side
 * `SvgSanitizer` reached through `/visual-editor/api/icons/svg/sanitize`.
 * The helpers in this file handle the request envelope + file-type
 * gate; everything security-relevant happens server-side so the
 * warnings the author sees match what the server actually stripped.
 */

/**
 * Whether the file looks plausibly like an SVG. We reject by both MIME
 * type AND extension because:
 *
 *  - Browsers occasionally hand back an empty `type` for files dragged
 *    out of weird sources (Finder previews, some download tools), so a
 *    pure MIME check rejects legitimate uploads.
 *  - Trusting the extension alone lets a renamed `payload.exe.svg`
 *    through without even a smell test; pairing the two narrows the
 *    gap that the server-side sanitizer has to close.
 */
export function isSvgFile( file: File ): boolean {
    const name = file.name.toLowerCase();
    const type = file.type.toLowerCase();
    const extensionOk = name.endsWith( '.svg' );
    const mimeOk = type === '' || type === 'image/svg+xml' || type === 'image/svg';
    return extensionOk && mimeOk;
}

/**
 * Cap the upload size before we even read the file. Mirrors the 256 KB
 * cap on the server endpoint so the editor's error message matches what
 * the API would return on submit.
 */
export const MAX_SVG_BYTES = 262_144;

export interface SanitizeResponse {
    readonly svg: string;
    readonly warnings: readonly string[];
}

function readMetaCsrfToken(): string | null {
    if ( typeof document === 'undefined' ) {
        return null;
    }
    const meta = document.querySelector< HTMLMetaElement >( 'meta[name="csrf-token"]' );
    return meta ? meta.content : null;
}

function readXsrfCookie(): string | null {
    if ( typeof document === 'undefined' ) {
        return null;
    }
    const match = document.cookie.match( /(?:^|;\s*)XSRF-TOKEN=([^;]*)/ );
    return match ? decodeURIComponent( match[ 1 ] ) : null;
}

/**
 * POST the raw markup to the sanitize endpoint and return the
 * authoritative result. Throws on network / 4xx / 5xx so the caller can
 * surface a single, consistent error message.
 */
export async function sanitizeOnServer(
    raw: string,
    fetchImpl: typeof fetch = fetch,
): Promise< SanitizeResponse > {
    const headers: Record< string, string > = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const csrf = readMetaCsrfToken();
    const xsrf = readXsrfCookie();
    if ( csrf ) {
        headers[ 'X-CSRF-TOKEN' ] = csrf;
    }
    if ( xsrf ) {
        headers[ 'X-XSRF-TOKEN' ] = xsrf;
    }

    const response = await fetchImpl( '/visual-editor/api/icons/svg/sanitize', {
        method: 'POST',
        credentials: 'include',
        headers,
        body: JSON.stringify( { svg: raw } ),
    } );

    if ( ! response.ok ) {
        // Surface the server's warnings if it returned a structured
        // 4xx (e.g. the 256 KB cap). Falling back to a generic message
        // for 5xx / network failures keeps the editor copy stable.
        let warnings: readonly string[] = [];
        try {
            const body = ( await response.json() ) as { warnings?: readonly string[] };
            if ( Array.isArray( body.warnings ) ) {
                warnings = body.warnings;
            }
        } catch {
            // ignore — body wasn't JSON, fall through to the throw.
        }
        const detail = warnings.length > 0 ? `: ${ warnings.join( ', ' ) }` : '';
        throw new Error( `svg sanitize failed (${ response.status })${ detail }` );
    }

    const body = ( await response.json() ) as Partial< SanitizeResponse >;
    return {
        svg: typeof body.svg === 'string' ? body.svg : '',
        warnings: Array.isArray( body.warnings ) ? body.warnings : [],
    };
}
