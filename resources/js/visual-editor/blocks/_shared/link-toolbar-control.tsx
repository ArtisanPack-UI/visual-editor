/**
 * Shared toolbar link control (#1022).
 *
 * The Image and Icon blocks both persist a URL / target / rel triple but
 * had no editor UI to set it. This control mirrors the Button block's
 * toolbar flow — a Link / Unlink pair in `BlockControls` that opens an
 * `ArtisanPackLinkControl` popover with "Open in new tab" and "Mark as
 * nofollow" toggles — and reports changes as a normalized
 * `{ url, linkTarget, rel }` triple so each block can map it onto its
 * own attribute names.
 *
 * Unlike the Button block's `getUpdatedLinkAttributes()`, the rel tokens
 * are handled as a set (so toggling never leaves stray or duplicate
 * tokens) and Dynamic Content `{{token}}` hrefs are not prefixed with
 * `https://`.
 *
 * @since 1.18.0
 */

import type { ReactElement } from 'react';
import { BlockControls } from '@wordpress/block-editor';
import { Popover, ToolbarButton } from '@wordpress/components';
import { useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { link as linkIcon, linkOff as linkOffIcon } from '@wordpress/icons';
import { prependHTTPS } from '@wordpress/url';

import { ArtisanPackLinkControl } from '../../dynamic-content/link-control';
import { TEXT_DOMAIN } from '../../vendor/i18n';

const NEW_TAB_TARGET = '_blank';
const NEW_TAB_REL_TOKENS: readonly string[] = [ 'noreferrer', 'noopener' ];
const NOFOLLOW_REL = 'nofollow';

export interface LinkToolbarValue {
    readonly url: string;
    readonly linkTarget: string | undefined;
    readonly rel: string | undefined;
}

export interface LinkToolbarControlProps {
    readonly url?: string;
    readonly linkTarget?: string;
    readonly rel?: string;
    readonly onChange: ( next: LinkToolbarValue ) => void;
    readonly onRemove: () => void;
}

interface LinkControlChange {
    readonly url?: string;
    readonly opensInNewTab?: boolean;
    readonly nofollow?: boolean;
}

/**
 * Whether a `rel` string carries the `nofollow` token.
 *
 * @since 1.18.0
 */
export function hasNofollow( rel: string | undefined ): boolean {
    return ( rel ?? '' ).split( /\s+/ ).includes( NOFOLLOW_REL );
}

/**
 * Resolve a LinkControl change into the normalized link triple. Author
 * `rel` tokens are kept; the new-tab (`noreferrer noopener`) and
 * `nofollow` tokens are owned by the two toggles and re-derived from
 * them on every change.
 *
 * @since 1.18.0
 */
export function resolveLinkChange(
    change: LinkControlChange,
    currentRel: string | undefined
): LinkToolbarValue {
    const managed = new Set( [ ...NEW_TAB_REL_TOKENS, NOFOLLOW_REL ] );
    const tokens = new Set(
        ( currentRel ?? '' )
            .split( /\s+/ )
            .filter( ( token ) => '' !== token && ! managed.has( token ) )
    );

    if ( change.opensInNewTab ) {
        NEW_TAB_REL_TOKENS.forEach( ( token ) => tokens.add( token ) );
    }

    if ( change.nofollow ) {
        tokens.add( NOFOLLOW_REL );
    }

    const rawUrl = ( change.url ?? '' ).trim();
    const rel = Array.from( tokens ).join( ' ' );

    return {
        // Dynamic Content hrefs (`{{token}}`) are resolved at render time;
        // prefixing a scheme would break the resolved URL.
        url: rawUrl.startsWith( '{{' ) ? rawUrl : prependHTTPS( rawUrl ),
        linkTarget: change.opensInNewTab ? NEW_TAB_TARGET : undefined,
        rel: rel || undefined,
    };
}

export default function LinkToolbarControl( {
    url,
    linkTarget,
    rel,
    onChange,
    onRemove,
}: LinkToolbarControlProps ): ReactElement {
    const [ isOpen, setIsOpen ] = useState( false );
    const anchorRef = useRef< HTMLSpanElement | null >( null );
    const hasUrl = !! url;

    const remove = (): void => {
        onRemove();
        setIsOpen( false );
    };

    return (
        <>
            <BlockControls group="block">
                <ToolbarButton
                    // @ts-expect-error - upstream prop
                    name="link"
                    icon={ linkIcon }
                    title={ __( 'Link', TEXT_DOMAIN ) }
                    onClick={ () => setIsOpen( true ) }
                    isActive={ hasUrl }
                />
                { hasUrl && (
                    <ToolbarButton
                        // @ts-expect-error - upstream prop
                        name="unlink"
                        icon={ linkOffIcon }
                        title={ __( 'Unlink', TEXT_DOMAIN ) }
                        onClick={ remove }
                    />
                ) }
            </BlockControls>
            <span
                ref={ anchorRef }
                style={ { position: 'absolute' } }
                aria-hidden="true"
            />
            { isOpen && (
                <Popover
                    placement="bottom"
                    onClose={ () => setIsOpen( false ) }
                    anchor={ anchorRef.current }
                    focusOnMount={ 'firstElement' as unknown as boolean }
                    shift
                >
                    <ArtisanPackLinkControl
                        value={ {
                            url: url ?? '',
                            opensInNewTab: '_blank' === linkTarget,
                            nofollow: hasNofollow( rel ),
                        } }
                        settings={ [
                            { id: 'opensInNewTab', title: __( 'Open in new tab', TEXT_DOMAIN ) },
                            { id: 'nofollow', title: __( 'Mark as nofollow', TEXT_DOMAIN ) },
                        ] }
                        onChange={ ( next ) => onChange( resolveLinkChange( next, rel ) ) }
                        onRemove={ remove }
                        forceIsEditingLink={ ! hasUrl }
                    />
                </Popover>
            ) }
        </>
    );
}
