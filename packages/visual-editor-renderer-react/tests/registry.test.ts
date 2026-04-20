import { afterEach, describe, expect, it } from 'vitest';
import {
    getBlockRenderer,
    getRegisteredBlockNames,
    hasBlockRenderer,
    registerBlockRenderer,
    resetBlockRegistry,
    unregisterBlockRenderer,
} from '../src/registry';
import { registerCoreBlocks } from '../src/blocks/registerCoreBlocks';

afterEach(() => {
    resetBlockRegistry();
    registerCoreBlocks();
});

describe('registerBlockRenderer', () => {
    it('rejects empty block names', () => {
        expect(() => registerBlockRenderer('   ', () => null)).toThrow(/name cannot be empty/);
    });

    it('trims whitespace in block names', () => {
        const renderer = () => null;

        registerBlockRenderer('  acme/trimmed  ', renderer);

        expect(getBlockRenderer('acme/trimmed')).toBe(renderer);
        expect(hasBlockRenderer('acme/trimmed')).toBe(true);
    });

    it('overrides an existing renderer registration', () => {
        const first = () => null;
        const second = () => null;

        registerBlockRenderer('acme/replace', first);
        registerBlockRenderer('acme/replace', second);

        expect(getBlockRenderer('acme/replace')).toBe(second);
    });

    it('includes every core block after registerCoreBlocks', () => {
        const names = getRegisteredBlockNames();

        expect(names).toContain('core/paragraph');
        expect(names).toContain('core/heading');
        expect(names).toContain('core/image');
        expect(names).toContain('core/button');
        expect(names).toContain('core/cover');
        expect(names).toContain('core/separator');
    });

    it('allows unregistering a renderer', () => {
        registerBlockRenderer('acme/temp', () => null);
        expect(hasBlockRenderer('acme/temp')).toBe(true);

        unregisterBlockRenderer('acme/temp');
        expect(hasBlockRenderer('acme/temp')).toBe(false);
    });
});
