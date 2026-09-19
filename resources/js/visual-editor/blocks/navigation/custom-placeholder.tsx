/**
 * Navigation — first-party placeholder (#797).
 *
 * Upstream `core/navigation` renders nothing in the canvas when the block
 * has no `ref` unless every one of a chain of internal conditions
 * (`hasResolvedNavigationMenus`, `classicMenus?.length === 0`,
 * `!hasUncontrolledInnerBlocks`, …) is satisfied — and its
 * `MenuInspectorControls` live in the `list` inspector group (List View),
 * not the block Settings sidebar. Fresh navigation blocks therefore
 * mount as an empty `<nav>` with no user-visible way to pick or create
 * a menu.
 *
 * Rather than chasing every upstream gate through the shim, this
 * placeholder short-circuits the upstream edit whenever `attributes.ref`
 * is empty: it renders a first-party picker (existing menus, list from
 * `useEntityRecords('postType', 'wp_navigation', ...)`) and a "Create
 * menu" button that dispatches `saveEntityRecord('postType',
 * 'wp_navigation', { title, content, status: 'publish' })` through the
 * core-data shim, hitting the H6 `POST /visual-editor/api/menus`
 * surface fixed for the Gutenberg payload shape in the sibling PHP
 * change. Once `ref` is set the block re-renders through the upstream
 * `core/navigation` edit unchanged.
 */

import { useMemo, useState } from 'react';
import { Button, Placeholder, SelectControl, Spinner } from '@wordpress/components';
import { useBlockProps } from '@wordpress/block-editor';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import { useEntityRecords } from '../../vendor/core-data-shim';

interface NavigationMenuRecord {
    id: number | string;
    status?: string;
    title?: { raw?: string; rendered?: string } | string;
    slug?: string;
}

// Only the fields we actually read off the Navigation edit's props. Kept
// permissive — upstream ships a much wider prop set that we forward via
// `{ ...props }` in the wrapper.
export interface NavigationPlaceholderProps {
    attributes: { ref?: number | string | null } & Record<string, unknown>;
    setAttributes: ( attrs: { ref?: number | string | null } ) => void;
}

function menuLabel( record: NavigationMenuRecord ): string {
    const { title } = record;

    if ( typeof title === 'string' ) {
        return title;
    }

    if ( title && typeof title === 'object' ) {
        if ( typeof title.raw === 'string' && title.raw !== '' ) {
            return title.raw;
        }

        if ( typeof title.rendered === 'string' && title.rendered !== '' ) {
            return title.rendered;
        }
    }

    return record.slug ?? __( 'Untitled menu' );
}

export default function NavigationInlinePlaceholder( {
    attributes,
    setAttributes,
}: NavigationPlaceholderProps ): JSX.Element {
    // Mirrors upstream `PRELOADED_NAVIGATION_MENUS_QUERY` so the read
    // shares its cache with `useNavigationMenu`.
    const { records, isResolving, hasResolved } = useEntityRecords<NavigationMenuRecord>(
        'postType',
        'wp_navigation',
        { per_page: -1, status: [ 'publish', 'draft' ], order: 'desc', orderby: 'date' },
    );

    const menus = useMemo(
        () =>
            ( records ?? [] ).filter(
                ( menu ) => ! menu.status || menu.status === 'publish' || menu.status === 'draft',
            ),
        [ records ],
    );

    const [ selectedId, setSelectedId ] = useState<string>( '' );
    const [ isCreating, setIsCreating ] = useState<boolean>( false );

    // `useDispatch( 'core' )` binds to whichever store is registered
    // under that name — the shim registers itself there so this hits
    // our `saveEntityRecord` action (see `vendor/core-data-shim.ts`).
    const { saveEntityRecord } = useDispatch( 'core' ) as unknown as {
        saveEntityRecord: (
            kind: string,
            name: string,
            record: Record<string, unknown>,
        ) => Promise<{ id?: number | string } | null>;
    };

    const options = useMemo(
        () => [
            { value: '', label: __( 'Select a menu…' ) },
            ...menus.map( ( menu ) => ( {
                value: String( menu.id ),
                label: menuLabel( menu ),
            } ) ),
        ],
        [ menus ],
    );

    const isLoading = ! hasResolved || isResolving;

    async function handleCreate(): Promise<void> {
        if ( isCreating ) {
            return;
        }

        setIsCreating( true );

        try {
            const saved = await saveEntityRecord( 'postType', 'wp_navigation', {
                title: __( 'Navigation' ),
                content: '',
                status: 'publish',
            } );

            if ( saved?.id ) {
                setAttributes( { ref: saved.id } );
            }
        } finally {
            setIsCreating( false );
        }
    }

    // Keep the block's normal wrapper so selection, block-editing mode
    // outlines, and layout classnames still work while the picker is
    // showing.
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    void attributes;

    return (
        <nav { ...blockProps }>
            <Placeholder
                className="wp-block-navigation-placeholder"
                icon="menu"
                label={ __( 'Navigation' ) }
                instructions={ __(
                    'Choose an existing menu or create a new one to get started.',
                ) }
            >
                <div className="wp-block-navigation-placeholder__actions">
                    { isLoading && <Spinner /> }
                    { ! isLoading && menus.length > 0 && (
                        <SelectControl
                            __nextHasNoMarginBottom
                            __next40pxDefaultSize
                            label={ __( 'Existing menus' ) }
                            hideLabelFromVision
                            value={ selectedId }
                            options={ options }
                            onChange={ ( value ) => {
                                setSelectedId( value );

                                if ( value !== '' ) {
                                    const parsed = Number( value );
                                    setAttributes( {
                                        ref: Number.isNaN( parsed ) ? value : parsed,
                                    } );
                                }
                            } }
                        />
                    ) }
                    { ! isLoading && menus.length === 0 && (
                        <p>{ __( 'No menus yet — create one to start.' ) }</p>
                    ) }
                    <Button
                        __next40pxDefaultSize
                        variant="primary"
                        isBusy={ isCreating }
                        disabled={ isCreating }
                        onClick={ handleCreate }
                    >
                        { __( 'Create menu' ) }
                    </Button>
                </div>
            </Placeholder>
        </nav>
    );
}
