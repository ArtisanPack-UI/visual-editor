/**
 * Search Filters Taxonomy — editor-side preview (#502).
 *
 * Renders a labelled `<select>` with a placeholder option so authors
 * see the chrome immediately. The runtime renderers populate it with
 * the actual term list from the host's `_resolvedTerms` stamp.
 */

import { useId, type ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface SearchFiltersTaxonomyAttributes {
    readonly label: string;
    readonly taxonomy: string;
    readonly taxonomyName: string;
}

interface SearchFiltersTaxonomyEditProps {
    readonly attributes: SearchFiltersTaxonomyAttributes;
    readonly setAttributes: (next: Partial<SearchFiltersTaxonomyAttributes>) => void;
}

const TAXONOMY_SLUG_PATTERN = /^[a-z][a-z0-9_-]*$/;

export default function SearchFiltersTaxonomyEdit({
    attributes,
    setAttributes,
}: SearchFiltersTaxonomyEditProps): ReactElement {
    const { label, taxonomy, taxonomyName } = attributes;

    const blockProps = useBlockProps({ className: 'ap-search-filters-taxonomy' });
    const previewId = useId();

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Taxonomy search settings', TEXT_DOMAIN)} initialOpen>
                    <TextControl
                        label={__('Label', TEXT_DOMAIN)}
                        value={label}
                        onChange={(value) => setAttributes({ label: value })}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Taxonomy slug', TEXT_DOMAIN)}
                        value={taxonomy}
                        onChange={(value) => {
                            const next = value.trim().toLowerCase();
                            if (next === '' || TAXONOMY_SLUG_PATTERN.test(next)) {
                                setAttributes({ taxonomy: next });
                            }
                        }}
                        help={__(
                            'Lowercase slug of the taxonomy. Posted to the host page as the GET key under which the selected term lives.',
                            TEXT_DOMAIN
                        )}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Taxonomy display name', TEXT_DOMAIN)}
                        value={taxonomyName}
                        onChange={(value) => setAttributes({ taxonomyName: value })}
                        help={__(
                            'Shown inside the placeholder option (e.g. "Select a Category").',
                            TEXT_DOMAIN
                        )}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <label
                    className="ap-search-filters-taxonomy__label"
                    htmlFor={previewId}
                >
                    {label}
                </label>
                <select
                    className="ap-search-filters-taxonomy__select"
                    id={previewId}
                    disabled
                >
                    <option value="">
                        {__('Select a', TEXT_DOMAIN)} {taxonomyName}
                    </option>
                </select>
            </div>
        </>
    );
}
