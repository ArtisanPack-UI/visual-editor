/**
 * Button — link attribute updater.
 *
 * Ported from
 * `@wordpress/block-library/src/button/get-updated-link-attributes.js`
 * (v9.43.0).
 */

import { prependHTTPS } from '@wordpress/url';

import { NEW_TAB_REL, NEW_TAB_TARGET, NOFOLLOW_REL } from './constants';

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
    let newLinkTarget: string | undefined;
    let updatedRel = rel;

    if (opensInNewTab) {
        newLinkTarget = NEW_TAB_TARGET;
        updatedRel = updatedRel?.includes(NEW_TAB_REL)
            ? updatedRel
            : updatedRel + ` ${NEW_TAB_REL}`;
    } else {
        const relRegex = new RegExp(`\\b${NEW_TAB_REL}\\s*`, 'g');
        updatedRel = updatedRel?.replace(relRegex, '').trim();
    }

    if (nofollow) {
        updatedRel = updatedRel?.includes(NOFOLLOW_REL)
            ? updatedRel
            : (updatedRel + ` ${NOFOLLOW_REL}`).trim();
    } else {
        const relRegex = new RegExp(`\\b${NOFOLLOW_REL}\\s*`, 'g');
        updatedRel = updatedRel?.replace(relRegex, '').trim();
    }

    return {
        url: prependHTTPS(url),
        linkTarget: newLinkTarget,
        rel: updatedRel || undefined,
    };
}
