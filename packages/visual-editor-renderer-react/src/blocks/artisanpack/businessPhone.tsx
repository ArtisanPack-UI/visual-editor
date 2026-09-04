/**
 * React renderer for the `artisanpack/business-phone` block (#761).
 *
 * Mirrors the Blade partial + Vue renderer. The phone number arrives on
 * `_resolvedBusinessInfo` from BusinessInfoResolver + the host's
 * `ap.visualEditor.businessInfo` filter. The renderer wraps the number
 * in a `tel:` link, stripping non-dial characters from the target only.
 */

import type { ReactElement } from 'react';

import { attrBoolean, attrRecord, attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

function telTarget(phone: string): string {
    return phone.replace(/[^0-9+xX,]/g, '');
}

export function BusinessPhoneBlock({ attributes }: BlockRendererProps): ReactElement | null {
    const info = attrRecord(attributes._resolvedBusinessInfo);
    const phone = attrString(info.phone).trim();

    const className = attrString(attributes.className);
    const wrapperClasses = classList(['ap-business-phone', className]);
    const ariaLabel = attrString(attributes.ariaLabel, 'Business phone');

    const showIcon = attrBoolean(attributes.showIcon, false);
    const explicitLabel = attrString(attributes.label);
    const label = explicitLabel !== '' ? explicitLabel : phone;

    if (phone === '') {
        return <div className={wrapperClasses} aria-label={ariaLabel} />;
    }

    return (
        <div className={wrapperClasses} aria-label={ariaLabel}>
            <a className="ap-business-phone__link" href={`tel:${telTarget(phone)}`}>
                {showIcon && (
                    <span className="ap-business-phone__icon" aria-hidden="true">
                        {'☎'}
                    </span>
                )}
                <span className="ap-business-phone__label">{label}</span>
            </a>
        </div>
    );
}
