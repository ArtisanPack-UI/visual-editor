import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { Template } from '../src/Template';
import { registerCoreBlocks } from '../src/blocks/registerCoreBlocks';
import type { PatternRecord } from '../src/patterns';
import { resetBlockRegistry } from '../src/registry';
import type { TemplateRecord } from '../src/templateParts';
import type { Block } from '../src/types';
import { makeBlock, normalizeHtml } from './helpers';

function paragraph(text: string, clientId = 'p-cid'): Block {
    return makeBlock('core/paragraph', { content: text }, [], clientId);
}

function patternRef(ref: number, clientId = 'pat-cid'): Block {
    return makeBlock('core/block', { ref }, [], clientId);
}

afterEach(() => {
    resetBlockRegistry();
    registerCoreBlocks();
});

describe('BlockTree synced-pattern inlining', () => {
    it('renders a referenced pattern wrapped in a wp-block-block element', () => {
        const patterns: PatternRecord[] = [
            { id: 1, blocks: [paragraph('Hero copy', 'h-1')] },
        ];

        const wrapper = mount(BlockTree, {
            props: { tree: [patternRef(1)], patterns },
        });
        const html = normalizeHtml(wrapper.html());

        expect(html).toContain('<div class="wp-block-block"');
        expect(html).toContain('data-ve-pattern-ref="1"');
        expect(html).toContain('<p class="wp-block-paragraph">Hero copy</p>');
    });

    it('wraps a missing reference as an empty element with no children', () => {
        const wrapper = mount(BlockTree, {
            props: { tree: [patternRef(9999)], patterns: [] as PatternRecord[] },
        });

        const el = wrapper.find('.wp-block-block');

        expect(el.exists()).toBe(true);
        expect(el.element.children.length).toBe(0);
    });

    it('does not run the inliner when no patterns prop is supplied', () => {
        const wrapper = mount(BlockTree, {
            props: { tree: [patternRef(1)] },
        });

        const el = wrapper.find('.wp-block-block');

        expect(el.exists()).toBe(true);
        expect(el.element.children.length).toBe(0);
    });

    it('runs the inliner for an explicit empty patterns array so missing references are flagged', () => {
        const wrapper = mount(BlockTree, {
            props: { tree: [patternRef(1)], patterns: [] as PatternRecord[] },
        });

        const el = wrapper.find('.wp-block-block');

        expect(el.attributes('data-ve-resolution-error')).toBe('not-found');
    });

    it('detects cycles inside the inlined tree', () => {
        const patterns: PatternRecord[] = [
            { id: 1, blocks: [patternRef(2, 'b-ref')] },
            { id: 2, blocks: [patternRef(1, 'a-ref')] },
        ];

        const wrapper = mount(BlockTree, {
            props: { tree: [patternRef(1, 'a-root')], patterns },
        });

        const wrappers = wrapper.findAll('.wp-block-block');

        expect(wrappers.length).toBeGreaterThanOrEqual(3);
        expect(wrapper.find('[data-ve-resolution-error="cycle"]').exists()).toBe(true);
    });
});

describe('Template component synced-pattern inlining', () => {
    it('forwards the patterns prop to the inner BlockTree', () => {
        const templates: TemplateRecord[] = [
            {
                slug: 'page-about',
                theme: 'artisanpack-base',
                blocks: [patternRef(1), paragraph('Body', 'b-1')],
            },
        ];
        const patterns: PatternRecord[] = [
            { id: 1, blocks: [paragraph('Hero', 'h-1')] },
        ];

        const wrapper = mount(Template, {
            props: {
                slug: 'page-about',
                theme: 'artisanpack-base',
                templates,
                templateParts: [],
                patterns,
            },
        });
        const html = normalizeHtml(wrapper.html());

        expect(html).toContain('data-ve-pattern-ref="1"');
        expect(html).toContain('<p class="wp-block-paragraph">Hero</p>');
        expect(html).toContain('<p class="wp-block-paragraph">Body</p>');
    });
});
