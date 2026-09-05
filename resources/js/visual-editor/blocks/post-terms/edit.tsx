/**
 * Post Terms — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from the stamped `_resolvedTermsByTaxonomy`
 * map combined with the block's own `term` / `separator` / `prefix` /
 * `suffix` attributes.
 *
 * The fork previews through a thin wrapper around `createEntityPlaceholderEdit`
 * that synthesizes a `_resolvedTermsLabel` preview value from the resolved
 * terms map (front-end / saved-tree path) or from the `artisanpack/postPreview`
 * query-loop context (#483) when no `_resolvedTermsByTaxonomy` is stamped yet.
 * The live page-entity fetch path is intentionally absent because the
 * block-level extractors don't have access to the per-block `term` attribute
 * needed to pick the right taxonomy from the entity's `_preview.terms`.
 *
 * `InspectorControls` (#771) surfaces a taxonomy picker — populated from
 * the {@see getTaxonomies} runtime registry — alongside the `separator` /
 * `prefix` / `suffix` attributes, so authors can bind the block to a
 * taxonomy and tune its display without hand-editing markup. Paired with
 * the per-taxonomy inserter variations (`./variations`), this closes the
 * gap where `term` was previously only settable in the code editor.
 *
 * Phase I-Block-Fork — post navigation / metadata family (#520).
 */

import type { ReactElement } from 'react';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
    createEntityPlaceholderEdit,
    PREVIEW_CONTEXT_KEY,
    type EntityPreviewValue,
} from '../_shared/entity-placeholder-edit';
import type { QueryPreviewPost } from '../../editor/use-query-preview';
import { getTaxonomies } from '../../editor/taxonomy-registry';
import { TEXT_DOMAIN } from '../../vendor/i18n';

interface TermReference {
    readonly name?: string;
    readonly url?: string;
}

interface PostTermsAttributes {
    readonly term?: string;
    readonly separator?: string;
    readonly prefix?: string;
    readonly suffix?: string;
    readonly _resolvedTermsByTaxonomy?: unknown;
    readonly _resolvedTermsLabel?: string;
}

function joinTerms(
    terms: ReadonlyArray<TermReference>,
    separator: string,
    prefix: string,
    suffix: string,
): string {
    const names = terms
        .map( ( term ) => ( typeof term.name === 'string' ? term.name : '' ) )
        .filter( ( name ) => name !== '' );

    if ( names.length === 0 ) {
        return '';
    }

    return `${ prefix }${ names.join( separator ) }${ suffix }`;
}

function readTaxonomyMap(
    value: unknown,
    taxonomy: string,
): ReadonlyArray<TermReference> {
    if ( value === null || typeof value !== 'object' || taxonomy === '' ) {
        return [];
    }

    const entry = ( value as Record<string, unknown> )[ taxonomy ];

    if ( ! Array.isArray( entry ) ) {
        return [];
    }

    return entry.filter(
        ( term ): term is TermReference =>
            term !== null && typeof term === 'object'
    );
}

function readQueryPreviewTerms(
    context: unknown,
    taxonomy: string,
): ReadonlyArray<TermReference> {
    if ( context === null || typeof context !== 'object' ) {
        return [];
    }

    const preview = ( context as Record<string, unknown> )[ PREVIEW_CONTEXT_KEY ];

    if ( preview === null || typeof preview !== 'object' ) {
        return [];
    }

    const terms = ( preview as QueryPreviewPost & { terms?: unknown } ).terms;

    return readTaxonomyMap( terms, taxonomy );
}

const PlaceholderEdit = createEntityPlaceholderEdit( {
    label: 'Post Terms',
    resolvedKey: '_resolvedTermsLabel',
    kind: 'text',
    // Authors editing a template with no post in scope (or no terms
    // resolved) get a representative dummy so the styled chip shape is
    // visible. Front-end render never sees this — only the editor does.
    dummyValue: { text: 'Category, Updates' },
} );

interface PostTermsEditProps {
    readonly attributes?: PostTermsAttributes;
    readonly setAttributes?: ( attrs: Partial<PostTermsAttributes> ) => void;
    readonly context?: unknown;
}

