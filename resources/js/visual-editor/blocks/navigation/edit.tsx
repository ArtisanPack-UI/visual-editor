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
import { store as blockEditorStore } from '@wordpress/block-editor';
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
