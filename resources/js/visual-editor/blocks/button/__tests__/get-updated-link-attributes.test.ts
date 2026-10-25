/**
 * Tests for the Button block's link attribute updater.
 */

import { describe, it, expect } from 'vitest';

import { getUpdatedLinkAttributes } from '../get-updated-link-attributes';

describe('getUpdatedLinkAttributes', () => {
    it('adds the new-tab tokens and target', () => {
        expect(
            getUpdatedLinkAttributes({ url: 'https://example.com', opensInNewTab: true })
        ).toEqual({
            url: 'https://example.com',
            linkTarget: '_blank',
            rel: 'noreferrer noopener',
        });
    });

    it('removes the new-tab tokens whatever order they were stored in', () => {
        expect(
            getUpdatedLinkAttributes({
                url: 'https://example.com',
                rel: 'noopener sponsored noreferrer',
                opensInNewTab: false,
            })
        ).toEqual({ url: 'https://example.com', linkTarget: undefined, rel: 'sponsored' });
    });

    it('does not duplicate tokens when re-enabling a new tab', () => {
        expect(
            getUpdatedLinkAttributes({
                url: 'https://example.com',
                rel: 'noopener noreferrer',
                opensInNewTab: true,
            }).rel
        ).toBe('noreferrer noopener');
    });

    it('keeps a nofollow typed into Link relation when the popover has no nofollow toggle', () => {
        expect(
            getUpdatedLinkAttributes({ url: 'https://example.com/new', rel: 'nofollow' }).rel
        ).toBe('nofollow');
    });

    it('drops nofollow when the caller turns it off', () => {
        expect(
            getUpdatedLinkAttributes({ url: 'https://example.com', rel: 'nofollow', nofollow: false }).rel
        ).toBeUndefined();
    });

    it.each(['{{site.facebook}}', '{{post.author_url}}'])(
        'leaves the Dynamic Content href %s without an https:// prefix',
        (url) => {
            expect(getUpdatedLinkAttributes({ url }).url).toBe(url);
        }
    );

    it('still prefixes a bare domain with https://', () => {
        expect(getUpdatedLinkAttributes({ url: 'example.com' }).url).toBe('https://example.com');
    });
});
