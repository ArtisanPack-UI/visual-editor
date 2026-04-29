import { render } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { resetBlockRegistry } from '../src/registry';
import { registerCoreBlocks } from '../src/blocks/registerCoreBlocks';
import { setDefaultSiteMeta } from '../src/siteMeta';
import { makeBlock, normalizeHtml } from './helpers';

function renderTree(
    tree: unknown,
    siteMeta?: Parameters<typeof BlockTree>[0]['siteMeta']
): string {
    const { container } = render(
        <BlockTree
            tree={tree as Parameters<typeof BlockTree>[0]['tree']}
            siteMeta={siteMeta}
        />
    );

    return normalizeHtml(container.innerHTML);
}

afterEach(() => {
    resetBlockRegistry();
    registerCoreBlocks();
    setDefaultSiteMeta(null);
});

describe('site-meta resolution', () => {
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

    it('leaves non-site-meta blocks untouched', () => {
        const tree = [
            makeBlock('core/paragraph', { content: 'Hello' }),
        ];

        expect(renderTree(tree, { title: 'Should Not Appear' })).not.toContain(
            'Should Not Appear'
        );
    });

    it('stamps blocks brought into the tree via template-part inlining', () => {
        const tree = [makeBlock('core/template-part', { slug: 'header', theme: 'a' })];

        const { container } = render(
            <BlockTree
                tree={tree}
                templateParts={[
                    {
                        slug: 'header',
                        theme: 'a',
                        blocks: [makeBlock('core/site-title')],
                    },
                ]}
                siteMeta={{ title: 'Inlined Site' }}
            />
        );

        expect(normalizeHtml(container.innerHTML)).toContain('Inlined Site');
    });
});
