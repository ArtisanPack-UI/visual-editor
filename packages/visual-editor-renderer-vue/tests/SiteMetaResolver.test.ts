import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { resetBlockRegistry } from '../src/registry';
import { registerCoreBlocks } from '../src/blocks/registerCoreBlocks';
import { setDefaultSiteMeta } from '../src/siteMeta';
import type { SiteMeta } from '../src/siteMeta';
import { makeBlock, normalizeHtml } from './helpers';
import type { Block } from '../src/types';

function renderTree(tree: unknown, siteMeta?: SiteMeta | null): string {
    const wrapper = mount(BlockTree, {
        props: {
            tree: tree as Block[] | string | null | undefined,
            siteMeta,
        },
    });

    return normalizeHtml(wrapper.html());
}

afterEach(() => {
    resetBlockRegistry();
    registerCoreBlocks();
    setDefaultSiteMeta(null);
});

describe('site-meta resolution (Vue)', () => {
    it('stamps siteMeta prop values onto core/site-* blocks', () => {
        const tree = [
            makeBlock('core/site-title'),
            makeBlock('core/site-tagline'),
            makeBlock('core/site-logo'),
        ];

        const rendered = renderTree(tree, {
            title: 'From Prop',
            description: 'Tagline From Prop',
            url: 'https://prop.example',
            logoUrl: 'https://prop.example/logo.svg',
        });

        expect(rendered).toContain('From Prop');
        expect(rendered).toContain('href="https://prop.example"');
        expect(rendered).toContain('Tagline From Prop');
        expect(rendered).toContain('src="https://prop.example/logo.svg"');
    });

    it('falls back to setDefaultSiteMeta when no prop is supplied', () => {
        setDefaultSiteMeta({
            title: 'From Default',
            url: 'https://default.example',
        });

        const tree = [makeBlock('core/site-title')];

        expect(renderTree(tree)).toContain('From Default');
    });

    it('lets the per-render siteMeta prop win over the global default', () => {
        setDefaultSiteMeta({ title: 'Default Title' });

        const tree = [makeBlock('core/site-title')];

        expect(renderTree(tree, { title: 'Prop Title' })).toContain('Prop Title');
        expect(renderTree(tree, { title: 'Prop Title' })).not.toContain('Default Title');
    });

    it('lets pre-stamped block attributes win over the resolver values', () => {
        const tree = [
            makeBlock('core/site-title', { _resolvedSiteTitle: 'Pre-stamped' }),
        ];

        expect(renderTree(tree, { title: 'Prop Title' })).toContain('Pre-stamped');
    });

    it('treats explicit null as a disable signal that bypasses the bootstrap default', () => {
        setDefaultSiteMeta({ title: 'Should Not Appear' });

        const tree = [makeBlock('core/site-title')];

        // Explicit null tells the resolver "do not stamp anything,
        // including the bootstrap default" — so the block renders
        // empty rather than picking up the fallback.
        expect(renderTree(tree, null)).not.toContain('Should Not Appear');
    });

    it('leaves non-site-meta blocks untouched', () => {
        const tree = [makeBlock('core/paragraph', { content: 'Hello' })];

        expect(renderTree(tree, { title: 'Should Not Appear' })).not.toContain(
            'Should Not Appear'
        );
    });

    it('stamps blocks brought into the tree via template-part inlining', () => {
        const tree = [makeBlock('core/template-part', { slug: 'header', theme: 'a' })];

        const wrapper = mount(BlockTree, {
            props: {
                tree,
                templateParts: [
                    {
                        slug: 'header',
                        theme: 'a',
                        blocks: [makeBlock('core/site-title')],
                    },
                ],
                siteMeta: { title: 'Inlined Site' },
            },
        });

        expect(normalizeHtml(wrapper.html())).toContain('Inlined Site');
    });
});
