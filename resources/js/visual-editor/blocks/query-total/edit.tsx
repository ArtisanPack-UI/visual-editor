/**
 * QueryTotal — edit component.
 *
 * Server-rendered display block (#962). The real count is emitted by the
 * Blade / React / Vue renderers from the `_resolvedTotal` page snapshot
 * stamped by `QueryInliner`. In the canvas the count comes from the
 * `artisanpack/queryPreview` block context the surrounding query
 * provides (#599), falling back to a sample of 25 results, 9 per page,
 * so the line is visible and stylable before the resolver settles.
 *
 * The inspector picks the display type and edits the prefix, singular
 * and plural nouns, and suffix.
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { readQueryPreviewContext } from '../../editor/query-preview-context';
import { TEXT_DOMAIN } from '../../vendor/i18n';

type DisplayType = 'total-results' | 'range-display';

interface QueryTotalAttributes {
    displayType?: DisplayType;
    prefix?: string;
    singular?: string;
    plural?: string;
    suffix?: string;
}

interface QueryTotalEditProps {
    attributes: QueryTotalAttributes;
    setAttributes: ( changes: Partial<QueryTotalAttributes> ) => void;
    context?: Record<string, unknown>;
}

/** Sample count the canvas shows when no query preview has resolved. */
export const SAMPLE_TOTAL = 25;

/** Sample page size paired with {@see SAMPLE_TOTAL}. */
export const SAMPLE_PER_PAGE = 9;

export interface QueryTotalPreview {
    readonly total: number;
    readonly range: string | null;
    readonly noun: string;
}

/**
 * Build the preview count, range and noun with the same rules as the
 * front-end renderers (`query-total.blade.php`): the singular noun only
 * for a total of one, and no range for zero results.
 */
export function buildQueryTotalPreview(
    attributes: QueryTotalAttributes,
    total: number,
    currentPage: number,
    perPage: number,
    items: number | null
): QueryTotalPreview {
    const singular = typeof attributes.singular === 'string' ? attributes.singular : 'result';
    const plural = typeof attributes.plural === 'string' ? attributes.plural : 'results';
    const noun = 1 === total ? singular : plural;

    if ( 'range-display' !== ( attributes.displayType ?? 'total-results' ) || total <= 0 ) {
        return { total, range: null, noun };
    }

    const page = Math.max( 1, currentPage );
    const first = 0 === perPage ? 1 : Math.min( total, ( page - 1 ) * perPage + 1 );
    let last = 0 === perPage ? total : Math.min( total, page * perPage );

    if ( null !== items && items > 0 ) {
        last = Math.min( total, first + items - 1 );
    }

    return { total, range: first === last ? String( first ) : `${ first }–${ last }`, noun };
}

export default function QueryTotalEdit( {
    attributes,
    setAttributes,
    context,
}: QueryTotalEditProps ): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    const queryPreview = readQueryPreviewContext( context );
    const hasResolvedPreview = null !== queryPreview && 'ready' === queryPreview.status;

    const preview = hasResolvedPreview
        ? buildQueryTotalPreview(
            attributes,
            queryPreview.total,
            queryPreview.currentPage,
            queryPreview.perPage,
            queryPreview.posts.length
        )
        : buildQueryTotalPreview( attributes, SAMPLE_TOTAL, 1, SAMPLE_PER_PAGE, null );

    const prefix = attributes.prefix ?? '';
    const suffix = attributes.suffix ?? '';

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Settings', TEXT_DOMAIN ) }>
                    <SelectControl
                        label={ __( 'Display type', TEXT_DOMAIN ) }
                        value={ attributes.displayType ?? 'total-results' }
                        options={ [
                            { label: __( 'Total results', TEXT_DOMAIN ), value: 'total-results' },
                            { label: __( 'Range display', TEXT_DOMAIN ), value: 'range-display' },
                        ] }
                        onChange={ ( value: string ) =>
                            setAttributes( { displayType: value as DisplayType } )
                        }
                        __nextHasNoMarginBottom
                        __next40pxDefaultSize
                    />
                    <TextControl
                        label={ __( 'Prefix', TEXT_DOMAIN ) }
                        help={ __( 'Text before the number, e.g. "Showing ".', TEXT_DOMAIN ) }
                        value={ prefix }
                        onChange={ ( value: string ) => setAttributes( { prefix: value } ) }
                        __nextHasNoMarginBottom
                        __next40pxDefaultSize
                    />
                    <TextControl
                        label={ __( 'Singular label', TEXT_DOMAIN ) }
                        value={ attributes.singular ?? 'result' }
                        onChange={ ( value: string ) => setAttributes( { singular: value } ) }
                        __nextHasNoMarginBottom
                        __next40pxDefaultSize
                    />
                    <TextControl
                        label={ __( 'Plural label', TEXT_DOMAIN ) }
                        value={ attributes.plural ?? 'results' }
                        onChange={ ( value: string ) => setAttributes( { plural: value } ) }
                        __nextHasNoMarginBottom
                        __next40pxDefaultSize
                    />
                    <TextControl
                        label={ __( 'Suffix', TEXT_DOMAIN ) }
                        value={ suffix }
                        onChange={ ( value: string ) => setAttributes( { suffix: value } ) }
                        __nextHasNoMarginBottom
                        __next40pxDefaultSize
                    />
                </PanelBody>
            </InspectorControls>
            <div { ...blockProps }>
                { prefix }
                { null !== preview.range ? (
                    <>
                        <strong>{ preview.range }</strong>{ ` ${ __( 'of', TEXT_DOMAIN ) } ` }<strong>{ preview.total }</strong>
                    </>
                ) : (
                    <strong>{ preview.total }</strong>
                ) }
                { `${ '' !== preview.noun ? ` ${ preview.noun }` : '' }${ suffix }` }
            </div>
        </>
    );
}
