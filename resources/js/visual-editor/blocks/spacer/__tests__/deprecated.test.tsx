/**
 * Locks the deprecation chain for `artisanpack/spacer`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
}));

import deprecated from '../deprecated';

describe('spacer deprecation chain', () => {
    it('ships one deprecation entry (v1) matching upstream', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(1);
    });

    it('v1 declares the legacy numeric height/width attribute schema', () => {
        const v1 = deprecated[0];
        const attrs = v1.attributes as Record<
            string,
            { type: string; default?: number }
        >;
        expect(attrs.height.type).toBe('number');
        expect(attrs.height.default).toBe(100);
        expect(attrs.width.type).toBe('number');
    });

    it('v1 migrate() converts numeric height/width to px strings', () => {
        const v1 = deprecated[0];
        const migrated = v1.migrate({ height: 100, width: 50 });
        expect(migrated.height).toBe('100px');
        expect(migrated.width).toBe('50px');
    });

    it('v1 migrate() leaves undefined width as undefined', () => {
        const v1 = deprecated[0];
        const migrated = v1.migrate({ height: 100 });
        expect(migrated.height).toBe('100px');
        expect(migrated.width).toBeUndefined();
    });

    it('v1 renders a <div> in legacy markup', () => {
        const v1 = deprecated[0];
        const html = renderToStaticMarkup(
            v1.save({ attributes: { height: 100, width: 50 } })
        );
        expect(html).toContain('<div');
        expect(html).toContain('aria-hidden');
    });
});
