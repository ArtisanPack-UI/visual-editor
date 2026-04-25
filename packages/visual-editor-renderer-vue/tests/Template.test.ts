import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { Template } from '../src/Template';
import { resetBlockRegistry } from '../src/registry';
import { registerCoreBlocks } from '../src/blocks/registerCoreBlocks';
import type { TemplatePartRecord, TemplateRecord } from '../src/templateParts';
import type { Block } from '../src/types';
import { makeBlock, normalizeHtml } from './helpers';

function paragraph(text: string, clientId = 'p-cid'): Block {
    return makeBlock('core/paragraph', { content: text }, [], clientId);
}

function partRef(slug: string, theme = 'artisanpack-base', clientId = 'tp-cid'): Block {
    return makeBlock('core/template-part', { slug, theme }, [], clientId);
}

afterEach(() => {
    resetBlockRegistry();
    registerCoreBlocks();
});

describe('BlockTree template-part inlining', () => {
    it('renders a referenced part wrapped in a wp-block-template-part element', () => {
        const parts: TemplatePartRecord[] = [
            { slug: 'header', theme: 'artisanpack-base', blocks: [paragraph('Header text', 'h-1')] },
        ];

        const wrapper = mount(BlockTree, {
            props: { tree: [partRef('header')], templateParts: parts },
        });
        const html = normalizeHtml(wrapper.html());

        expect(html).toContain('<div class="wp-block-template-part wp-block-template-part--header"');
        expect(html).toContain('data-ve-template-part="header"');
        expect(html).toContain('<p class="wp-block-paragraph">Header text</p>');
    });

    it('wraps a missing reference as an empty element with no children', () => {
        const wrapper = mount(BlockTree, {
            props: { tree: [partRef('never-created')], templateParts: [] as TemplatePartRecord[] },
        });

        const el = wrapper.find('[data-ve-template-part="never-created"]');

        expect(el.exists()).toBe(true);
        expect(el.element.children.length).toBe(0);
    });

    it('does not run the inliner when no parts are supplied', () => {
        const wrapper = mount(BlockTree, {
            props: { tree: [partRef('header')] },
        });

        const el = wrapper.find('[data-ve-template-part="header"]');

        expect(el.exists()).toBe(true);
        expect(el.element.children.length).toBe(0);
    });

    it('runs the inliner for an explicit empty templateParts array so missing references are flagged', () => {
        const wrapper = mount(BlockTree, {
            props: { tree: [partRef('header')], templateParts: [] as TemplatePartRecord[] },
        });

        const el = wrapper.find('[data-ve-template-part="header"]');

        // Empty array means "no parts supplied" — the inliner should mark
        // the reference unresolved so dev diagnostics surface, rather than
        // silently rendering an empty wrapper indistinguishable from
        // "templateParts not configured at all."
        expect(el.attributes('data-ve-resolution-error')).toBe('not-found');
    });
});

describe('Template component', () => {
    const parts: TemplatePartRecord[] = [
        { slug: 'header', theme: 'artisanpack-base', blocks: [paragraph('Header content', 'h-1')] },
    ];

    it('renders the matched template with parts inlined', () => {
        const templates: TemplateRecord[] = [
            {
                slug: 'page-about',
                theme: 'artisanpack-base',
                blocks: [partRef('header'), paragraph('Body', 'b-1')],
            },
        ];

        const wrapper = mount(Template, {
            props: {
                slug: 'page-about',
                theme: 'artisanpack-base',
                templates,
                templateParts: parts,
            },
        });
        const html = normalizeHtml(wrapper.html());

        expect(html).toContain('data-ve-template="page-about"');
        expect(html).toContain('<p class="wp-block-paragraph">Header content</p>');
        expect(html).toContain('<p class="wp-block-paragraph">Body</p>');
    });

    it('walks the fallback chain (page-about → page → index)', () => {
        const templates: TemplateRecord[] = [
            { slug: 'page', theme: 'artisanpack-base', blocks: [paragraph('Default page', 'p-1')] },
            { slug: 'index', theme: 'artisanpack-base', blocks: [paragraph('Catch-all', 'i-1')] },
        ];

        const wrapper = mount(Template, {
            props: { slug: 'page-about', theme: 'artisanpack-base', templates, templateParts: parts },
        });
        const html = normalizeHtml(wrapper.html());

        expect(html).toContain('data-ve-matched-template="page"');
        expect(html).toContain('Default page');
        expect(html).not.toContain('Catch-all');
    });

    it('renders an empty wrapper when nothing in the chain matches', () => {
        const wrapper = mount(Template, {
            props: { slug: 'single-post', theme: 'artisanpack-base', templates: [], templateParts: [] },
        });

        const el = wrapper.find('[data-ve-template="single-post"]');

        expect(el.exists()).toBe(true);
        expect(el.element.children.length).toBe(0);
    });

    it('exposes the fallback chain via a data attribute in development', () => {
        const wrapper = mount(Template, {
            props: { slug: 'single-post', theme: 'artisanpack-base', templates: [], templateParts: [] },
        });

        const el = wrapper.find('[data-ve-template="single-post"]');

        expect(el.attributes('data-ve-resolution-error')).toBe('no-matching-template');
        expect(el.attributes('data-ve-fallback-chain')).toBe('single-post,single,index');
    });
});
