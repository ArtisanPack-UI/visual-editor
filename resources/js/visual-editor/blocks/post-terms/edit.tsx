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
 * Phase I-Block-Fork — post navigation / metadata family (#520).
 */

import {
    createEntityPlaceholderEdit,
    PREVIEW_CONTEXT_KEY,
    type EntityPreviewValue,
} from '../_shared/entity-placeholder-edit';
import type { QueryPreviewPost } from '../../editor/use-query-preview';

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

export default function PostTermsEdit( props: {
    attributes?: PostTermsAttributes;
    context?: unknown;
} ): ReturnType<typeof PlaceholderEdit > {
    const attributes = props.attributes ?? {};
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

    if ( label === '' ) {
        // No resolved data and no preview context — fall through to the
        // labelled chip placeholder from createEntityPlaceholderEdit.
        return PlaceholderEdit( props );
    }

    const synthesizedAttributes: PostTermsAttributes & EntityPreviewValue = {
        ...attributes,
        _resolvedTermsLabel: label,
    };

    return PlaceholderEdit( { ...props, attributes: synthesizedAttributes } );
}
