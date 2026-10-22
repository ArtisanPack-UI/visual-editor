/**
 * Search form action inspector control (#961).
 *
 * Shared by `artisanpack/search` and `artisanpack/search-filters`, whose
 * `action` attribute picks where the rendered `<form method="get">`
 * submits:
 *
 *  - `''` (default) — the site search URL (`visual-editor.search.url`);
 *  - `'current'` — the page the form is rendered on, minus its query
 *    string and pagination;
 *  - a same-site path starting with `/` — anything else is ignored by the
 *    renderers, which fall back to the site search URL.
 *
 * The path check mirrors `SearchForm::sameSitePath()` in the Blade
 * renderer and `sameSitePath()` in the React / Vue renderers. It is
 * duplicated rather than imported because
 * `resources/js/visual-editor/blocks/**` must not depend on
 * `packages/visual-editor-renderer-*`.
 */

import type { ReactElement } from 'react';
import { SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

export const SEARCH_ACTION_CURRENT = 'current';

export type SearchActionMode = 'site' | 'current' | 'custom';

/**
 * Which option the stored `action` value corresponds to.
 */
export function searchActionMode( action: unknown ): SearchActionMode {
    if ( typeof action !== 'string' || action.trim() === '' ) {
        return 'site';
    }

    return action.trim() === SEARCH_ACTION_CURRENT ? 'current' : 'custom';
}

/**
 * Whether `path` is a same-site relative path the renderers will honour.
 */
export function isSameSitePath( path: string ): boolean {
    const trimmed = path.trim();

    if ( ! trimmed.startsWith( '/' ) || trimmed.startsWith( '//' ) ) {
        return false;
    }

    // eslint-disable-next-line no-control-regex
    return ! trimmed.includes( '\\' ) && ! /[\x00-\x20\x7F]/.test( trimmed );
}

interface SearchFormActionControlProps {
    readonly action: string | undefined;
    readonly onChange: ( action: string ) => void;
}

export default function SearchFormActionControl( {
    action,
    onChange,
}: SearchFormActionControlProps ): ReactElement {
    const mode = searchActionMode( action );
    const path = typeof action === 'string' ? action : '';

    const onModeChange = ( next: string ): void => {
        if ( 'current' === next ) {
            onChange( SEARCH_ACTION_CURRENT );
        } else if ( 'custom' === next ) {
            // Seed a path so the stored value stays distinct from the
            // "Site search" default while the author types.
            onChange( 'custom' === mode ? path : '/' );
        } else {
            onChange( '' );
        }
    };

    return (
        <>
            <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={ __( 'Submit to', TEXT_DOMAIN ) }
                value={ mode }
                options={ [
                    { value: 'site', label: __( 'Site search', TEXT_DOMAIN ) },
                    { value: 'current', label: __( 'This page', TEXT_DOMAIN ) },
                    { value: 'custom', label: __( 'Custom path', TEXT_DOMAIN ) },
                ] }
                onChange={ onModeChange }
                help={
                    'current' === mode
                        ? __(
                            'Searches reload the page the form is on, so an archive can filter its own results.',
                            TEXT_DOMAIN
                        )
                        : __( 'Where the search form sends visitors.', TEXT_DOMAIN )
                }
            />
            { 'custom' === mode && (
                <TextControl
                    __next40pxDefaultSize
                    __nextHasNoMarginBottom
                    label={ __( 'Path', TEXT_DOMAIN ) }
                    value={ path }
                    placeholder="/blog"
                    onChange={ ( value: string ) => onChange( '' === value.trim() ? '/' : value ) }
                    help={
                        isSameSitePath( path )
                            ? __( 'A path on this site, starting with /.', TEXT_DOMAIN )
                            : __(
                                'Only paths on this site starting with / are used; anything else submits to the site search.',
                                TEXT_DOMAIN
                            )
                    }
                />
            ) }
        </>
    );
}
