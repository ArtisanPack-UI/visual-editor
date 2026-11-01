/**
 * Query Loop — taxonomy and term filter (#1029).
 *
 * Lists the taxonomies registered for the selected post type and the
 * chosen taxonomy's terms, then writes the selection to `query.taxQuery`
 * as `{ taxonomy, terms, operator: 'IN' }`.
 *
 * `taxQuery` stays `null` until at least one term is picked, because the
 * resolve endpoint rejects a `taxQuery` without terms. A taxonomy chosen
 * with no terms yet is held in local state instead.
 *
 * @since 1.20.0
 */

import type { ReactElement } from 'react';
import { useState } from 'react';
import { CheckboxControl, Notice, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { getTaxonomiesForPostType } from '../../editor/taxonomy-registry';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import { useTaxonomyTerms } from './use-taxonomy-terms';

import './taxonomy-filter.css';

export interface QueryTaxQuery {
    taxonomy: string;
    terms: number[];
    operator: 'IN';
}

interface TaxonomyFilterProps {
    postType: string;
    taxQuery: unknown;
    onChange: ( taxQuery: QueryTaxQuery | null ) => void;
}

/**
 * Reads a stored `taxQuery`, or returns `null` for anything other than a
 * taxonomy with at least one term id. That includes the `''` placeholder
 * and the upstream `{ category: [ 1 ] }` shape, which this filter doesn't
 * write.
 *
 * @since 1.20.0
 */
export function readTaxQuery( value: unknown ): QueryTaxQuery | null {
    if ( value === null || typeof value !== 'object' ) {
        return null;
    }

    const { taxonomy, terms } = value as { taxonomy?: unknown; terms?: unknown };

    if ( typeof taxonomy !== 'string' || taxonomy === '' || ! Array.isArray( terms ) ) {
        return null;
    }

    // Numeric strings count: the server accepts them, so they still filter.
    const ids = terms.map( Number ).filter( ( id ) => Number.isInteger( id ) && id > 0 );

    return ids.length > 0 ? { taxonomy, terms: ids, operator: 'IN' } : null;
}

/**
 * Whether `taxonomy` is registered for `postType`.
 *
 * @since 1.20.0
 */
export function isTaxonomyAvailable( taxonomy: string, postType: string ): boolean {
    return getTaxonomiesForPostType( postType ).some( ( entry ) => entry.slug === taxonomy );
}

export default function TaxonomyFilter( {
    postType,
    taxQuery,
    onChange,
}: TaxonomyFilterProps ): ReactElement | null {
    const stored = readTaxQuery( taxQuery );
    const [ pendingTaxonomy, setPendingTaxonomy ] = useState( '' );

    const taxonomies = getTaxonomiesForPostType( postType );
    const taxonomy = stored?.taxonomy
        ?? ( isTaxonomyAvailable( pendingTaxonomy, postType ) ? pendingTaxonomy : '' );
    const selected = stored?.terms ?? [];
    const { status, terms } = useTaxonomyTerms( taxonomy );

    if ( taxonomies.length === 0 && stored === null ) {
        return null;
    }

    const options = [
        { label: __( 'All', TEXT_DOMAIN ), value: '' },
        ...taxonomies.map( ( entry ) => ( { label: entry.plural, value: entry.slug } ) ),
    ];

    if ( taxonomy !== '' && ! options.some( ( option ) => option.value === taxonomy ) ) {
        options.push( {
            /* translators: %s: taxonomy slug saved on the block that is not in the picker's list. */
            label: sprintf( __( '%s (unavailable)', TEXT_DOMAIN ), taxonomy ),
            value: taxonomy,
        } );
    }

    const changeTaxonomy = ( value: string ): void => {
        setPendingTaxonomy( value );

        if ( stored !== null ) {
            onChange( null );
        }
    };

    const toggleTerm = ( id: number, checked: boolean ): void => {
        const next = checked
            ? [ ...selected, id ]
            : selected.filter( ( termId ) => termId !== id );

        if ( next.length === 0 ) {
            setPendingTaxonomy( taxonomy );
            onChange( null );
            return;
        }

        onChange( { taxonomy, terms: next, operator: 'IN' } );
    };

    // A selected term that's since been deleted still filters the query,
    // so it stays listed where the author can remove it.
    const missingTerms = status === 'ready'
        ? selected
            .filter( ( id ) => ! terms.some( ( term ) => term.id === id ) )
            .map( ( id ) => ( {
                id,
                /* translators: %d: id of a term saved on the block that no longer exists. */
                name: sprintf( __( 'Term #%d (unavailable)', TEXT_DOMAIN ), id ),
            } ) )
        : [];
    const listedTerms = [ ...terms, ...missingTerms ];

    return (
        <div className="ap-query-taxonomy-filter">
            <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={ __( 'Taxonomy', TEXT_DOMAIN ) }
                value={ taxonomy }
                options={ options }
                onChange={ changeTaxonomy }
            />
            { status === 'loading' && (
                <p className="ap-query-taxonomy-filter__status" role="status">
                    { __( 'Loading terms…', TEXT_DOMAIN ) }
                </p>
            ) }
            { status === 'error' && (
                <Notice status="error" isDismissible={ false }>
                    { __( 'Terms could not be loaded.', TEXT_DOMAIN ) }
                </Notice>
            ) }
            { status === 'ready' && listedTerms.length === 0 && (
                <p className="ap-query-taxonomy-filter__status">
                    { __( 'No terms found.', TEXT_DOMAIN ) }
                </p>
            ) }
            { listedTerms.length > 0 && (
                <fieldset className="ap-query-taxonomy-filter__terms">
                    <legend className="ap-query-taxonomy-filter__legend">
                        { __( 'Terms', TEXT_DOMAIN ) }
                    </legend>
                    <div className="ap-query-taxonomy-filter__list">
                        { listedTerms.map( ( term ) => (
                            <CheckboxControl
                                __nextHasNoMarginBottom
                                key={ term.id }
                                label={ term.name }
                                checked={ selected.includes( term.id ) }
                                onChange={ ( checked: boolean ) => toggleTerm( term.id, checked ) }
                            />
                        ) ) }
                    </div>
                </fieldset>
            ) }
        </div>
    );
}
