/**
 * Callout — editor-side render.
 *
 * The edit component mirrors the static `save.tsx` output so authors see
 * exactly what the frontend renders, and wires the severity + icon
 * attributes into the InspectorControls sidebar so they can be changed
 * without leaving the canvas. `RichText` drives the body; Gutenberg's
 * `source: 'rich-text'` attribute wiring handles serialization for us.
 */

import type { ReactElement } from 'react';
import {
    InspectorControls,
    RichText,
    useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import { CalloutIcon, type CalloutIconName } from './icons';

export type CalloutSeverity = 'info' | 'success' | 'warning' | 'error';

interface CalloutAttributes {
    readonly severity: CalloutSeverity;
    readonly icon: CalloutIconName;
    readonly content: string;
}

interface CalloutEditProps {
    readonly attributes: CalloutAttributes;
    readonly setAttributes: (next: Partial<CalloutAttributes>) => void;
}

const SEVERITY_OPTIONS: ReadonlyArray<{ readonly label: string; readonly value: CalloutSeverity }> = [
    { label: 'Info', value: 'info' },
    { label: 'Success', value: 'success' },
    { label: 'Warning', value: 'warning' },
    { label: 'Error', value: 'error' },
];

const ICON_OPTIONS: ReadonlyArray<{ readonly label: string; readonly value: CalloutIconName }> = [
    { label: 'Info', value: 'info' },
    { label: 'Check', value: 'check' },
    { label: 'Warning', value: 'warning' },
    { label: 'Error', value: 'error' },
    { label: 'Lightbulb', value: 'lightbulb' },
];

export default function CalloutEdit({
    attributes,
    setAttributes,
}: CalloutEditProps): ReactElement {
    const { severity, icon, content } = attributes;

    const blockProps = useBlockProps({
        className: `ap-callout ap-callout--${severity}`,
        'data-severity': severity,
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Callout settings', TEXT_DOMAIN)} initialOpen>
                    <SelectControl
                        label={__('Severity', TEXT_DOMAIN)}
                        value={severity}
                        options={SEVERITY_OPTIONS.map((option) => ({
                            label: __(option.label, TEXT_DOMAIN),
                            value: option.value,
                        }))}
                        onChange={(value) =>
                            setAttributes({ severity: value as CalloutSeverity })
                        }
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Icon', TEXT_DOMAIN)}
                        value={icon}
                        options={ICON_OPTIONS.map((option) => ({
                            label: __(option.label, TEXT_DOMAIN),
                            value: option.value,
                        }))}
                        onChange={(value) =>
                            setAttributes({ icon: value as CalloutIconName })
                        }
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <span className="ap-callout__icon" aria-hidden="true">
                    <CalloutIcon name={icon} />
                </span>
                <RichText
                    tagName="div"
                    className="ap-callout__body"
                    value={content}
                    onChange={(next: string) => setAttributes({ content: next })}
                    placeholder={__('Write a callout…', TEXT_DOMAIN)}
                />
            </div>
        </>
    );
}
