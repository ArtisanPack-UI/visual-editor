/**
 * Related Posts grid layout helpers (#964).
 *
 * The editor's List / Grid toggles and Columns control read the same
 * `displayLayout.columns` → `numColumns` → 3 fallbacks the renderers
 * use, write both attributes back in sync, and hide when a nested Post
 * Template owns the layout.
 */

import { describe, expect, it } from 'vitest';

import {
    hasNestedPostTemplate,
    readRelatedPostsLayout,
    relatedPostsLayoutChanges,
} from '../related-posts/layout';

describe('readRelatedPostsLayout', () => {
    it('defaults to a list', () => {
        expect(readRelatedPostsLayout({})).toEqual({ type: 'list', columns: 3 });
    });

    it('reads a grid displayLayout and its columns', () => {
        expect(
            readRelatedPostsLayout({
                numColumns: 2,
                displayLayout: { type: 'grid', columns: 4 },
            })
        ).toEqual({ type: 'grid', columns: 4 });
    });

    it('falls back to numColumns, then 3, and clamps to the control range', () => {
        expect(
            readRelatedPostsLayout({ numColumns: 5, displayLayout: { type: 'grid' } })
        ).toEqual({ type: 'grid', columns: 5 });
        expect(readRelatedPostsLayout({ displayLayout: { type: 'grid' } }).columns).toBe(3);
        expect(
            readRelatedPostsLayout({ displayLayout: { type: 'grid', columns: '9' } }).columns
        ).toBe(6);
    });

    it('treats an unknown type as a list', () => {
        expect(readRelatedPostsLayout({ displayLayout: { type: 'masonry' } }).type).toBe('list');
    });
});

describe('relatedPostsLayoutChanges', () => {
    it('writes displayLayout and the legacy numColumns mirror', () => {
        expect(relatedPostsLayoutChanges({ type: 'grid', columns: 3 })).toEqual({
            displayLayout: { type: 'grid', columns: 3 },
            numColumns: 3,
        });
    });
});

describe('hasNestedPostTemplate', () => {
    it('detects a nested post template', () => {
        expect(hasNestedPostTemplate([{ name: 'artisanpack/post-template' }])).toBe(true);
        expect(hasNestedPostTemplate([{ name: 'core/post-template' }])).toBe(true);
    });

    it('is false for flat related-post content', () => {
        expect(hasNestedPostTemplate([{ name: 'core/group' }, {}])).toBe(false);
        expect(hasNestedPostTemplate([])).toBe(false);
    });
});
