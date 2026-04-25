/**
 * Menu-tree round-trip + mutation helpers.
 *
 * The bridge between the persisted Gutenberg block tree and the
 * in-memory MenuItem tree is the load-bearing piece of D4: it has to
 * round-trip without losing data, and the helper mutators need to be
 * pure so React state stays stable.
 */

import { describe, expect, it } from 'vitest';

import {
    appendChild,
    blocksToMenuTree,
    buildTreeFromFlat,
    commitProjection,
    effectiveLabel,
    findMenuItem,
    flattenTree,
    getDescendantIds,
    makeMenuItem,
    menuTreeToBlocks,
    projectDrop,
    removeMenuItem,
    reorderTopLevel,
    replaceMenuItem,
    type MenuItem,
} from '../menu-tree';

describe('blocksToMenuTree', () => {
    it('converts a navigation-link block into a typed page reference', () => {
        const blocks = [
            {
                name: 'core/navigation-link',
                attributes: {
                    kind: 'post-type',
                    type: 'page',
                    id: 12,
                    label: 'About',
                    url: 'https://example.com/about',
                    opensInNewTab: false,
                },
                innerBlocks: [],
            },
        ];

        const tree = blocksToMenuTree(blocks);

        expect(tree).toHaveLength(1);
        expect(tree[0]).toMatchObject({
            type: 'page',
            targetId: 12,
            url: 'https://example.com/about',
            autoLabel: 'About',
            children: [],
        });
    });

    it('treats a bare URL block as the custom type', () => {
        const blocks = [
            {
                name: 'core/navigation-link',
                attributes: {
                    label: 'Docs',
                    url: 'https://docs.example.com',
                },
                innerBlocks: [],
            },
        ];

        const tree = blocksToMenuTree(blocks);

        expect(tree[0].type).toBe('custom');
        expect(tree[0].targetId).toBeNull();
        expect(tree[0].url).toBe('https://docs.example.com');
    });

    it('walks navigation-submenu inner blocks recursively', () => {
        const blocks = [
            {
                name: 'core/navigation-submenu',
                attributes: { label: 'Products', kind: 'custom' },
                innerBlocks: [
                    {
                        name: 'core/navigation-link',
                        attributes: { label: 'Pro', url: '/pro' },
                        innerBlocks: [],
                    },
                ],
            },
        ];

        const tree = blocksToMenuTree(blocks);

        expect(tree[0].autoLabel).toBe('Products');
        expect(tree[0].children).toHaveLength(1);
        expect(tree[0].children[0].url).toBe('/pro');
    });

    it('drops blocks whose name is not a navigation block', () => {
        const tree = blocksToMenuTree([
            { name: 'core/paragraph', attributes: {}, innerBlocks: [] },
            {
                name: 'core/navigation-link',
                attributes: { label: 'Home', url: '/' },
                innerBlocks: [],
            },
        ]);

        expect(tree).toHaveLength(1);
        expect(tree[0].url).toBe('/');
    });
});

describe('menuTreeToBlocks', () => {
    it('serializes a leaf as a navigation-link block', () => {
        const tree: MenuItem[] = [
            makeMenuItem({
                type: 'page',
                targetId: 7,
                autoLabel: 'About',
                url: '/about',
            }),
        ];

        const blocks = menuTreeToBlocks(tree) as Array<{
            name: string;
            attributes: Record<string, unknown>;
        }>;

        expect(blocks).toHaveLength(1);
        expect(blocks[0].name).toBe('core/navigation-link');
        expect(blocks[0].attributes).toMatchObject({
            kind: 'post-type',
            type: 'page',
            id: 7,
            label: 'About',
            url: '/about',
        });
    });

    it('serializes parents with children as navigation-submenu blocks', () => {
        const tree: MenuItem[] = [
            makeMenuItem({
                type: 'custom',
                autoLabel: 'Products',
                children: [
                    makeMenuItem({
                        type: 'custom',
                        autoLabel: 'Pro',
                        url: '/pro',
                    }),
                ],
            }),
        ];

        const blocks = menuTreeToBlocks(tree) as Array<{
            name: string;
            innerBlocks: unknown[];
        }>;

        expect(blocks[0].name).toBe('core/navigation-submenu');
        expect(blocks[0].innerBlocks).toHaveLength(1);
    });

    it('round-trips a full tree without losing data', () => {
        const original: MenuItem[] = [
            makeMenuItem({
                type: 'page',
                targetId: 1,
                autoLabel: 'Home',
                url: '/',
            }),
            makeMenuItem({
                type: 'custom',
                autoLabel: 'Products',
                children: [
                    makeMenuItem({
                        type: 'post',
                        targetId: 5,
                        autoLabel: 'Pro',
                        url: '/pro',
                    }),
                ],
            }),
        ];

        const blocks = menuTreeToBlocks(original);
        const reparsed = blocksToMenuTree(blocks);

        // Local IDs are unstable across regenerations — strip them
        // before comparing.
        const stripIds = (items: MenuItem[]): unknown[] =>
            items.map(({ localId, children, ...rest }) => {
                void localId;
                return { ...rest, children: stripIds(children) };
            });

        expect(stripIds(reparsed)).toEqual(stripIds(original));
    });
});

