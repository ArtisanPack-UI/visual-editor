/**
 * Business Email — editor-side WYSIWYG preview (#761).
 *
 * Renders the real email address as a non-navigating `mailto:` link from
 * the host's `ap.visualEditor.businessInfo` filter so the canvas matches
 * the public front end. Resolution priority:
 *
 *   1. `_resolvedBusinessInfo` attribute (front-end / saved-tree path).
 *   2. Envelope fetched from `/visual-editor/api/business-info`
 *      (editor path, shared across all business-* blocks on the page).
 *   3. Hardcoded stub address so a block placed before the filter is
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

interface BusinessEmailAttributes {
    readonly label: string;
    readonly showIcon: boolean;
    readonly _resolvedBusinessInfo?: BusinessInfoEnvelope;
}

interface BusinessEmailEditProps {
    readonly attributes: BusinessEmailAttributes;
    readonly setAttributes: (next: Partial<BusinessEmailAttributes>) => void;
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Percent-encode the local + domain independently so special characters
// in a well-formed address can't inject mailto headers (subject/cc/bcc)
// or be misinterpreted by the mail client.
function safeMailtoHref(email: string): string {
    const at = email.lastIndexOf('@');
    if (at < 0) {
        return `mailto:${encodeURIComponent(email)}`;
    }
    const local = email.slice(0, at);
    const domain = email.slice(at + 1);
    return `mailto:${encodeURIComponent(local)}@${encodeURIComponent(domain)}`;
}

export default function BusinessEmailEdit({
    attributes,
    setAttributes,
}: BusinessEmailEditProps): ReactElement {
    const { label, showIcon } = attributes;

    const blockProps = useBlockProps({ className: 'ap-business-email' });

    const stamped = attributes._resolvedBusinessInfo;
    const { envelope: fetched } = useBusinessInfo();

    const envelope: BusinessInfoEnvelope | null = stamped ?? fetched;
    const email =
        typeof envelope?.email === 'string' ? envelope.email.trim() : '';
    const isValidEmail = '' !== email && EMAIL_PATTERN.test(email);

    const displayLabel = isValidEmail
        ? '' !== label
            ? label
            : email
        : '' !== label
            ? label
            : __('hello@example.com', TEXT_DOMAIN);

    const href = isValidEmail ? safeMailtoHref(email) : '#email-pseudo-link';

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Business email settings', TEXT_DOMAIN)} initialOpen>
                    <TextControl
                        label={__('Custom label', TEXT_DOMAIN)}
                        help={__(
                            'Optional. Overrides the email address as the visible link text; the mailto: target still uses the address itself.',
                            TEXT_DOMAIN
                        )}
                        value={label}
                        onChange={(next) => setAttributes({ label: next })}
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Show email icon', TEXT_DOMAIN)}
                        checked={showIcon}
                        onChange={(next) => setAttributes({ showIcon: next })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <a
                    className="ap-business-email__link"
                    href={href}
                    onClick={(event) => event.preventDefault()}
                >
                    {showIcon && (
                        <span className="ap-business-email__icon" aria-hidden="true">
                            {'✉'}
                        </span>
                    )}
                    <span className="ap-business-email__label">{displayLabel}</span>
                </a>
            </div>
        </>
    );
}
