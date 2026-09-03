/**
 * Vitest coverage for the TOC editor-preview heading extractor
 * (#760).
 *
 * Behaviour parity with the server-side `TocResolver` (PHP) is what
 * makes the block WYSIWYG — the assertions below mirror the ones in
 * `tests/Unit/VisualEditor/Resources/TocResolverTest.php`.
 */

import { describe, expect, it } from 'vitest';

import { deriveHeadingItems, filterItemsByLevel } from '../derive-items';

function heading(level: number, content: string, extras: Record<string, unknown> = {}) {
    return {
        name: 'core/heading',
        attributes: { level, content, ...extras },
    };
}

function apHeading(level: number, content: string, extras: Record<string, unknown> = {}) {
    return {
        name: 'artisanpack/heading',
        attributes: { level, content, ...extras },
    };
}

function group(children: unknown[]) {
    return {
        name: 'core/group',
        attributes: {},
        innerBlocks: children,
    };
}

describe('deriveHeadingItems', () => {
    it('extracts core/heading and artisanpack/heading blocks in document order', () => {
        const items = deriveHeadingItems([
            heading(2, 'Getting Started'),
            heading(3, 'Installation Steps'),
            apHeading(2, 'FAQ Support'),
        ] as never);

        expect(items).toEqual([
            { level: 2, text: 'Getting Started', anchor: 'getting-started' },
            { level: 3, text: 'Installation Steps', anchor: 'installation-steps' },
            { level: 2, text: 'FAQ Support', anchor: 'faq-support' },
        ]);
    });

    it('preserves author-set anchor attributes', () => {
        const items = deriveHeadingItems([
            heading(2, 'Custom anchor', { anchor: 'my-slug' }),
        ] as never);

        expect(items[0].anchor).toBe('my-slug');
    });

    it('suffixes duplicate slugs so anchors are unique', () => {
        const items = deriveHeadingItems([
            heading(2, 'Overview'),
            heading(3, 'Overview'),
            heading(3, 'Overview'),
        ] as never);

        expect(items.map((i) => i.anchor)).toEqual([
            'overview',
            'overview-1',
            'overview-2',
        ]);
    });

    it('claims an author-set anchor so a later auto-generated one is suffixed', () => {
        const items = deriveHeadingItems([
            heading(2, 'Setup', { anchor: 'setup' }),
            heading(3, 'Setup'),
        ] as never);

        expect(items[0].anchor).toBe('setup');
        expect(items[1].anchor).toBe('setup-1');
    });

    it('walks inner blocks so nested headings are extracted', () => {
        const items = deriveHeadingItems([
            group([
                heading(2, 'Outer'),
                group([heading(3, 'Deep')]),
            ]),
        ] as never);

        expect(items).toEqual([
            { level: 2, text: 'Outer', anchor: 'outer' },
            { level: 3, text: 'Deep', anchor: 'deep' },
        ]);
    });

    it('skips headings whose content is punctuation-only (empty slug)', () => {
        const items = deriveHeadingItems([
            heading(2, '???'),
            heading(2, 'Kept'),
        ] as never);

        expect(items).toEqual([
            { level: 2, text: 'Kept', anchor: 'kept' },
        ]);
    });

    it('ignores non-heading blocks', () => {
        const items = deriveHeadingItems([
            { name: 'core/paragraph', attributes: { content: 'hello' } },
            heading(2, 'Kept'),
        ] as never);

        expect(items).toHaveLength(1);
        expect(items[0].anchor).toBe('kept');
    });
});

describe('filterItemsByLevel', () => {
    const items = [
        { level: 1, text: 'H1', anchor: 'h1' },
        { level: 2, text: 'H2', anchor: 'h2' },
        { level: 3, text: 'H3', anchor: 'h3' },
        { level: 4, text: 'H4', anchor: 'h4' },
    ];

    it('keeps only entries within min/max', () => {
        const filtered = filterItemsByLevel(items, 2, 3);

        expect(filtered.map((i) => i.level)).toEqual([2, 3]);
    });

    it('swaps bounds when the range is inverted', () => {
        const filtered = filterItemsByLevel(items, 4, 2);

        expect(filtered.map((i) => i.level)).toEqual([2, 3, 4]);
    });
});