describe('mutations', () => {
    it('replaceMenuItem updates a nested item without touching siblings', () => {
        const child = makeMenuItem({ autoLabel: 'Child', url: '/child' });
        const tree = [
            makeMenuItem({
                autoLabel: 'Parent',
                children: [child],
            }),
            makeMenuItem({ autoLabel: 'Sibling' }),
        ];

        const next = replaceMenuItem(tree, child.localId, (item) => ({
            ...item,
            url: '/changed',
        }));

        expect(next[0].children[0].url).toBe('/changed');
        expect(next[1].autoLabel).toBe('Sibling');
        expect(next).not.toBe(tree);
    });

    it('removeMenuItem prunes a deeply-nested node', () => {
        const grand = makeMenuItem({ autoLabel: 'Grand' });
        const child = makeMenuItem({ autoLabel: 'Child', children: [grand] });
        const tree = [
            makeMenuItem({ autoLabel: 'Parent', children: [child] }),
        ];

        const next = removeMenuItem(tree, grand.localId);

        expect(next[0].children[0].children).toHaveLength(0);
    });

    it('appendChild adds a fresh node under the right parent', () => {
        const fresh = makeMenuItem({ autoLabel: 'New' });
        const parent = makeMenuItem({ autoLabel: 'Parent' });
        const tree = [parent];

        const next = appendChild(tree, parent.localId, fresh);

        expect(next[0].children).toHaveLength(1);
        expect(next[0].children[0].autoLabel).toBe('New');
    });

    it('reorderTopLevel swaps two items', () => {
        const a = makeMenuItem({ autoLabel: 'A' });
        const b = makeMenuItem({ autoLabel: 'B' });
        const c = makeMenuItem({ autoLabel: 'C' });

        const next = reorderTopLevel([a, b, c], 0, 2);

        expect(next.map((item) => item.autoLabel)).toEqual(['B', 'C', 'A']);
    });

    it('findMenuItem locates a deeply-nested node', () => {
        const grand = makeMenuItem({ autoLabel: 'Grand' });
        const child = makeMenuItem({ autoLabel: 'Child', children: [grand] });
        const tree = [
            makeMenuItem({ autoLabel: 'Parent', children: [child] }),
        ];

        const found = findMenuItem(tree, grand.localId);

        expect(found?.autoLabel).toBe('Grand');
    });
});

