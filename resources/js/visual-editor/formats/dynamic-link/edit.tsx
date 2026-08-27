/**
 * Dynamic-Content-aware link format — toolbar button + popover (#662).
 *
 * Replaces the built-in `core/link` edit. The toolbar Link button opens a
 * popover whose body is {@link ArtisanPackLinkControl} — the stock
 * `LinkControl` plus a "Dynamic Content" tab. Picking a field writes a
 * scheme-appropriate raw-token href (`mailto:{{…}}` / `tel:{{…}}` /
 * `{{…}}`) as a `core/link` format, which the SSR resolver rewrites at
 * render. Typing a normal URL in the Link tab behaves exactly as before.
 *
 * The heavy URL-suggestion UX is delegated to the stock `LinkControl`
 * inside the wrapper; this component only owns anchoring, the mod+k
 * shortcut, and applying/removing the `core/link` format across the
 * selection (or the active link's boundary when the caret is collapsed
 * inside it).
 *
 * @since 1.7.0
 */

import type { ReactElement, RefObject } from 'react';
import { RichTextShortcut, RichTextToolbarButton } from '@wordpress/block-editor';
import { Popover } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { link as linkIcon, linkOff as linkOffIcon } from '@wordpress/icons';
import { applyFormat, insert, isCollapsed, removeFormat, useAnchor } from '@wordpress/rich-text';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { ArtisanPackLinkControl, type LinkControlValue } from '../../dynamic-content/link-control';

import { LINK_FORMAT_NAME } from './constants';
import { activeLinkRange, buildLinkFormat, type RichTextValueLike } from './value';

const ANCHOR_SETTINGS = {
    name: LINK_FORMAT_NAME,
    tagName: 'a',
    className: null,
} as const;

interface FormatEditProps {
    readonly value: RichTextValueLike;
    readonly onChange: ( value: RichTextValueLike ) => void;
    readonly isActive?: boolean;
    readonly activeAttributes?: Record< string, string >;
    readonly contentRef?: RefObject< HTMLElement >;
}

export function DynamicLinkEdit( props: FormatEditProps ): ReactElement {
    const { value, onChange, isActive, activeAttributes, contentRef } = props;

    const [ isOpen, setIsOpen ] = useState( false );

    // Close the popover when the caret leaves an active link so a stale
    // editor doesn't linger over unlinked text.
    useEffect( () => {
        if ( ! isActive ) {
            setIsOpen( false );
        }
    }, [ isActive ] );

    const popoverAnchor = useAnchor( {
        editableContentElement: contentRef?.current ?? null,
        settings: ANCHOR_SETTINGS as never,
    } );

    const applyLink = ( next: LinkControlValue ): void => {
        const linkFormat = buildLinkFormat( next );
        const start      = value.start ?? 0;
        const end        = value.end ?? start;

        let nextValue: RichTextValueLike;

        if ( isActive ) {
            // Editing an existing link — retarget the whole link run even
            // if the caret is collapsed inside it.
            const range = activeLinkRange( value );
            nextValue = range
                ? ( applyFormat( value as never, linkFormat as never, range[ 0 ], range[ 1 ] ) as RichTextValueLike )
                : ( applyFormat( value as never, linkFormat as never, start, end ) as RichTextValueLike );
        } else if ( isCollapsed( value as never ) ) {
            // No selection — insert the href as visible text, then link it
            // (mirrors the stock link format's collapsed-caret behavior).
            const text     = next.url ?? '';
            const inserted = insert( value as never, text ) as RichTextValueLike;
            nextValue = applyFormat(
                inserted as never,
                linkFormat as never,
                start,
                start + text.length
            ) as RichTextValueLike;
        } else {
            nextValue = applyFormat( value as never, linkFormat as never, start, end ) as RichTextValueLike;
        }

        onChange( nextValue );
        setIsOpen( false );
    };

    const removeLink = (): void => {
        const range = activeLinkRange( value );
        const start = range ? range[ 0 ] : value.start ?? 0;
        const end   = range ? range[ 1 ] : value.end ?? start;
        onChange( removeFormat( value as never, LINK_FORMAT_NAME, start, end ) as RichTextValueLike );
        setIsOpen( false );
    };

    return (
        <>
            <RichTextShortcut type="primary" character="k" onUse={ () => setIsOpen( true ) } />
            <RichTextShortcut type="primaryShift" character="k" onUse={ removeLink } />

            <RichTextToolbarButton
                icon={ linkIcon }
                title={ __( 'Link', TEXT_DOMAIN ) }
                onClick={ () => setIsOpen( true ) }
                isActive={ !! isActive }
                shortcutType="primary"
                shortcutCharacter="k"
                role="menuitemcheckbox"
            />

            { isActive && (
                <RichTextToolbarButton
                    icon={ linkOffIcon }
                    title={ __( 'Unlink', TEXT_DOMAIN ) }
                    onClick={ removeLink }
                    isActive={ false }
                    shortcutType="primaryShift"
                    shortcutCharacter="k"
                    role="menuitemcheckbox"
                />
            ) }

            { isOpen && (
                <Popover
                    anchor={ popoverAnchor }
                    onClose={ () => setIsOpen( false ) }
                    focusOnMount={ 'firstElement' as unknown as boolean }
                    placement="bottom"
                    shift
                >
                    <ArtisanPackLinkControl
                        value={ {
                            url: activeAttributes?.url ?? '',
                            opensInNewTab: '_blank' === activeAttributes?.target,
                        } }
                        onChange={ applyLink }
                        onRemove={ removeLink }
                        forceIsEditingLink={ ! isActive }
                    />
                </Popover>
            ) }
        </>
    );
}

export default DynamicLinkEdit;
