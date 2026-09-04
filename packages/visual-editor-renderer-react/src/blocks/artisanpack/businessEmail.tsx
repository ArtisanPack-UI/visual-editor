/**
 * React renderer for the `artisanpack/business-email` block (#761).
 *
 * Mirrors the Blade partial + Vue renderer. The email address arrives on
 * `_resolvedBusinessInfo` from BusinessInfoResolver + the host's
 * `ap.visualEditor.businessInfo` filter. The renderer wraps a valid
 * address in a `mailto:` link and drops through to an empty wrapper on
 * an invalid or missing address.
 */

import type { ReactElement } from 'react';

import { attrBoolean, attrRecord, attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

// Conservative RFC-5322-adjacent check — matches the FILTER_VALIDATE_EMAIL
// contract the Blade partial applies so React and Blade agree on when to
// emit a link vs. an empty wrapper.
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function BusinessEmailBlock({ attributes }: BlockRendererProps): ReactElement {
    const info = attrRecord(attributes._resolvedBusinessInfo);
    const email = attrString(info.email).trim();

    const className = attrString(attributes.className);
    const wrapperClasses = classList(['ap-business-email', className]);

    const isValid = email !== '' && EMAIL_PATTERN.test(email);

    const showIcon = attrBoolean(attributes.showIcon, false);
    const explicitLabel = attrString(attributes.label);
    const label = explicitLabel !== '' ? explicitLabel : email;

    if (!isValid) {
        return <div className={wrapperClasses} />;
    }

    return (
        <div className={wrapperClasses}>
            <a className="ap-business-email__link" href={`mailto:${email}`}>
                {showIcon && (
                    <span className="ap-business-email__icon" aria-hidden="true">
                        {'✉'}
                    </span>
                )}
                <span className="ap-business-email__label">{label}</span>
            </a>
        </div>
    );
}
