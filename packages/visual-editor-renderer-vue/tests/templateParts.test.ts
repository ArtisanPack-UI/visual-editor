import { describe, expect, it } from 'vitest';

import {
    DEFAULT_MAX_TEMPLATE_PART_DEPTH,
    findTemplate,
    inlineTemplateParts,
    resolveTemplate,
    templateFallbackChain,
} from '../src/templateParts';
import type { Block } from '../src/types';

function paragraph(text: string, clientId = 'p-cid'): Block {
    return {
        clientId,
        name: 'core/paragraph',
        attributes: { content: text },
        innerBlocks: [],
    };
}

function partRef(slug: string, theme = 'artisanpack-base', clientId = 'tp-cid'): Block {
    return {
        clientId,
        name: 'core/template-part',
        attributes: { slug, theme },
        innerBlocks: [],
    };
}

describe('templateFallbackChain', () => {
    it('expands single-{slug} → single → index', () => {
        expect(templateFallbackChain('single-post')).toEqual(['single-post', 'single', 'index']);
    });

    it('expands page-{slug} → page → index', () => {
        expect(templateFallbackChain('page-about')).toEqual(['page-about', 'page', 'index']);
    });

    it('falls straight to index for unspecialized slugs', () => {
        expect(templateFallbackChain('archive')).toEqual(['archive', 'index']);
    });

    it('returns just [index] for index itself', () => {
        expect(templateFallbackChain('index')).toEqual(['index']);
    });
});

describe('findTemplate / resolveTemplate', () => {
    const templates = [
        { slug: 'index', theme: 'artisanpack-base', blocks: [paragraph('Index')] },
        { slug: 'page', theme: 'artisanpack-base', blocks: [paragraph('Page')] },
        { slug: 'page-about', theme: 'artisanpack-base', blocks: [paragraph('About')] },
    ];

    it('returns the exact match when present', () => {
        expect(resolveTemplate(templates, 'page-about', 'artisanpack-base')?.slug).toBe('page-about');
    });

    it('falls back to page when page-{slug} is missing', () => {
        expect(resolveTemplate(templates, 'page-contact', 'artisanpack-base')?.slug).toBe('page');
    });

    it('falls back to index when page is missing too', () => {
        const subset = [{ slug: 'index', blocks: [paragraph('idx')] }];

        expect(resolveTemplate(subset, 'page-about')?.slug).toBe('index');
    });

    it('returns undefined when nothing matches', () => {
        expect(resolveTemplate([], 'archive')).toBeUndefined();
    });

    it('returns templates regardless of theme when no theme is supplied', () => {
        expect(findTemplate(templates, 'index')?.slug).toBe('index');
    });
});

describe('inlineTemplateParts', () => {
    it('inlines a referenced part', () => {
        const parts = [{ slug: 'header', theme: 'artisanpack-base', blocks: [paragraph('H')] }];
        const tree = [partRef('header')];

        const inlined = inlineTemplateParts(tree, { parts });

        expect(inlined[0].name).toBe('core/template-part');
        expect(inlined[0].innerBlocks?.[0].attributes?.content).toBe('H');
    });

    it('uses defaultTheme when the reference omits theme', () => {
        const parts = [{ slug: 'header', theme: 'artisanpack-base', blocks: [paragraph('H')] }];
        const tree = [
            { clientId: 'tp', name: 'core/template-part', attributes: { slug: 'header' }, innerBlocks: [] },
        ];

        const inlined = inlineTemplateParts(tree, { parts, defaultTheme: 'artisanpack-base' });

        expect(inlined[0].innerBlocks?.[0].attributes?.content).toBe('H');
    });

    it('marks missing references with the not-found error', () => {
        const tree = [partRef('never-created')];

        const inlined = inlineTemplateParts(tree, { parts: [] });

        expect(inlined[0].attributes?._resolutionError).toBe('not-found');
    });

    it('marks missing slug with the missing-slug error', () => {
        const tree: Block[] = [
            { clientId: 'tp', name: 'core/template-part', attributes: {}, innerBlocks: [] },
        ];

        const inlined = inlineTemplateParts(tree, { parts: [] });

        expect(inlined[0].attributes?._resolutionError).toBe('missing-slug');
    });

    it('detects direct cycles', () => {
        const parts = [{ slug: 'looper', theme: 'artisanpack-base', blocks: [partRef('looper')] }];
        const tree = [partRef('looper')];

        const inlined = inlineTemplateParts(tree, { parts });

        expect(inlined[0].innerBlocks?.[0].attributes?._resolutionError).toBe('cycle');
    });

    it('detects indirect cycles (a → b → a)', () => {
        const parts = [
            { slug: 'a', theme: 'artisanpack-base', blocks: [partRef('b')] },
            { slug: 'b', theme: 'artisanpack-base', blocks: [partRef('a')] },
        ];
        const tree = [partRef('a')];

        const inlined = inlineTemplateParts(tree, { parts });

        const cycleNode = inlined[0].innerBlocks?.[0].innerBlocks?.[0];

        expect(cycleNode?.attributes?._resolutionError).toBe('cycle');
    });

    it('enforces the depth limit', () => {
        const parts = [
            { slug: 'p0', theme: 'artisanpack-base', blocks: [partRef('p1')] },
            { slug: 'p1', theme: 'artisanpack-base', blocks: [partRef('p2')] },
            { slug: 'p2', theme: 'artisanpack-base', blocks: [partRef('p3')] },
            { slug: 'p3', theme: 'artisanpack-base', blocks: [paragraph('end')] },
        ];

        const inlined = inlineTemplateParts([partRef('p0')], { parts, maxDepth: 2 });

        const third = inlined[0].innerBlocks?.[0].innerBlocks?.[0];

        expect(third?.attributes?._resolutionError).toBe('depth-limit');
    });

    it('preserves non-template-part blocks', () => {
        const tree = [paragraph('only')];

        expect(inlineTemplateParts(tree, { parts: [] })).toEqual(tree);
    });

    it('descends into nested innerBlocks looking for parts', () => {
        const parts = [{ slug: 'header', theme: 'artisanpack-base', blocks: [paragraph('H')] }];
        const tree = [
            { clientId: 'g', name: 'core/group', attributes: {}, innerBlocks: [partRef('header')] },
        ];

        const inlined = inlineTemplateParts(tree, { parts });

        expect(inlined[0].innerBlocks?.[0].innerBlocks?.[0].attributes?.content).toBe('H');
    });

    it('exposes a default depth limit', () => {
        expect(DEFAULT_MAX_TEMPLATE_PART_DEPTH).toBe(10);
    });
});
