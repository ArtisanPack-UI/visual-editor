/**
 * QueryNoResults — edit component.
 *
 * Wrapper block whose inner template is shown on the front end when
 * the surrounding `artisanpack/query` resolves to zero results. In the
 * editor the empty-state usually wants to be invisible (so authors
 * editing a populated query see their populated layout, not the
 * fallback) but is also stylable on demand. Issue #599 introduces a
 * `showInEditor` design-time toggle on the block so authors can flip
 * the empty state on while they style it, without forcing the query
 * to actually return zero results. The front-end render still only
 * emits the wrapper when `_resolvedTotal === 0`. Phase I-Block-Fork
 * query family (#521).
 */

import type { ReactElement } from 'react';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { readQueryPreviewContext } from '../../editor/query-preview-context';
import { TEXT_DOMAIN } from '../../vendor/i18n';

const DEFAULT_TEMPLATE: ReadonlyArray<[ string, Record<string, unknown> ]> = [
    [
        'artisanpack/paragraph',
        {
            placeholder: __(
                'Add text or blocks that will display when the query returns no results.',
                TEXT_DOMAIN
            ),
        },
    ],
];

interface QueryNoResultsEditProps {
    attributes: { showInEditor?: boolean };
    setAttributes: ( changes: { showInEditor?: boolean } ) => void;
    context?: Record<string, unknown>;
}

export default function QueryNoResultsEdit( {
    attributes,
    setAttributes,
    context,
}: QueryNoResultsEditProps ): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    const showInEditor = attributes.showInEditor === true;
    const preview = readQueryPreviewContext( context );
    // Honour the front-end semantics in the canvas too: when the
    // resolver returned zero matches, the no-results state is the
    // active branch regardless of the design-time toggle, because
    // that's what the user will see on the page. Otherwise it's a
    // pure design-time affordance gated by the toggle.
    const isZeroResultBranch = preview !== null && preview.status === 'ready' && preview.total === 0;
    const shouldRenderContent = showInEditor || isZeroResultBranch;

    return (
        <div { ...blockProps }>
            <InspectorControls>
                <PanelBody title={ __( 'Empty state', TEXT_DOMAIN ) }>
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Show in editor', TEXT_DOMAIN ) }
                        help={ __(
                            'Render the empty-state template in the canvas so you can style it without forcing the query to return zero results. Does not affect the front-end render.',
                            TEXT_DOMAIN
                        ) }
                        checked={ showInEditor }
                        onChange={ ( value ) => setAttributes( { showInEditor: value } ) }
                    />
                </PanelBody>
            </InspectorControls>
            { shouldRenderContent ? (
                <InnerBlocks template={ [ ...DEFAULT_TEMPLATE ] } />
            ) : (
                <span
                    contentEditable={ false }
                    aria-hidden={ true }
                    style={ {
                        display: 'inline-block',
                        padding: '0.25em 0.6em',
                        border: '1px dashed currentColor',
                        borderRadius: '4px',
                        opacity: 0.55,
                        fontStyle: 'italic',
                    } }
                >
                    { __( 'No Results state (hidden — toggle on to style it)', TEXT_DOMAIN ) }
                </span>
            ) }
        </div>
    );
}
