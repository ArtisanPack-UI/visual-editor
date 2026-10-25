/**
 * Guards the generated Social Icons registries (#1023).
 */

import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/i18n', () => ({
    _x: (text: string) => text,
}));

import { socialLinkServices } from '../services';
import getSocialLinkVariations from '../variations';
import { SOCIAL_LINK_SERVICES as REACT_SERVICES } from '../../../../../../packages/visual-editor-renderer-react/src/blocks/artisanpack/socialLinkServices';
import { SOCIAL_LINK_SERVICES as VUE_SERVICES } from '../../../../../../packages/visual-editor-renderer-vue/src/blocks/artisanpack/socialLinkServices';

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
