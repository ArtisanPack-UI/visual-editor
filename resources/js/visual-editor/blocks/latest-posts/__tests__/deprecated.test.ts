/**
 * Locks the deprecation chain for `artisanpack/latest-posts`.
 *
 * Mirrors upstream's single v1 entry, which migrates the legacy string
 * `categories` attribute (a bare term id) to the array-of-objects shape.
 */

import { describe, it, expect } from 'vitest';

import deprecated from '../deprecated';

describe('latest-posts deprecation chain', () => {
    it('ships one deprecation entry (v1) matching upstream', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(1);
    });

    it('v1 declares the legacy string categories attribute', () => {
        const v1 = deprecated[0];
        expect((v1.attributes as Record<string, { type: string }>).categories.type).toBe('string');
    });

    it('v1 is eligible only when categories is a string', () => {
        const v1 = deprecated[0];
        expect(v1.isEligible({ categories: '7' })).toBe(true);
        expect(v1.isEligible({ categories: [{ id: 7 }] as unknown as string })).toBe(false);
        expect(v1.isEligible({})).toBe(false);
    });

    it('v1 migrate() upgrades the string id to an array of objects', () => {
        const v1 = deprecated[0];
        const migrated = v1.migrate({ categories: '7', postsToShow: 3 });
        expect(migrated.categories).toEqual([{ id: 7 }]);
        expect(migrated.postsToShow).toBe(3);
    });

    it('v1 migrate() drops a malformed category instead of emitting { id: NaN }', () => {
        const v1 = deprecated[0];
        const migrated = v1.migrate({ categories: 'not-a-number', postsToShow: 3 });
        expect(migrated.categories).toBeUndefined();
        expect(migrated.postsToShow).toBe(3);
    });

    it('v1 save() returns null (dynamic block)', () => {
        const v1 = deprecated[0];
        expect(v1.save()).toBeNull();
    });
});
