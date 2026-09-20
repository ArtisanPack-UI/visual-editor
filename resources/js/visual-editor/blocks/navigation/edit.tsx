/**
 * Navigation — edit component.
 *
 * When the block has a `ref` (a resolved `wp_navigation` post id) delegates
 * to the registered `core/navigation` edit so the fork inherits the
 * upstream + V1 editor surface untouched (see
 * `../_shared/forked-entity-edit.tsx`). Phase I5 entity cluster (#413).
 *
 * When the block has no `ref` AND no uncontrolled inner blocks (#797)
 * upstream renders nothing user-visible — its default placeholder gate at
 * `edit/index.mjs:832` requires a `customPlaceholder` AND every one of
 * `hasResolvedNavigationMenus`, `classicMenus?.length === 0`,
 * `!hasUncontrolledInnerBlocks` to clear through the shim. Short-circuit
 * into a first-party picker that lists `wp_navigation` records and
 * creates a new one on demand so the block is addressable end-to-end.
 *
 * When the block has no `ref` but DOES carry uncontrolled inner blocks
 * (an unsaved menu being authored inline, or a paste of legacy markup),
 * upstream's edit stays authoritative — bypassing it would hide those
 * children and lose the user's in-progress work.
 */

import { type ComponentType } from 'react';
import { store as blockEditorStore, useBlockEditingMode } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

import { createForkedEntityEdit } from '../_shared/forked-entity-edit';
import NavigationInlinePlaceholder from './custom-placeholder';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyProps = Record<string, any>;

const ForkedNavigationEdit = createForkedEntityEdit( 'core/navigation' );

const NavigationEdit: ComponentType<AnyProps> = ( props ) => {
    const ref = props?.attributes?.ref;
    const clientId = props?.clientId;

    const hasUncontrolledInnerBlocks = useSelect(
        ( select ) => {
            if ( typeof clientId !== 'string' || clientId === '' ) {
                return false;
            }

            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            const store = select( blockEditorStore ) as any;
            const block = store?.getBlock?.( clientId );
            const innerBlocks = block?.innerBlocks;

            return Array.isArray( innerBlocks ) && innerBlocks.length > 0;
        },
        [ clientId ],
    );

    // #808 H2/H3/H6 — DELETE ONCE RESOLVED.
    //   H2: is the parent marked as controlling its inner blocks?
    //   H3: is block editing mode `default` (not `contentOnly` / `disabled`)?
    //   H6: can upstream actually insert navigation-* children here?
    const diag = useSelect(
        ( select ) => {
            if ( typeof clientId !== 'string' || clientId === '' ) {
                return null;
            }
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            const store = select( blockEditorStore ) as any;
            return {
                areInnerBlocksControlled:
                    typeof store?.areInnerBlocksControlled === 'function'
                        ? store.areInnerBlocksControlled( clientId )
                        : 'selector-missing',
                blockListSettings:
                    typeof store?.getBlockListSettings === 'function'
                        ? store.getBlockListSettings( clientId )
                        : 'selector-missing',
                innerBlockCount:
                    typeof store?.getBlockOrder === 'function'
                        ? ( store.getBlockOrder( clientId ) || [] ).length
                        : 'selector-missing',
                canInsertNavLink:
                    typeof store?.canInsertBlockType === 'function'
                        ? store.canInsertBlockType( 'core/navigation-link', clientId )
                        : 'selector-missing',
                canInsertNavSubmenu:
                    typeof store?.canInsertBlockType === 'function'
                        ? store.canInsertBlockType( 'core/navigation-submenu', clientId )
                        : 'selector-missing',
                canInsertPageList:
                    typeof store?.canInsertBlockType === 'function'
                        ? store.canInsertBlockType( 'core/page-list', clientId )
                        : 'selector-missing',
                selectedBlockClientId:
                    typeof store?.getSelectedBlockClientId === 'function'
                        ? store.getSelectedBlockClientId()
                        : 'selector-missing',
            };
        },
        [ clientId ],
    );
    const h3EditingMode = useBlockEditingMode();
    if ( typeof window !== 'undefined' ) {
        // eslint-disable-next-line no-console
        console.log(
            '[AP #808 nav-diag]',
            JSON.stringify(
                {
                    clientId,
                    ref,
                    hasUncontrolledInnerBlocks,
                    blockEditingMode: h3EditingMode,
                    ...diag,
                },
                ( _key, value ) => {
                    if ( typeof value === 'function' ) return '[Function]';
                    return value;
                },
                2,
            ),
        );
    }

    // Global click probe — install once. Logs clicks on any element
    // whose ancestry includes an element with a data-block clientId
    // matching this fork's clientId. That tells us whether the click
    // even reaches DOM handlers.
    if (
        typeof window !== 'undefined' &&
        typeof clientId === 'string' &&
        clientId !== '' &&
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        ! ( window as any ).__apNavClickProbeInstalled
    ) {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        ( window as any ).__apNavClickProbeInstalled = true;
        const logClick = ( event: Event ) => {
            const target = event.target as HTMLElement | null;
            if ( ! target ) return;
            // eslint-disable-next-line no-console
            console.log( '[AP #808 nav-click]', {
                inIframe: target.ownerDocument !== document,
                tag: target.tagName,
                ariaLabel: target.getAttribute?.( 'aria-label' ),
                textContent: target.textContent?.slice( 0, 60 ),
                classList: target.className,
                closestButton: target.closest?.( 'button' )?.outerHTML?.slice( 0, 240 ),
            } );
        };
        // Attach both to top document and to any block-editor iframes.
        document.addEventListener( 'click', logClick, true );
        // Attach to iframes as they mount.
        const attachToIframes = () => {
            document.querySelectorAll( 'iframe' ).forEach( ( iframe ) => {
                try {
                    const doc = ( iframe as HTMLIFrameElement ).contentDocument;
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    if ( doc && ! ( doc as any ).__apNavClickAttached ) {
                        // eslint-disable-next-line @typescript-eslint/no-explicit-any
                        ( doc as any ).__apNavClickAttached = true;
                        doc.addEventListener( 'click', logClick, true );
                    }
                } catch {
                    // cross-origin iframe — skip
                }
            } );
        };
        attachToIframes();
        // Re-scan after render frames since iframes may mount later.
        setTimeout( attachToIframes, 500 );
        setTimeout( attachToIframes, 2000 );
    }

    if ( ! ref && ! hasUncontrolledInnerBlocks ) {
        return (
            <NavigationInlinePlaceholder
                attributes={ props.attributes }
                setAttributes={ props.setAttributes }
            />
        );
    }

    return <ForkedNavigationEdit { ...props } />;
};

NavigationEdit.displayName = 'ForkedNavigationEdit(core/navigation)';

export default NavigationEdit;
