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

function severityOptions(): ReadonlyArray<{ readonly label: string; readonly value: CalloutSeverity }> {
    return [
        { label: __('Info', TEXT_DOMAIN), value: 'info' },
        { label: __('Success', TEXT_DOMAIN), value: 'success' },
        { label: __('Warning', TEXT_DOMAIN), value: 'warning' },
        { label: __('Error', TEXT_DOMAIN), value: 'error' },
    ];
}

function iconOptions(): ReadonlyArray<{ readonly label: string; readonly value: CalloutIconName }> {
    return [
        { label: __('Info', TEXT_DOMAIN), value: 'info' },
        { label: __('Check', TEXT_DOMAIN), value: 'check' },
        { label: __('Warning', TEXT_DOMAIN), value: 'warning' },
        { label: __('Error', TEXT_DOMAIN), value: 'error' },
        { label: __('Lightbulb', TEXT_DOMAIN), value: 'lightbulb' },
    ];
}

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
                        options={severityOptions()}
                        onChange={(value) =>
                            setAttributes({ severity: value as CalloutSeverity })
                        }
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Icon', TEXT_DOMAIN)}
                        value={icon}
                        options={iconOptions()}
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
