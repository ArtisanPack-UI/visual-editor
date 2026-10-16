/**
 * Copyright — editor-side preview.
 *
 * Renders the resolved copyright line with the current year so authors
 * see the live shape as they tweak the type / text controls. The real
 * markup is rendered at runtime by the Blade / React / Vue renderers
 * from the saved attributes.
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

type CopyrightType = 'icon-text' | 'icon-only' | 'text-only';

interface CopyrightAttributes {
    readonly copyrightType: CopyrightType;
    readonly copyrightText: string;
}

interface CopyrightEditProps {
    readonly attributes: CopyrightAttributes;
    readonly setAttributes: (next: Partial<CopyrightAttributes>) => void;
}

function typeOptions(): ReadonlyArray<{
    readonly label: string;
    readonly value: CopyrightType;
}> {
    return [
        { label: __('Icon and text', TEXT_DOMAIN), value: 'icon-text' },
        { label: __('Icon only', TEXT_DOMAIN), value: 'icon-only' },
        { label: __('Text only', TEXT_DOMAIN), value: 'text-only' },
    ];
}

function isCopyrightType(value: string): value is CopyrightType {
    return typeOptions().some((option) => option.value === value);
}

function buildLine(type: CopyrightType, text: string, year: number): string {
    const trimmed = text.trim();
    if (type === 'icon-only') {
        return `© ${year}`;
    }
    if (type === 'text-only') {
        return trimmed === '' ? `${year}` : `${trimmed} ${year}`;
    }
    return trimmed === '' ? `© ${year}` : `© ${trimmed} ${year}`;
}

export default function CopyrightEdit({
    attributes,
    setAttributes,
}: CopyrightEditProps): ReactElement {
    const { copyrightType, copyrightText } = attributes;

    const blockProps = useBlockProps({ className: 'ap-copyright' });

    const currentYear = new Date().getUTCFullYear();
    const line = buildLine(copyrightType, copyrightText, currentYear);

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Copyright settings', TEXT_DOMAIN)} initialOpen>
                    <SelectControl
                        label={__('Copyright type', TEXT_DOMAIN)}
                        value={copyrightType}
                        options={typeOptions()}
                        onChange={(value) => {
                            if (isCopyrightType(value)) {
                                setAttributes({ copyrightType: value });
                            }
                        }}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Copyright text', TEXT_DOMAIN)}
                        value={copyrightText}
                        onChange={(value) => setAttributes({ copyrightText: value })}
                        help={__(
                            'Shown next to the © symbol (icon and text) or on its own (text only). Hidden in icon-only mode.',
                            TEXT_DOMAIN
                        )}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <p {...blockProps}>{line}</p>
        </>
    );
}
