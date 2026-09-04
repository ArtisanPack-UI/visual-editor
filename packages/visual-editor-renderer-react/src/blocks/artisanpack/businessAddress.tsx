/**
 * React renderer for the `artisanpack/business-address` block (#761).
 *
 * Mirrors the Blade partial + Vue renderer. Address fields + a
 * ready-to-embed `mapEmbedUrl` arrive on `_resolvedBusinessInfo`
 * (populated by BusinessInfoResolver from the host's
 * `ap.visualEditor.businessInfo` filter). The renderer only decides
 * layout — the OSM-vs-Google decision has already happened server-side.
 */

import type { ReactElement } from 'react';

import { attrRecord, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

function joinCityLine(city: string, region: string, postalCode: string): string {
    const cityRegion = [city, region].filter((s) => s !== '').join(', ');
    return [cityRegion, postalCode].filter((s) => s !== '').join(' ');
}

export function BusinessAddressBlock({ attributes }: BlockRendererProps): ReactElement {
    const info = attrRecord(attributes._resolvedBusinessInfo);
    const address = attrRecord(info.address);

    const street = attrString(address.street);
    const street2 = attrString(address.street2);
    const city = attrString(address.city);
    const region = attrString(address.region);
    const postalCode = attrString(address.postal_code);
    const country = attrString(address.country);

    const hasAddress =
        street + street2 + city + region + postalCode + country !== '';

    const mapEmbedUrl = safeUrl(info.mapEmbedUrl);

    const className = attrString(attributes.className);
    const ariaLabel = attrString(attributes.ariaLabel, 'Business address');
    const wrapperClasses = classList(['ap-business-address', className]);

    const cityLine = joinCityLine(city, region, postalCode);

    return (
        <section className={wrapperClasses} aria-label={ariaLabel}>
            {hasAddress && (
                <address className="ap-business-address__address">
                    {street !== '' && street}
                    {street2 !== '' && (
                        <>
                            <br />
                            {street2}
                        </>
                    )}
                    {cityLine !== '' && (
                        <>
                            <br />
                            {cityLine}
                        </>
                    )}
                    {country !== '' && (
                        <>
                            <br />
                            {country}
                        </>
                    )}
                </address>
            )}
            {mapEmbedUrl !== '' && (
                <div className="ap-business-address__map">
                    <iframe
                        src={mapEmbedUrl}
                        title="Map"
                        loading="lazy"
                        referrerPolicy="no-referrer"
                        sandbox="allow-scripts allow-same-origin allow-popups"
                        style={{
                            border: 0,
                            width: '100%',
                            height: '100%',
                            minHeight: '300px',
                        }}
                        allowFullScreen
                    />
                </div>
            )}
        </section>
    );
}
