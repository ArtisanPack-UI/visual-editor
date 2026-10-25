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
 * The rel / URL normalization lives in `./link-attributes`.
 *
 * @since 1.18.0
 */

import type { ReactElement } from 'react';
import { BlockControls } from '@wordpress/block-editor';
import { Popover, ToolbarButton } from '@wordpress/components';
import { useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { link as linkIcon, linkOff as linkOffIcon } from '@wordpress/icons';

import { ArtisanPackLinkControl } from '../../dynamic-content/link-control';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import { NEW_TAB_TARGET, hasNofollow, resolveLinkChange, type ResolvedLink } from './link-attributes';

export { hasNofollow, resolveLinkChange } from './link-attributes';

export type LinkToolbarValue = ResolvedLink;

export interface LinkToolbarControlProps {
    readonly url?: string;
    readonly linkTarget?: string;
    readonly rel?: string;
    readonly onChange: ( next: LinkToolbarValue ) => void;
    readonly onRemove: () => void;
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
                            opensInNewTab: NEW_TAB_TARGET === linkTarget,
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
