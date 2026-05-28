/**
 * Gallery — internal href/destination helpers.
 *
 * Ported from `@wordpress/block-library/src/gallery/utils.js`
 * (v9.43.0). Maps the gallery `linkTo` setting to the equivalent
 * image-block link destination + lightbox state.
 *
 * Image link-destination constants are inlined (`IMAGE_LINK_*`)
 * rather than imported from `../image/constants` — the fork does not
 * (and should not) depend on block-library internals at runtime.
 * Values are kept identical to upstream so transforms round-trip.
 */

import {
    LINK_DESTINATION_ATTACHMENT,
    LINK_DESTINATION_MEDIA,
    LINK_DESTINATION_NONE,
    LINK_DESTINATION_MEDIA_WP_CORE,
    LINK_DESTINATION_ATTACHMENT_WP_CORE,
    LINK_DESTINATION_LIGHTBOX,
} from './constants';

// Mirrors `@wordpress/block-library/src/image/constants.js`.
const IMAGE_LINK_DESTINATION_NONE = 'none';
const IMAGE_LINK_DESTINATION_MEDIA = 'media';
const IMAGE_LINK_DESTINATION_ATTACHMENT = 'attachment';

interface GalleryImage {
    readonly source_url?: string;
    readonly url?: string;
    readonly link?: string;
}

interface BlockAttributesWithLightbox {
    readonly lightbox?: { enabled?: boolean; [key: string]: unknown };
    readonly [key: string]: unknown;
}

interface LightboxSetting {
    readonly enabled?: boolean;
}

export interface HrefAndDestination {
    href?: string;
    linkDestination?: string;
    lightbox?: { enabled?: boolean; [key: string]: unknown };
}

export function getHrefAndDestination(
    image: GalleryImage | null | undefined,
    galleryDestination: string | undefined,
    imageDestination?: string | false,
    attributes?: BlockAttributesWithLightbox,
    lightboxSetting?: LightboxSetting
): HrefAndDestination {
    switch (imageDestination ? imageDestination : galleryDestination) {
        case LINK_DESTINATION_MEDIA_WP_CORE:
        case LINK_DESTINATION_MEDIA:
            return {
                href: image?.source_url || image?.url,
                linkDestination: IMAGE_LINK_DESTINATION_MEDIA,
                lightbox: lightboxSetting?.enabled
                    ? { ...attributes?.lightbox, enabled: false }
                    : undefined,
            };
        case LINK_DESTINATION_ATTACHMENT_WP_CORE:
        case LINK_DESTINATION_ATTACHMENT:
            return {
                href: image?.link,
                linkDestination: IMAGE_LINK_DESTINATION_ATTACHMENT,
                lightbox: lightboxSetting?.enabled
                    ? { ...attributes?.lightbox, enabled: false }
                    : undefined,
            };
        case LINK_DESTINATION_LIGHTBOX:
            return {
                href: undefined,
                lightbox: !lightboxSetting?.enabled
                    ? { ...attributes?.lightbox, enabled: true }
                    : undefined,
                linkDestination: IMAGE_LINK_DESTINATION_NONE,
            };
        case LINK_DESTINATION_NONE:
            return {
                href: undefined,
                linkDestination: IMAGE_LINK_DESTINATION_NONE,
                lightbox: undefined,
            };
    }

    return {};
}

/**
 * Inlined from `@wordpress/block-library/src/image/utils.js`. The
 * upstream module isn't reachable via the package's `exports` field,
 * so we mirror the function under the fork. The behaviour is verbatim:
 * builds the `{ linkTarget, rel }` pair for an image block based on the
 * gallery's new-tab toggle.
 */
const NEW_TAB_REL = ['noreferrer', 'noopener'];

function removeNewTabRel(currentRel: string | undefined): string | undefined {
    let newRel = currentRel;

    if (currentRel !== undefined && newRel) {
        NEW_TAB_REL.forEach((relVal) => {
            const regExp = new RegExp('\\b' + relVal + '\\b', 'gi');
            newRel = (newRel as string).replace(regExp, '');
        });

        if (newRel !== currentRel) {
            newRel = (newRel as string).trim();
        }

        if (!newRel) {
            newRel = undefined;
        }
    }

    return newRel;
}

export function getUpdatedLinkTargetSettings(
    value: string | undefined,
    { rel }: { rel?: string }
): { linkTarget: string | undefined; rel: string | undefined } {
    const linkTarget = value ? '_blank' : undefined;

    let updatedRel: string | undefined;
    if (!linkTarget && !rel) {
        updatedRel = undefined;
    } else if (linkTarget) {
        // When the link opens in a new tab, ensure the protective
        // `noopener noreferrer` tokens are present (and de-duplicated)
        // so the opener can't access `window.opener` and the referrer
        // isn't leaked. Start from whatever `removeNewTabRel` returns
        // (so we don't double up if the rel already carried them).
        const stripped = removeNewTabRel(rel);
        const tokens = new Set(
            (stripped ?? '').split(/\s+/).filter(Boolean)
        );
        tokens.add('noopener');
        tokens.add('noreferrer');
        updatedRel = Array.from(tokens).join(' ');
    } else {
        updatedRel = removeNewTabRel(rel);
    }

    return {
        linkTarget,
        rel: updatedRel,
    };
}

/**
 * Inlined from `@wordpress/block-library/src/image/utils.js`. Maps a
 * size slug onto the corresponding source URL from the attachment
 * record. Returns an empty object when the size is unavailable so
 * existing image attributes are not clobbered.
 */
interface ImageMediaDetails {
    readonly media_details?: { readonly sizes?: { readonly [slug: string]: { readonly source_url?: string } | undefined } };
}

export function getImageSizeAttributes(
    image: ImageMediaDetails | null | undefined,
    size: string
): { url?: string; width?: undefined; height?: undefined; sizeSlug?: string } {
    const url = image?.media_details?.sizes?.[size]?.source_url;

    if (url) {
        return { url, width: undefined, height: undefined, sizeSlug: size };
    }

    return {};
}
