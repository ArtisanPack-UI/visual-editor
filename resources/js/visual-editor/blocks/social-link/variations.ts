/**
 * Social Icon — one inserter variation per service (#1023).
 *
 * Ported from `@wordpress/block-library/src/social-link/variations.js`
 * (v9.43.0); the list comes from the generated `./services` registry.
 */

import { createElement } from '@wordpress/element';
import type { BlockVariation } from '@wordpress/blocks';

import ServiceIcon from './service-icon';
import { socialLinkServices } from './services';

export default function getSocialLinkVariations(): BlockVariation[] {
    return socialLinkServices().map((service, index) => ({
        ...(0 === index ? { isDefault: true } : {}),
        name: service.slug,
        attributes: { service: service.slug },
        title: service.label,
        ...(service.keywords ? { keywords: [...service.keywords] } : {}),
        icon: createElement(ServiceIcon, { service: service.slug }),
        isActive: (blockAttributes: Record<string, unknown>, variationAttributes: Record<string, unknown>) =>
            blockAttributes.service === variationAttributes.service,
    }));
}
