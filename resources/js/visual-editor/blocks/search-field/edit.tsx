/**
 * Search Field — editor-side preview (#502).
 *
 * Renders an empty search input + label so authors see the chrome
 * immediately. The runtime renderers fill the input's value from the
 * current `s` query parameter.
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface SearchFieldAttributes {
    readonly label: string;
    readonly placeholder: string;
}

interface SearchFieldEditProps {
    readonly attributes: SearchFieldAttributes;
    readonly setAttributes: (next: Partial<SearchFieldAttributes>) => void;
}

export default function SearchFieldEdit({
    attributes,
    setAttributes,
}: SearchFieldEditProps): ReactElement {
    const { label, placeholder } = attributes;

    const blockProps = useBlockProps({ className: 'ap-search-field' });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Search field settings', TEXT_DOMAIN)} initialOpen>
                    <TextControl
                        label={__('Label', TEXT_DOMAIN)}
                        value={label}
                        onChange={(value) => setAttributes({ label: value })}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Placeholder', TEXT_DOMAIN)}
                        value={placeholder}
                        onChange={(value) => setAttributes({ placeholder: value })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <label className="ap-search-field__label" htmlFor="ap-search-field-preview">
                    {label}
                </label>
                <input
                    type="search"
                    className="ap-search-field__input"
                    id="ap-search-field-preview"
                    placeholder={placeholder}
                    disabled
                />
            </div>
        </>
    );
}
