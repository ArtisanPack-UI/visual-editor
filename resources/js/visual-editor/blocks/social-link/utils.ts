/**
 * Social Icon — service lookups (#1023).
 */

import { socialLinkServices, type SocialLinkService } from './services';

/**
 * The registered service for `slug`, or `undefined` for an unknown one.
 *
 * @since 1.18.0
 */
export function getSocialLinkService(slug: string): SocialLinkService | undefined {
    return socialLinkServices().find((service) => service.slug === slug);
}

/**
 * The glyph for `slug`, falling back to the generic link glyph so an
 * unknown service still renders an icon.
 *
 * @since 1.18.0
 */
export function getSocialLinkGlyph(slug: string): Pick<SocialLinkService, 'viewBox' | 'path'> {
    const service = getSocialLinkService(slug) ?? getSocialLinkService('chain');

    return { viewBox: service?.viewBox ?? '0 0 24 24', path: service?.path ?? '' };
}