/**
 * Builds the taxonomy `SelectControl` options from the runtime registry.
 * A leading blank option lets an unbound block (`term: undefined`) render
 * a "no taxonomy" state instead of silently pinning the first taxonomy,
 * and a currently-set `term` that isn't in the registry is appended so
 * the control never drops the author's stored value.
 */
function taxonomyOptions(
    currentTerm: string,
): { label: string; value: string }[] {
    const options: { label: string; value: string }[] = [
        { label: __( 'Select a taxonomy…', TEXT_DOMAIN ), value: '' },
    ];

    let matched = false;
    for ( const taxonomy of getTaxonomies() ) {
        options.push( { label: taxonomy.label, value: taxonomy.slug } );
        if ( taxonomy.slug === currentTerm ) {
            matched = true;
        }
    }

    if ( currentTerm !== '' && ! matched ) {
        options.push( { label: currentTerm, value: currentTerm } );
    }

    return options;
}

export default function PostTermsEdit( props: PostTermsEditProps ): ReactElement {
    const attributes = props.attributes ?? {};
    const setAttributes = props.setAttributes;
    const taxonomy = typeof attributes.term === 'string' ? attributes.term : '';
    const separator =
        typeof attributes.separator === 'string' ? attributes.separator : ', ';
    const prefix = typeof attributes.prefix === 'string' ? attributes.prefix : '';
    const suffix = typeof attributes.suffix === 'string' ? attributes.suffix : '';

    // Pre-stamped map wins — matches the post-excerpt convention where
    // the saved-tree value is the canonical preview source.
    let label = '';
    const stampedMap = readTaxonomyMap( attributes._resolvedTermsByTaxonomy, taxonomy );
    if ( stampedMap.length > 0 ) {
        label = joinTerms( stampedMap, separator, prefix, suffix );
    }

    if ( label === '' ) {
        // Fall back to the query-loop preview context when inside a
        // resolved `artisanpack/query` block (#483).
        const previewTerms = readQueryPreviewTerms( props.context, taxonomy );
        if ( previewTerms.length > 0 ) {
            label = joinTerms( previewTerms, separator, prefix, suffix );
        }
    }

    // No resolved data and no preview context — fall through to the
    // labelled chip placeholder from createEntityPlaceholderEdit.
    // Otherwise synthesize the resolved label for the preview chip.
    const preview =
        label === ''
            ? PlaceholderEdit( props )
            : PlaceholderEdit( {
                  ...props,
                  attributes: {
                      ...attributes,
                      _resolvedTermsLabel: label,
                  } as PostTermsAttributes & EntityPreviewValue,
              } );

    return (
        <>
            { setAttributes !== undefined && (
                <InspectorControls>
                    <PanelBody title={ __( 'Settings', TEXT_DOMAIN ) }>
                        <SelectControl
                            // @ts-expect-error - upstream prop
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={ __( 'Taxonomy', TEXT_DOMAIN ) }
                            value={ taxonomy }
                            options={ taxonomyOptions( taxonomy ) }
                            onChange={ ( value: string ) =>
                                setAttributes( { term: value } )
                            }
                        />
                        <TextControl
                            // @ts-expect-error - upstream prop
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={ __( 'Separator', TEXT_DOMAIN ) }
                            value={ separator }
                            onChange={ ( value: string ) =>
                                setAttributes( { separator: value } )
                            }
                        />
                        <TextControl
                            // @ts-expect-error - upstream prop
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={ __( 'Prefix', TEXT_DOMAIN ) }
                            value={ prefix }
                            onChange={ ( value: string ) =>
                                setAttributes( { prefix: value } )
                            }
                        />
                        <TextControl
                            // @ts-expect-error - upstream prop
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={ __( 'Suffix', TEXT_DOMAIN ) }
                            value={ suffix }
                            onChange={ ( value: string ) =>
                                setAttributes( { suffix: value } )
                            }
                        />
                    </PanelBody>
                </InspectorControls>
            ) }
            { preview }
        </>
    );
}
