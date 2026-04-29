import { describe, expect, it } from 'vitest';

import { entityTypeForResource } from '../entity-type';

describe('entityTypeForResource', () => {
    it('maps the cms-framework "posts" slug onto the "post" entity', () => {
        expect(entityTypeForResource('posts')).toBe('post');
    });

    it('maps the cms-framework "pages" slug onto the "page" entity', () => {
        expect(entityTypeForResource('pages')).toBe('page');
    });

    it('returns null for any other resource so the EntityProvider wrap is skipped', () => {
        expect(entityTypeForResource('orders')).toBeNull();
        expect(entityTypeForResource('m3-content')).toBeNull();
        expect(entityTypeForResource('')).toBeNull();
    });

    it('does not coerce capitalisation — the slug is case-sensitive', () => {
        // Defensive: cms-framework registers slugs lowercased via the
        // `ap.visual-editor.resources` filter, so anything else is a
        // misconfig. Not silently mapping `Posts` → `post` keeps the
        // misconfig visible (block resolution falls through to null).
        expect(entityTypeForResource('Posts')).toBeNull();
        expect(entityTypeForResource('PAGES')).toBeNull();
    });
} );
