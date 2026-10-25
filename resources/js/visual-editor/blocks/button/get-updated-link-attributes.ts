/**
 * Button — link attribute updater.
 *
 * Adapted from
 * `@wordpress/block-library/src/button/get-updated-link-attributes.js`
 * (v9.43.0). Delegates to the shared `resolveLinkChange()` instead of
 * upstream's substring matching, so:
 *
 *   - the `noreferrer noopener` tokens are removed whatever order they
 *     were stored in (upstream only matched that exact sequence);
 *   - a `nofollow` typed into Link relation survives a URL edit when the
 *     caller has no nofollow toggle (`nofollow` left undefined);
 *   - Dynamic Content `{{token}}` hrefs are not prefixed with `https://`.
 */

import { resolveLinkChange } from '../_shared/link-attributes';

interface UpdateLinkInput {
    rel?: string;
    url?: string;
    opensInNewTab?: boolean;
    nofollow?: boolean;
}

interface UpdatedLinkAttributes {
    url: string;
    linkTarget: string | undefined;
    rel: string | undefined;
}

export function getUpdatedLinkAttributes({
    rel = '',
    url = '',
    opensInNewTab,
    nofollow,
}: UpdateLinkInput): UpdatedLinkAttributes {
    const resolved = resolveLinkChange({ url, opensInNewTab, nofollow }, rel);

    return {
        url: resolved.url,
        linkTarget: resolved.linkTarget,
        rel: resolved.rel,
    };
}
