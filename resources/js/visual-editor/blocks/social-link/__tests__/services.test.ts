/**
 * Guards the generated Social Icons registries (#1023).
 */

import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
}));

import {
    AUTHOR_SOCIAL_PLATFORM_SLUGS,
    SHARE_SOCIAL_PLATFORM_SLUGS,
    authorSocialPlatforms,
    shareSocialPlatforms,
    socialLinkServices,
} from '../services';
import getSocialLinkVariations from '../variations';
import {
    SOCIAL_LINK_SERVICES as REACT_SERVICES,
    getAuthorIcon as getReactAuthorIcon,
    getShareIcon as getReactShareIcon,
} from '../../../../../../packages/visual-editor-renderer-react/src/blocks/artisanpack/socialLinkServices';
import {
    SOCIAL_LINK_SERVICES as VUE_SERVICES,
    getAuthorIcon as getVueAuthorIcon,
    getShareIcon as getVueShareIcon,
} from '../../../../../../packages/visual-editor-renderer-vue/src/blocks/artisanpack/socialLinkServices';

const PACKAGE_ROOT = resolve(__dirname, '../../../../../..');

describe('social link service registries', () => {
    it('are in sync with scripts/sync-social-link-services.mjs', () => {
        expect(() =>
            execFileSync('node', ['scripts/sync-social-link-services.mjs', '--check'], {
                cwd: PACKAGE_ROOT,
                stdio: 'pipe',
            })
        ).not.toThrow();
    });

    it('lists every WordPress social-link service with a glyph', () => {
        const services = socialLinkServices();

        expect(services).toHaveLength(48);
        expect(services[0].slug).toBe('wordpress');
        for (const service of services) {
            expect(service.viewBox).toMatch(/^0 0 \d+ \d+$/);
            expect(service.path.length).toBeGreaterThan(10);
        }
    });

    it('matches the React and Vue renderer registries', () => {
        const editor = Object.fromEntries(
            socialLinkServices().map(({ slug, label, viewBox, path }) => [slug, { label, viewBox, path }])
        );

        expect(REACT_SERVICES).toEqual(editor);
        expect(VUE_SERVICES).toEqual(editor);
    });
});

describe('author and share platforms', () => {
    const service = (slug: string) => socialLinkServices().find((entry) => entry.slug === slug);

    it('keeps the saved slugs and picker order', () => {
        expect(authorSocialPlatforms().map(({ slug }) => slug)).toEqual([...AUTHOR_SOCIAL_PLATFORM_SLUGS]);
        expect(shareSocialPlatforms().map(({ slug }) => slug)).toEqual([...SHARE_SOCIAL_PLATFORM_SLUGS]);
        expect(AUTHOR_SOCIAL_PLATFORM_SLUGS).toEqual(['facebook', 'twitter', 'mastodon', 'instagram', 'tumblr', 'email', 'website']);
        expect(SHARE_SOCIAL_PLATFORM_SLUGS).toEqual(['facebook', 'twitter', 'linkedin', 'mastodon', 'reddit', 'pinterest', 'email', 'copyLink']);
    });

    it('draws each platform with its service glyph', () => {
        const twitter = authorSocialPlatforms().find(({ slug }) => slug === 'twitter');
        const email = shareSocialPlatforms().find(({ slug }) => slug === 'email');
        const copyLink = shareSocialPlatforms().find(({ slug }) => slug === 'copyLink');
        const website = authorSocialPlatforms().find(({ slug }) => slug === 'website');

        expect(twitter).toMatchObject({ label: 'X', path: service('x')?.path, viewBox: service('x')?.viewBox });
        expect(email).toMatchObject({ label: 'Email', path: service('mail')?.path });
        expect(copyLink).toMatchObject({ label: 'Copy link', path: service('chain')?.path });
        expect(website).toMatchObject({ label: 'Website', path: service('chain')?.path });
    });

    it('matches the React and Vue renderers', () => {
        for (const { slug, ...icon } of authorSocialPlatforms()) {
            expect(getReactAuthorIcon(slug)).toEqual(icon);
            expect(getVueAuthorIcon(slug)).toEqual(icon);
        }

        for (const { slug, ...icon } of shareSocialPlatforms()) {
            expect(getReactShareIcon(slug)).toEqual(icon);
            expect(getVueShareIcon(slug)).toEqual(icon);
        }
    });

    it.each(['unknown', 'constructor', 'reddit'])('has no author chip for %s', (slug) => {
        expect(getReactAuthorIcon(slug)).toBeUndefined();
        expect(getVueAuthorIcon(slug)).toBeUndefined();
    });

    it.each(['unknown', 'constructor', 'website'])('has no share chip for %s', (slug) => {
        expect(getReactShareIcon(slug)).toBeUndefined();
        expect(getVueShareIcon(slug)).toBeUndefined();
    });
});

describe('social link variations', () => {
    const variations = getSocialLinkVariations();

    it('makes WordPress the default and gives every service a variation', () => {
        expect(variations).toHaveLength(48);
        expect(variations[0]).toMatchObject({ name: 'wordpress', isDefault: true });
        expect(variations.filter((variation) => variation.isDefault)).toHaveLength(1);
    });

    it('activates the variation that matches the service', () => {
        const mail = variations.find((variation) => variation.name === 'mail');
        const isActive = mail?.isActive as (a: Record<string, unknown>, b: Record<string, unknown>) => boolean;

        expect(mail?.keywords).toEqual(['email', 'e-mail']);
        expect(isActive({ service: 'mail' }, { service: 'mail' })).toBe(true);
        expect(isActive({ service: 'x' }, { service: 'mail' })).toBe(false);
    });
});
