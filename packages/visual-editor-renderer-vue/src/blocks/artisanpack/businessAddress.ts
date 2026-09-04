/**
 * Vue renderer for the `artisanpack/business-address` block (#761).
 *
 * Mirrors the Blade partial + React renderer. Reads address fields +
 * pre-composed `mapEmbedUrl` from `_resolvedBusinessInfo`.
 */

import { defineComponent, h, type VNode } from 'vue';

import { attrRecord, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

function joinCityLine(city: string, region: string, postalCode: string): string {
    const cityRegion = [city, region].filter((s) => s !== '').join(', ');
    return [cityRegion, postalCode].filter((s) => s !== '').join(' ');
}

export const BusinessAddressBlock = defineComponent({
    name: 'BusinessAddressBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const attributes = props.attributes;
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
            const cityLine = joinCityLine(city, region, postalCode);

            const className = attrString(attributes.className);
            const ariaLabel = attrString(attributes.ariaLabel, 'Business address');
            const wrapperClasses = classList(['ap-business-address', className]);

            const children: VNode[] = [];

            if (hasAddress) {
                const addressChildren: (string | VNode)[] = [];
                if (street !== '') {
                    addressChildren.push(street);
                }
                if (street2 !== '') {
                    addressChildren.push(h('br'));
                    addressChildren.push(street2);
                }
                if (cityLine !== '') {
                    addressChildren.push(h('br'));
                    addressChildren.push(cityLine);
                }
                if (country !== '') {
                    addressChildren.push(h('br'));
                    addressChildren.push(country);
                }
                children.push(
                    h(
                        'address',
                        { class: 'ap-business-address__address' },
                        addressChildren
                    )
                );
            }

            if (mapEmbedUrl !== '') {
                children.push(
                    h('div', { class: 'ap-business-address__map' }, [
                        h('iframe', {
                            src: mapEmbedUrl,
                            title: 'Map',
                            loading: 'lazy',
                            referrerpolicy: 'no-referrer',
                            sandbox: 'allow-scripts allow-same-origin allow-popups',
                            style: 'border:0;width:100%;height:100%;min-height:300px;',
                            allowfullscreen: '',
                        }),
                    ])
                );
            }

            return h(
                'section',
                { class: wrapperClasses, 'aria-label': ariaLabel },
                children
            );
        };
    },
});
