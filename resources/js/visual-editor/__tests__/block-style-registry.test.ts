import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const registerBlockStyle = vi.fn();
const getBlockStyles = vi.fn<(name: string) => Array<{ name: string }> | undefined>();

vi.mock('@wordpress/blocks', () => ({
    registerBlockStyle: (...args: unknown[]) => registerBlockStyle(...args),
    store: 'core/blocks',
}));

vi.mock('@wordpress/data', () => ({
    select: () => ({ getBlockStyles: (name: string) => getBlockStyles(name) }),
}));

import {
    blockNamesFor,
    getThemeBlockStyles,
    parseBlockStyles,
    refreshThemeBlockStyles,
    registerThemeBlockStyles,
} from '../block-style-registry';

describe('block style registry (#876)', () => {
    beforeEach(() => {
        registerBlockStyle.mockReset();
        getBlockStyles.mockReset();
        refreshThemeBlockStyles();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('parses the payload and drops malformed entries', () => {
        expect(
            parseBlockStyles(
                JSON.stringify([
                    { name: 'core/group', slug: 'card', label: 'Card', isDefault: false },
                    { name: 'core/button', slug: 'pill', label: '', isDefault: true },
                    { name: 'group', slug: 'x', label: 'X' },
                    { name: 'core/group', slug: 'Bad Slug', label: 'X' },
                    'nope',
                ]),
            ),
        ).toEqual([
            { name: 'core/group', slug: 'card', label: 'Card', isDefault: false },
            { name: 'core/button', slug: 'pill', label: 'pill', isDefault: true },
        ]);
    });

    it('returns nothing for empty or invalid JSON', () => {
        expect(parseBlockStyles('')).toEqual([]);
        expect(parseBlockStyles('{nope')).toEqual([]);
        expect(parseBlockStyles('{"a":1}')).toEqual([]);
    });

    it('maps core and artisanpack names to each other', () => {
        expect(blockNamesFor('core/group')).toEqual(['core/group', 'artisanpack/group']);
        expect(blockNamesFor('artisanpack/button')).toEqual(['artisanpack/button', 'core/button']);
        expect(blockNamesFor('acme/hero')).toEqual(['acme/hero']);
    });

    it('reads the post editor mount, then the site editor mount', () => {
        document.body.innerHTML = `<div data-ap-site-editor data-block-styles='[{"name":"core/quote","slug":"neon","label":"Neon"}]'></div>`;

        expect(getThemeBlockStyles()).toEqual([
            { name: 'core/quote', slug: 'neon', label: 'Neon', isDefault: false },
        ]);
    });

    it('registers each variation under both block names, skipping existing slugs', () => {
        getBlockStyles.mockImplementation((name) =>
            name === 'artisanpack/button' ? [{ name: 'outline' }] : [],
        );

        registerThemeBlockStyles([
            { name: 'core/button', slug: 'outline', label: 'Outline', isDefault: false },
            { name: 'core/group', slug: 'card', label: 'Card', isDefault: true },
        ]);

        expect(registerBlockStyle.mock.calls).toEqual([
            ['core/button', { name: 'outline', label: 'Outline', isDefault: false }],
            ['core/group', { name: 'card', label: 'Card', isDefault: true }],
            ['artisanpack/group', { name: 'card', label: 'Card', isDefault: true }],
        ]);
    });
});