describe('flat-list bridge', () => {
    function makeTree(): MenuItem[] {
        return [
            makeMenuItem({ autoLabel: 'Home' }),
            makeMenuItem({
                autoLabel: 'Products',
                children: [
                    makeMenuItem({ autoLabel: 'Pro' }),
                    makeMenuItem({ autoLabel: 'Plus' }),
                ],
            }),
            makeMenuItem({ autoLabel: 'About' }),
        ];
    }

    it('flattenTree emits one row per node with depth + parent metadata', () => {
        const tree = makeTree();
        const flat = flattenTree(tree);

        expect(flat).toHaveLength(5);
        expect(flat.map((entry) => entry.depth)).toEqual([0, 0, 1, 1, 0]);
        expect(flat[2].parentLocalId).toBe(tree[1].localId);
        expect(flat[3].parentLocalId).toBe(tree[1].localId);
        expect(flat[4].parentLocalId).toBeNull();
    });

    it('buildTreeFromFlat is the inverse of flattenTree', () => {
        const tree = makeTree();
        const reparsed = buildTreeFromFlat(flattenTree(tree));

        const stripIds = (items: MenuItem[]): unknown[] =>
            items.map(({ localId, children, ...rest }) => {
                void localId;
                return { ...rest, children: stripIds(children) };
            });

        expect(stripIds(reparsed)).toEqual(stripIds(tree));
    });

    it('buildTreeFromFlat handles a child appearing before its parent', () => {
        const tree = makeTree();
        const flat = flattenTree(tree);

        // Swap the parent ("Products") with one of its children — the
        // dnd-kit drag-end path can produce this layout when a single
        // item array-moves across its own children.
        const swapped = [...flat];
        const productsIndex = swapped.findIndex(
            (entry) => entry.item.autoLabel === 'Products'
        );
        const proIndex = swapped.findIndex(
            (entry) => entry.item.autoLabel === 'Pro'
        );

        [swapped[productsIndex], swapped[proIndex]] = [
            swapped[proIndex],
            swapped[productsIndex],
        ];

        const rebuilt = buildTreeFromFlat(swapped);

        const productsItem = rebuilt.find(
            (item) => item.autoLabel === 'Products'
        );

        expect(productsItem?.children.map((c) => c.autoLabel)).toEqual([
            'Pro',
            'Plus',
        ]);
    });

    it('getDescendantIds collects every transitive child', () => {
        const grand = makeMenuItem({ autoLabel: 'Grand' });
        const child = makeMenuItem({
            autoLabel: 'Child',
            children: [grand],
        });
        const parent = makeMenuItem({
            autoLabel: 'Parent',
            children: [child],
        });

        const descendants = getDescendantIds(
            flattenTree([parent]),
            parent.localId
        );

        expect(descendants.has(child.localId)).toBe(true);
        expect(descendants.has(grand.localId)).toBe(true);
        expect(descendants.has(parent.localId)).toBe(false);
    });

    it('projectDrop deepens when the drag offset clears one indent step', () => {
        const tree = [
            makeMenuItem({ autoLabel: 'A' }),
            makeMenuItem({ autoLabel: 'B' }),
        ];
        const flat = flattenTree(tree);

        const projection = projectDrop(
            flat,
            flat[1].item.localId,
            flat[1].item.localId,
            // Drag B 30 pixels right with INDENT_PX = 24 → projects to
            // depth 1 (becomes a child of A).
            30,
            24
        );

        expect(projection?.depth).toBe(1);
        expect(projection?.parentLocalId).toBe(flat[0].item.localId);
    });

    it('projectDrop clamps depth to maxDepth (previous sibling depth + 1)', () => {
        const tree = [
            makeMenuItem({ autoLabel: 'A' }),
            makeMenuItem({ autoLabel: 'B' }),
        ];
        const flat = flattenTree(tree);

        const projection = projectDrop(
            flat,
            flat[1].item.localId,
            flat[1].item.localId,
            // Far past the max depth; clamp to depth 1.
            500,
            24
        );

        expect(projection?.depth).toBe(1);
    });

    it('projectDrop pulls a nested item out to root with a left drag', () => {
        const child = makeMenuItem({ autoLabel: 'Child' });
        const parent = makeMenuItem({
            autoLabel: 'Parent',
            children: [child],
        });

        const flat = flattenTree([parent]);
        const projection = projectDrop(
            flat,
            child.localId,
            child.localId,
            // 60px left → −2 indent steps → clamps to 0 (root).
            -60,
            24
        );

        expect(projection?.depth).toBe(0);
        expect(projection?.parentLocalId).toBeNull();
    });

    it('commitProjection moves the active row and updates its depth + parent', () => {
        const tree = [
            makeMenuItem({ autoLabel: 'A' }),
            makeMenuItem({ autoLabel: 'B' }),
            makeMenuItem({ autoLabel: 'C' }),
        ];
        const flat = flattenTree(tree);
        const projection = {
            depth: 1,
            maxDepth: 1,
            minDepth: 0,
            parentLocalId: flat[0].item.localId,
        };

        const updated = commitProjection(
            flat,
            flat[2].item.localId,
            flat[1].item.localId,
            projection
        );

        // C moves to index 1, becomes child of A.
        expect(updated[1].item.localId).toBe(flat[2].item.localId);
        expect(updated[1].depth).toBe(1);
        expect(updated[1].parentLocalId).toBe(flat[0].item.localId);
    });

    it('round-trip: drag a child out to root rebuilds correctly', () => {
        const child = makeMenuItem({ autoLabel: 'Child' });
        const parent = makeMenuItem({
            autoLabel: 'Parent',
            children: [child],
        });
        const sibling = makeMenuItem({ autoLabel: 'Sibling' });

        const flat = flattenTree([parent, sibling]);
        const projection = {
            depth: 0,
            maxDepth: 0,
            minDepth: 0,
            parentLocalId: null,
        };

        // Move "Child" past "Sibling" at depth 0.
        const updated = commitProjection(
            flat,
            child.localId,
            sibling.localId,
            projection
        );
        const rebuilt = buildTreeFromFlat(updated);

        expect(rebuilt.map((item) => item.autoLabel)).toContain('Child');
        const rebuiltParent = rebuilt.find(
            (item) => item.autoLabel === 'Parent'
        );
        expect(rebuiltParent?.children).toHaveLength(0);
    });
});

describe('effectiveLabel', () => {
    it('prefers the override over the auto label', () => {
        const item = makeMenuItem({
            autoLabel: 'Auto',
            labelOverride: 'Explicit',
        });

        expect(effectiveLabel(item)).toBe('Explicit');
    });

    it('falls back to URL when both labels are empty', () => {
        const item = makeMenuItem({
            autoLabel: '',
            labelOverride: null,
            url: '/contact',
        });

        expect(effectiveLabel(item)).toBe('/contact');
    });
});
