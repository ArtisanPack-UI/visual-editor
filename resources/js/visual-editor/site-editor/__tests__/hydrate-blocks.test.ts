import { describe, expect, it, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    parse: (): unknown[] => [],
    getBlockType: (): undefined => undefined,
}));

import { ensureEditorInstances, hydrateBlocks } from '../hydrate-blocks';

describe('ensureEditorInstances (#809)', () => {
    it('mints clientId and isValid on server-parsed blocks, recursively', () => {
        const [block] = ensureEditorInstances([
            {
                name: 'core/navigation',
                attributes: { overlayMenu: 'never' },
                innerBlocks: [{ name: 'core/navigation-link', attributes: {}, innerBlocks: [] }],
            },
        ]);

        expect(typeof block.clientId).toBe('string');
        expect(block.clientId).not.toBe('');
        expect(block.isValid).toBe(true);
        expect(block.attributes).toEqual({ overlayMenu: 'never' });
        expect(typeof block.innerBlocks[0].clientId).toBe('string');
        expect(block.innerBlocks[0].clientId).not.toBe(block.clientId);
    });

    it('leaves editor-saved instances untouched', () => {
        const saved = {
            name: 'core/navigation',
            clientId: 'saved-id',
            isValid: true,
            attributes: { ref: 1 },
            innerBlocks: [],
        };

        const [block] = ensureEditorInstances([saved]);

        expect(block.clientId).toBe('saved-id');
        expect(block.attributes).toEqual({ ref: 1 });
    });

    it('coerces PHP empty-array attributes to an object', () => {
        const [block] = ensureEditorInstances([
            { name: 'core/navigation', attributes: [], innerBlocks: [] },
        ]);

        expect(block.attributes).toEqual({});
    });

    it('drops malformed entries instead of throwing', () => {
        expect(
            ensureEditorInstances([null, 'x', { attributes: {} }, { name: 'core/navigation' }]),
        ).toHaveLength(1);
    });

    it('is applied on the content.blocks path of hydrateBlocks', () => {
        const [block] = hydrateBlocks({
            raw: '',
            blocks: [{ name: 'core/navigation', attributes: {}, innerBlocks: [] }],
        });

        expect(typeof block.clientId).toBe('string');
    });
});
