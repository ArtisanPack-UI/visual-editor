/**
 * Business Phone — editor-side WYSIWYG preview (#761).
 *
 * Renders the real phone number as a non-navigating `tel:` link from the
 * host's `ap.visualEditor.businessInfo` filter so the canvas matches
 * the public front end. Resolution priority:
 *
 *   1. `_resolvedBusinessInfo` attribute (front-end / saved-tree path).
 *   2. Envelope fetched from `/visual-editor/api/business-info`
 *      (editor path, shared across all business-* blocks on the page).
 *   3. Hardcoded stub number so a block placed before the filter is
 *      wired up still looks intentional in the canvas.
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    useBusinessInfo,
    type BusinessInfoEnvelope,
} from '../_shared/use-business-info';

interface BusinessPhoneAttributes {
    readonly label: string;
    readonly showIcon: boolean;
    readonly _resolvedBusinessInfo?: BusinessInfoEnvelope;
}

interface BusinessPhoneEditProps {
    readonly attributes: BusinessPhoneAttributes;
    readonly setAttributes: (next: Partial<BusinessPhoneAttributes>) => void;
}

function telTarget(phone: string): string {
    return phone.replace(/[^0-9+xX,]/g, '');
}

export default function BusinessPhoneEdit({
    attributes,
    setAttributes,
}: BusinessPhoneEditProps): ReactElement {
    const { label, showIcon } = attributes;

    const blockProps = useBlockProps({ className: 'ap-business-phone' });

    const stamped = attributes._resolvedBusinessInfo;
    const { envelope: fetched } = useBusinessInfo();

    const envelope: BusinessInfoEnvelope | null = stamped ?? fetched;
    const phone =
        typeof envelope?.phone === 'string' ? envelope.phone.trim() : '';
    const hasPhone = '' !== phone;

    const displayLabel = hasPhone
        ? '' !== label
            ? label
            : phone
        : '' !== label
            ? label
            : __('(555) 123-4567', TEXT_DOMAIN);

    const href = hasPhone ? `tel:${telTarget(phone)}` : '#phone-pseudo-link';

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Business phone settings', TEXT_DOMAIN)} initialOpen>
                    <TextControl
                        label={__('Custom label', TEXT_DOMAIN)}
                        help={__(
                            'Optional. Overrides the phone number as the visible link text; the tel: target still uses the number itself.',
                            TEXT_DOMAIN
                        )}
                        value={label}
                        onChange={(next) => setAttributes({ label: next })}
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Show phone icon', TEXT_DOMAIN)}
                        checked={showIcon}
                        onChange={(next) => setAttributes({ showIcon: next })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <a
                    className="ap-business-phone__link"
                    href={href}
                    onClick={(event) => event.preventDefault()}
                >
                    {showIcon && (
                        <span className="ap-business-phone__icon" aria-hidden="true">
                            {'☎'}
                        </span>
                    )}
                    <span className="ap-business-phone__label">{displayLabel}</span>
                </a>
            </div>
        </>
    );
}
