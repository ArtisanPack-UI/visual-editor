import { describe, expect, it } from 'vitest';

import {
    DEFAULT_MAX_TEMPLATE_PART_DEPTH,
    findTemplate,
    inlineNavigationOverlays,
    inlineTemplateParts,
    isNavigationOverlayContent,
    NAVIGATION_OVERLAY_AREA,
    NAVIGATION_OVERLAY_CONTENT_BLOCK,
    NAVIGATION_OVERLAY_SLOT,
    resolveTemplate,
    stripStoredNavigationOverlayContent,
    TEMPLATE_PART_BLOCK_NAMES,
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

    it('prefers an exact-theme match over an unthemed record regardless of array order', () => {
        const mixed = [
            // Unthemed record listed first; the themed record should still win
            // because findTemplate runs an exact-theme pass before the fallback.
            { slug: 'index', theme: '', blocks: [paragraph('Unthemed')] },
            { slug: 'index', theme: 'artisanpack-base', blocks: [paragraph('Themed')] },
        ];

        expect(findTemplate(mixed, 'index', 'artisanpack-base')?.blocks[0].attributes?.content).toBe('Themed');
    });

    it('falls back to an unthemed record only when no exact-theme match exists', () => {
        const mixed = [
            { slug: 'index', theme: '', blocks: [paragraph('Unthemed')] },
            { slug: 'index', theme: 'other-theme', blocks: [paragraph('Other')] },
        ];

        expect(findTemplate(mixed, 'index', 'artisanpack-base')?.blocks[0].attributes?.content).toBe('Unthemed');
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

        // p0 (depth 0) and p1 (depth 1) resolve; p2 (depth 2) hits the cap.
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

    it('prefers an exact-theme part over an unthemed part regardless of array order', () => {
        const parts = [
            { slug: 'header', theme: '', blocks: [paragraph('Unthemed')] },
            { slug: 'header', theme: 'artisanpack-base', blocks: [paragraph('Themed')] },
        ];
        const tree = [partRef('header', 'artisanpack-base')];

        const inlined = inlineTemplateParts(tree, { parts });

        expect(inlined[0].innerBlocks?.[0].attributes?.content).toBe('Themed');
    });

    describe('artisanpack/template-part fork (#822)', () => {
        function forkRef(slug: string, clientId = 'tp-fork'): Block {
            return {
                clientId,
                name: 'artisanpack/template-part',
                attributes: { slug, theme: 'artisanpack-base' },
                innerBlocks: [],
            };
        }

        it('treats both core and fork names as template-part references', () => {
            expect(TEMPLATE_PART_BLOCK_NAMES.has('core/template-part')).toBe(true);
            expect(TEMPLATE_PART_BLOCK_NAMES.has('artisanpack/template-part')).toBe(true);
        });

        it('inlines a fork-named part and keeps the fork name', () => {
            const parts = [{ slug: 'header', theme: 'artisanpack-base', blocks: [paragraph('H')] }];

            const inlined = inlineTemplateParts([forkRef('header')], { parts });

            expect(inlined[0].name).toBe('artisanpack/template-part');
            expect(inlined[0].innerBlocks?.[0].attributes?.content).toBe('H');
        });

        it('replaces a stale innerBlocks snapshot saved on the fork block', () => {
            const parts = [{ slug: 'header', theme: 'artisanpack-base', blocks: [paragraph('Live')] }];
            const stale = { ...forkRef('header'), innerBlocks: [paragraph('Stale snapshot')] };

            const inlined = inlineTemplateParts([stale], { parts });

            expect(inlined[0].innerBlocks).toHaveLength(1);
            expect(inlined[0].innerBlocks?.[0].attributes?.content).toBe('Live');
        });

        it('marks an unknown fork-named slug as not-found and keeps the fork name', () => {
            const inlined = inlineTemplateParts([forkRef('never-created')], { parts: [] });

            expect(inlined[0].name).toBe('artisanpack/template-part');
            expect(inlined[0].attributes?._resolutionError).toBe('not-found');
            expect(inlined[0].innerBlocks).toEqual([]);
        });

        it('detects cycles across mixed core and fork references', () => {
            const parts = [
                { slug: 'a', theme: 'artisanpack-base', blocks: [partRef('b')] },
                { slug: 'b', theme: 'artisanpack-base', blocks: [forkRef('a')] },
            ];

            const inlined = inlineTemplateParts([forkRef('a')], { parts });
            const cycleNode = inlined[0].innerBlocks?.[0].innerBlocks?.[0];

            expect(cycleNode?.name).toBe('artisanpack/template-part');
            expect(cycleNode?.attributes?._resolutionError).toBe('cycle');
        });
    });
});

describe('inlineTemplateParts — navigation overlay parts (#804)', () => {
    function nav(attributes: Record<string, unknown>, name = 'core/navigation'): Block {
        return {
            clientId: 'nav-cid',
            name,
            attributes,
            innerBlocks: [
                { clientId: 'link-cid', name: 'core/navigation-link', attributes: { label: 'Home', url: '/' }, innerBlocks: [] },
            ],
        };
    }

    const overlayPart = {
        slug: 'mobile-overlay',
        theme: 'artisanpack-base',
        area: NAVIGATION_OVERLAY_AREA,
        blocks: [paragraph('Call us')],
    };

    it('appends the overlay part as a slot container after the menu items', () => {
        const [inlined] = inlineTemplateParts([nav({ overlay: 'mobile-overlay' })], {
            parts: [overlayPart],
            defaultTheme: 'artisanpack-base',
        });

        expect(inlined.innerBlocks).toHaveLength(2);
        expect(inlined.innerBlocks?.[0].name).toBe('core/navigation-link');

        const overlay = inlined.innerBlocks?.[1];

        expect(overlay?.name).toBe(NAVIGATION_OVERLAY_CONTENT_BLOCK);
        expect(overlay?.clientId).toBe('nav-cid-overlay');
        expect(overlay?.attributes).toEqual({ _slot: NAVIGATION_OVERLAY_SLOT, slug: 'mobile-overlay' });
        expect(overlay?.innerBlocks?.[0].attributes?.content).toBe('Call us');
    });

    it('resolves the overlay on the legacy artisanpack/navigation name', () => {
        const [inlined] = inlineTemplateParts([nav({ overlay: 'mobile-overlay' }, 'artisanpack/navigation')], {
            parts: [overlayPart],
            defaultTheme: 'artisanpack-base',
        });

        expect(inlined.innerBlocks?.[1]?.name).toBe(NAVIGATION_OVERLAY_CONTENT_BLOCK);
    });

    it('inlines template-part references nested inside the overlay part', () => {
        const parts = [
            { ...overlayPart, blocks: [partRef('social')] },
            { slug: 'social', theme: 'artisanpack-base', blocks: [paragraph('Follow us')] },
        ];

        const [inlined] = inlineTemplateParts([nav({ overlay: 'mobile-overlay' })], {
            parts,
            defaultTheme: 'artisanpack-base',
        });

        expect(inlined.innerBlocks?.[1]?.innerBlocks?.[0].innerBlocks?.[0].attributes?.content).toBe('Follow us');
    });

    it.each([
        ['the overlay is switched off', { overlay: 'mobile-overlay', overlayMenu: 'never' }, [overlayPart]],
        ['no overlay is set', {}, [overlayPart]],
        ['the slug is blank', { overlay: '   ' }, [overlayPart]],
        ['the slug is unknown', { overlay: 'deleted-overlay' }, [overlayPart]],
        ['the part is in another area', { overlay: 'mobile-overlay' }, [{ ...overlayPart, area: 'header' }]],
        ['the part has no area', { overlay: 'mobile-overlay' }, [{ ...overlayPart, area: undefined }]],
        ['the part has no blocks', { overlay: 'mobile-overlay' }, [{ ...overlayPart, blocks: [] }]],
    ])('keeps only the menu items when %s', (_label, attributes, parts) => {
        const [inlined] = inlineTemplateParts([nav(attributes)], {
            parts,
            defaultTheme: 'artisanpack-base',
        });

        expect(inlined.innerBlocks).toHaveLength(1);
        expect(inlined.innerBlocks?.[0].name).toBe('core/navigation-link');
    });

    it('does not recurse when the overlay part holds a navigation pointing back at it', () => {
        const parts = [{ ...overlayPart, blocks: [nav({ overlay: 'mobile-overlay' })] }];

        const [inlined] = inlineTemplateParts([nav({ overlay: 'mobile-overlay' })], {
            parts,
            defaultTheme: 'artisanpack-base',
        });

        const innerNav = inlined.innerBlocks?.[1]?.innerBlocks?.[0];

        expect(innerNav?.name).toBe('core/navigation');
        expect(innerNav?.innerBlocks).toHaveLength(1);
    });

    it('marks the overlay block it creates as inliner-produced', () => {
        const [inlined] = inlineTemplateParts([nav({ overlay: 'mobile-overlay' })], {
            parts: [overlayPart],
            defaultTheme: 'artisanpack-base',
        });
        const overlay = inlined.innerBlocks?.[1] as Block;

        expect(isNavigationOverlayContent(overlay)).toBe(true);
        // A stored (JSON) copy can't carry the marker.
        expect(isNavigationOverlayContent(JSON.parse(JSON.stringify(overlay)))).toBe(false);
    });

    it('is idempotent: running the inliner twice yields exactly one overlay block (RN-9)', () => {
        const options = { parts: [overlayPart], defaultTheme: 'artisanpack-base' };
        const once = inlineTemplateParts([nav({ overlay: 'mobile-overlay' })], options);
        const [twice] = inlineTemplateParts(once, options);
        const [thrice] = inlineNavigationOverlays([twice], options);

        for (const inlined of [twice, thrice]) {
            expect(inlined.innerBlocks).toHaveLength(2);
            expect(inlined.innerBlocks?.filter((block) => block.name === NAVIGATION_OVERLAY_CONTENT_BLOCK)).toHaveLength(1);
        }
    });

    it('drops a stored overlay-content block instead of routing it (RN-10)', () => {
        const stored: Block = {
            clientId: 'stored-overlay',
            name: NAVIGATION_OVERLAY_CONTENT_BLOCK,
            attributes: { _slot: NAVIGATION_OVERLAY_SLOT },
            innerBlocks: [paragraph('Injected')],
        };
        const navWithStored = { ...nav({ overlay: 'mobile-overlay' }), innerBlocks: [...(nav({}).innerBlocks ?? []), stored] };

        const [inlined] = inlineTemplateParts([navWithStored], {
            parts: [overlayPart],
            defaultTheme: 'artisanpack-base',
        });

        expect(inlined.innerBlocks).toHaveLength(2);
        expect(isNavigationOverlayContent(inlined.innerBlocks?.[1] as Block)).toBe(true);
        expect(inlined.innerBlocks?.[1]?.innerBlocks?.[0].attributes?.content).toBe('Call us');

        const [stripped] = stripStoredNavigationOverlayContent([navWithStored]);

        expect(stripped.innerBlocks).toHaveLength(1);
        expect(stripped.innerBlocks?.[0].name).toBe('core/navigation-link');
    });

    it('keeps inliner-produced overlay blocks when stripping stored ones', () => {
        const inlined = inlineTemplateParts([nav({ overlay: 'mobile-overlay' })], {
            parts: [overlayPart],
            defaultTheme: 'artisanpack-base',
        });

        expect(stripStoredNavigationOverlayContent(inlined)).toEqual(inlined);
        expect(stripStoredNavigationOverlayContent(inlined)[0]).toBe(inlined[0]);
    });

    it('resolves a navigation the first pass could not see, without re-resolving template parts (RN-9)', () => {
        // Simulates a synced pattern expanded after the first pass: the
        // navigation now sits inside an already-resolved header part.
        const [header] = inlineTemplateParts([partRef('header')], {
            parts: [{ slug: 'header', theme: 'artisanpack-base', blocks: [paragraph('Original')] }],
            defaultTheme: 'artisanpack-base',
        });
        const expanded: Block = { ...header, innerBlocks: [nav({ overlay: 'mobile-overlay' })] };

        const [resolved] = inlineNavigationOverlays([expanded], {
            parts: [overlayPart, { slug: 'header', theme: 'artisanpack-base', blocks: [paragraph('Re-resolved')] }],
            defaultTheme: 'artisanpack-base',
        });

        expect(resolved.innerBlocks).toHaveLength(1);
        expect(resolved.innerBlocks?.[0].name).toBe('core/navigation');
        expect(isNavigationOverlayContent(resolved.innerBlocks?.[0].innerBlocks?.[1] as Block)).toBe(true);
    });

    it('keeps the recursion guard in the overlay-only pass', () => {
        const parts = [{ ...overlayPart, blocks: [nav({ overlay: 'mobile-overlay' })] }];

        const [inlined] = inlineNavigationOverlays([nav({ overlay: 'mobile-overlay' })], {
            parts,
            defaultTheme: 'artisanpack-base',
        });

        const innerNav = inlined.innerBlocks?.[1]?.innerBlocks?.[0];

        expect(innerNav?.name).toBe('core/navigation');
        expect(innerNav?.innerBlocks).toHaveLength(1);
    });
});
