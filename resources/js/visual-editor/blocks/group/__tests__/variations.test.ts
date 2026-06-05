/**
 * Variation surface tests for `artisanpack/group`.
 *
 * Locks in the I3 forking contract: group ships row + stack as
 * variations and DOES NOT carry the upstream `grid` variation
 * (grid is split into a standalone artisanpack/grid +
 * artisanpack/grid-item block pair — see docs/plans/13-block-fork.md
 * §2.2.1).
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
}));

import variations from '../variations';

describe('artisanpack/group variations', () => {
    it('exports an array', () => {
        expect(Array.isArray(variations)).toBe(true);
    });

    it('includes the group, row, and stack variations', () => {
        const names = variations.map((v) => v.name);
        expect(names).toContain('group');
        expect(names).toContain('row');
        expect(names).toContain('stack');
    });

    it('EXCLUDES the grid variation (split into a separate block)', () => {
        const names = variations.map((v) => v.name);
        expect(names).not.toContain('grid');
        expect(names).not.toContain('group-grid');
    });

    it('marks the group variation as default', () => {
        const group = variations.find((v) => v.name === 'group');
        expect(group?.isDefault).toBe(true);
    });

    it('preserves the inserter + transform scopes on each variation', () => {
        for (const v of variations) {
            expect(v.scope).toContain('inserter');
            expect(v.scope).toContain('transform');
        }
    });

    it('row uses flex layout with no wrap', () => {
        const row = variations.find((v) => v.name === 'row');
        expect(row?.attributes.layout).toEqual({
            type: 'flex',
            flexWrap: 'nowrap',
        });
    });

    it('stack uses flex layout with vertical orientation', () => {
        const stack = variations.find((v) => v.name === 'stack');
        expect(stack?.attributes.layout).toEqual({
            type: 'flex',
            orientation: 'vertical',
        });
    });
});
