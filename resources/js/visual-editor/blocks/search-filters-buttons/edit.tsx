/**
 * Search Filters Buttons — editor-side preview (#502).
 *
 * Renders the submit + reset button pair so authors can adjust the
 * labels and see the spacing in place. The real markup is emitted by
 * the runtime renderers.
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface SearchFiltersButtonsAttributes {
    readonly searchLabel: string;
    readonly clearLabel: string;
}

interface SearchFiltersButtonsEditProps {
    readonly attributes: SearchFiltersButtonsAttributes;
    readonly setAttributes: (next: Partial<SearchFiltersButtonsAttributes>) => void;
}

export default function SearchFiltersButtonsEdit({
    attributes,
    setAttributes,
}: SearchFiltersButtonsEditProps): ReactElement {
    const { searchLabel, clearLabel } = attributes;

    const blockProps = useBlockProps({ className: 'ap-search-filters-buttons' });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Button labels', TEXT_DOMAIN)} initialOpen>
                    <TextControl
                        label={__('Submit label', TEXT_DOMAIN)}
                        value={searchLabel}
                        onChange={(value) => setAttributes({ searchLabel: value })}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Reset label', TEXT_DOMAIN)}
                        value={clearLabel}
                        onChange={(value) => setAttributes({ clearLabel: value })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <input
                    type="submit"
                    className="ap-search-filters-buttons__submit"
                    value={searchLabel}
                    disabled
                />
                <input
                    type="reset"
                    className="ap-search-filters-buttons__reset"
                    value={clearLabel}
                    disabled
                />
            </div>
        </>
    );
}
