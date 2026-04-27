import { defineComponent, h } from 'vue';
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

const noopRenderer = defineComponent({
    name: 'NoopRenderer',
    setup() {
        return () => h('div');
    },
});

afterEach(() => {
    resetBlockRegistry();
    registerCoreBlocks();
});

describe('registerBlockRenderer', () => {
    it('rejects empty block names', () => {
        expect(() => registerBlockRenderer('   ', noopRenderer)).toThrow(/name cannot be empty/);
    });

    it('trims whitespace in block names', () => {
        registerBlockRenderer('  acme/trimmed  ', noopRenderer);

        expect(getBlockRenderer('acme/trimmed')).toBe(noopRenderer);
        expect(hasBlockRenderer('acme/trimmed')).toBe(true);
    });

    it('overrides an existing renderer registration', () => {
        const first = defineComponent({
            setup() {
                return () => h('div');
            },
        });
        const second = defineComponent({
            setup() {
                return () => h('div');
            },
        });

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

    it('registers every E4 post-, site-, and navigation block', () => {
        const names = getRegisteredBlockNames();

        for (const block of [
            'core/post-title',
            'core/post-content',
            'core/post-excerpt',
            'core/post-date',
            'core/post-author',
            'core/post-featured-image',
            'core/site-title',
            'core/site-tagline',
            'core/site-logo',
            'core/navigation',
            'core/navigation-link',
            'core/navigation-submenu',
        ]) {
            expect(names).toContain(block);
        }
    });

    it('allows unregistering a renderer', () => {
        registerBlockRenderer('acme/temp', noopRenderer);
        expect(hasBlockRenderer('acme/temp')).toBe(true);

        unregisterBlockRenderer('acme/temp');
        expect(hasBlockRenderer('acme/temp')).toBe(false);
    });
});
