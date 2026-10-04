/**
 * Variation surface tests for `artisanpack/heading`.
 *
 * The h1–h6 variations are the only heading-level picker since upstream
 * moved level selection out of `edit` (#825), so the fork must ship all
 * six and register them on the block.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    sprintf: (format: string, ...args: unknown[]) =>
        format.replace('%d', String(args[0])),
}));

// The entrypoint's other modules pull `@wordpress/block-editor` and
// `@wordpress/blocks`, which can't load here; only `variations` is under test.
vi.mock('../edit', () => ({ default: () => null }));
vi.mock('../save', () => ({ default: () => null }));
vi.mock('../deprecated', () => ({ default: [] }));
vi.mock('../transforms', () => ({ default: {} }));

import variations from '../variations';
import headingBlock from '../index';

describe('artisanpack/heading variations', () => {
    it('ships one variation per heading level', () => {
        expect(variations.map((v) => v.name)).toEqual(['h1', 'h2', 'h3', 'h4', 'h5', 'h6']);
    });

    it('sets the level attribute, title, and keyword for each level', () => {
        variations.forEach((variation, index) => {
            const level = index + 1;

            expect(variation.attributes).toEqual({ level });
            expect(variation.title).toBe(`Heading ${level}`);
            expect(variation.keywords).toEqual([`h${level}`]);
            expect(variation.icon).toBeDefined();
        });
    });

    it('scopes variations to the block toolbar and transforms, not the inserter', () => {
        for (const variation of variations) {
            expect(variation.scope).toEqual(['block', 'transform']);
        }
    });

    it('marks exactly the matching level as active', () => {
        const h3 = variations.find((v) => v.name === 'h3');

        expect(h3?.isActive({ level: 3 })).toBe(true);
        expect(h3?.isActive({ level: 2 })).toBe(false);
        expect(h3?.isActive({})).toBe(false);
    });

    it('is registered through the block entrypoint', () => {
        expect(headingBlock.variations).toBe(variations);
    });
});
