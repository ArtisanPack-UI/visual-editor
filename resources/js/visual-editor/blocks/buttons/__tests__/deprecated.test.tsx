/**
 * Locks the deprecation chain for `artisanpack/buttons`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    InnerBlocks: {
        Content: () => null,
    },
}));

import deprecated from '../deprecated';

describe('buttons deprecation chain', () => {
    it('ships two deprecation entries (v1 + v2) matching upstream', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(2);
    });

    it('v1 declares the legacy contentJustification + orientation attributes', () => {
        const v1 = deprecated[0];
        expect(
            (v1.attributes as Record<string, { type: string }>)
                .contentJustification.type
        ).toBe('string');
        expect(
            (v1.attributes as Record<string, { type: string }>).orientation.type
        ).toBe('string');
    });

    it('v1 migrate() folds contentJustification + orientation into a flex layout', () => {
        const v1 = deprecated[0];
        const migrated = v1.migrate({
            contentJustification: 'center',
            orientation: 'vertical',
        });
        expect(migrated.layout).toEqual({
            type: 'flex',
            justifyContent: 'center',
            orientation: 'vertical',
        });
        expect(migrated.contentJustification).toBeUndefined();
        expect(migrated.orientation).toBeUndefined();
    });

    it('v2 migrates the legacy align attribute into contentJustification then into a flex layout', () => {
        const v2 = deprecated[1];
        expect(v2.isEligible({ align: 'center' })).toBe(true);
        expect(v2.isEligible({ align: 'wide' })).toBe(false);
        const migrated = v2.migrate({ align: 'left' });
        expect(migrated.align).toBeUndefined();
        expect(migrated.layout).toEqual({
            type: 'flex',
            justifyContent: 'left',
        });
    });
});
