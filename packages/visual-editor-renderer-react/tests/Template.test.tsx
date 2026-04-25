import { render } from '@testing-library/react';
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
        const tree = [partRef('header')];

        const { container } = render(<BlockTree tree={tree} templateParts={parts} />);
        const html = normalizeHtml(container.innerHTML);

        expect(html).toContain('<div class="wp-block-template-part wp-block-template-part--header"');
        expect(html).toContain('data-ve-template-part="header"');
        expect(html).toContain('<p class="wp-block-paragraph">Header text</p>');
    });

    it('wraps a missing reference as an empty element with no children', () => {
        const tree = [partRef('never-created')];

        const { container } = render(<BlockTree tree={tree} templateParts={[]} />);

        const wrapper = container.querySelector('[data-ve-template-part="never-created"]');

        expect(wrapper).not.toBeNull();
        expect(wrapper?.children.length).toBe(0);
    });

    it('does not run the inliner when no parts are supplied', () => {
        const tree = [partRef('header')];

        const { container } = render(<BlockTree tree={tree} />);

        // The template-part block still renders; just empty (no inlined children).
        const wrapper = container.querySelector('[data-ve-template-part="header"]');

        expect(wrapper).not.toBeNull();
        expect(wrapper?.children.length).toBe(0);
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

        const { container } = render(
            <Template
                slug="page-about"
                theme="artisanpack-base"
                templates={templates}
                templateParts={parts}
            />
        );
        const html = normalizeHtml(container.innerHTML);

        expect(html).toContain('data-ve-template="page-about"');
        expect(html).toContain('<p class="wp-block-paragraph">Header content</p>');
        expect(html).toContain('<p class="wp-block-paragraph">Body</p>');
    });

    it('walks the fallback chain (page-about → page → index)', () => {
        const templates: TemplateRecord[] = [
            { slug: 'page', theme: 'artisanpack-base', blocks: [paragraph('Default page', 'p-1')] },
            { slug: 'index', theme: 'artisanpack-base', blocks: [paragraph('Catch-all', 'i-1')] },
        ];

        const { container } = render(
            <Template
                slug="page-about"
                theme="artisanpack-base"
                templates={templates}
                templateParts={parts}
            />
        );
        const html = normalizeHtml(container.innerHTML);

        expect(html).toContain('data-ve-matched-template="page"');
        expect(html).toContain('Default page');
        expect(html).not.toContain('Catch-all');
    });

    it('renders an empty wrapper when nothing in the chain matches', () => {
        const { container } = render(
            <Template slug="single-post" theme="artisanpack-base" templates={[]} templateParts={[]} />
        );

        const wrapper = container.querySelector('[data-ve-template="single-post"]');

        expect(wrapper).not.toBeNull();
        expect(wrapper?.children.length).toBe(0);
    });

    it('exposes the fallback chain via a data attribute in development', () => {
        const { container } = render(
            <Template slug="single-post" theme="artisanpack-base" templates={[]} templateParts={[]} />
        );

        const wrapper = container.querySelector('[data-ve-template="single-post"]');

        // jsdom defaults NODE_ENV to 'test', which our isDevelopment() helper
        // treats as non-production — so the dev-only diagnostics are rendered.
        expect(wrapper?.getAttribute('data-ve-resolution-error')).toBe('no-matching-template');
        expect(wrapper?.getAttribute('data-ve-fallback-chain')).toBe('single-post,single,index');
    });
});
