/**
 * Link attribute helpers shared by every block that edits a URL / target
 * / rel triple through `LinkControl` (#1022).
 *
 * `rel` is handled as a token set: the author's own tokens are kept, and
 * the new-tab (`noreferrer noopener`) and `nofollow` tokens are owned by
 * the matching LinkControl toggles, so toggling never leaves stray or
 * duplicate tokens whatever order they were stored in. Dynamic Content
 * `{{token}}` hrefs are resolved at render time, so they are never
 * prefixed with `https://`.
 *
 * Kept free of component imports so block helpers (e.g. the Button
 * block's `getUpdatedLinkAttributes()`) can use it without pulling in
 * the toolbar control.
 *
 * @since 1.18.0
 */

import { prependHTTPS } from '@wordpress/url';

export const NEW_TAB_TARGET = '_blank';
export const NEW_TAB_REL_TOKENS: readonly string[] = [ 'noreferrer', 'noopener' ];
export const NOFOLLOW_REL = 'nofollow';

export interface LinkChange {
    readonly url?: string;
    readonly opensInNewTab?: boolean;
    /**
     * Whether the link is nofollow. `undefined` means the caller's
     * LinkControl has no nofollow toggle, so an existing `nofollow`
     * token is left as the author set it.
     */
    readonly nofollow?: boolean;
}

export interface ResolvedLink {
    readonly url: string;
    readonly linkTarget: string | undefined;
    readonly rel: string | undefined;
}

/**
 * Split a `rel` string into its non-empty tokens.
 *
 * @since 1.18.0
 */
function relTokens( rel: string | undefined ): string[] {
    return ( rel ?? '' ).split( /\s+/ ).filter( ( token ) => '' !== token );
}

/**
 * Whether a `rel` string carries the `nofollow` token.
 *
 * @since 1.18.0
 */
export function hasNofollow( rel: string | undefined ): boolean {
    return relTokens( rel ).includes( NOFOLLOW_REL );
}

/**
 * Normalize a URL typed or picked in LinkControl: trim it and prefix a
 * bare domain with `https://`, leaving Dynamic Content `{{token}}` hrefs
 * untouched.
 *
 * @since 1.18.0
 */
export function normalizeLinkUrl( url: string | undefined ): string {
    const trimmed = ( url ?? '' ).trim();

    return trimmed.startsWith( '{{' ) ? trimmed : prependHTTPS( trimmed );
}

/**
 * Resolve a LinkControl change into the normalized `{ url, linkTarget,
 * rel }` triple.
 *
 * @since 1.18.0
 */
export function resolveLinkChange( change: LinkChange, currentRel: string | undefined ): ResolvedLink {
    const keepNofollow = undefined === change.nofollow && hasNofollow( currentRel );
    const managed = new Set( [ ...NEW_TAB_REL_TOKENS, NOFOLLOW_REL ] );
    const tokens = new Set( relTokens( currentRel ).filter( ( token ) => ! managed.has( token ) ) );

    if ( change.opensInNewTab ) {
        NEW_TAB_REL_TOKENS.forEach( ( token ) => tokens.add( token ) );
    }

    if ( change.nofollow || keepNofollow ) {
        tokens.add( NOFOLLOW_REL );
    }

    const rel = Array.from( tokens ).join( ' ' );

    return {
        url: normalizeLinkUrl( change.url ),
        linkTarget: change.opensInNewTab ? NEW_TAB_TARGET : undefined,
        rel: rel || undefined,
    };
}
