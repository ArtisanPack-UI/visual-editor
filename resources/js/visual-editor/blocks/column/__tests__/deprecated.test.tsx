/**
 * Locks the deprecation chain for `artisanpack/column`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    InnerBlocks: {
        Content: () => null,
    },
}));

import deprecated from '../deprecated';

describe('column deprecation chain', () => {
    it('ships one deprecation entry (v1) matching upstream', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(1);
    });

    it('v1 declares the legacy numeric width attribute', () => {
        const v1 = deprecated[0];
        const width = (v1.attributes as Record<string, { type: string }>).width;
        expect(width.type).toBe('number');
    });

    it('v1 isEligible() matches finite numeric widths', () => {
        const v1 = deprecated[0];
        expect(v1.isEligible({ width: 50 })).toBe(true);
        expect(v1.isEligible({ width: undefined })).toBe(false);
    });

    it('v1 migrate() coerces numeric width into a percent string', () => {
        const v1 = deprecated[0];
        const migrated = v1.migrate({ width: 50 });
        expect(migrated.width).toBe('50%');
    });

    it('v1 migrate() leaves a missing width out instead of storing "undefined%" (#925)', () => {
        const v1 = deprecated[0];
        const migrated = v1.migrate({ verticalAlignment: 'center', style: { spacing: { padding: { top: '2rem' } } } });
        expect('width' in migrated).toBe(false);
        expect(migrated.verticalAlignment).toBe('center');
        expect(migrated.style).toEqual({ spacing: { padding: { top: '2rem' } } });
    });

    it('v1 migrate() drops the folded wp-block-column class and keeps custom ones (#925)', () => {
        const v1 = deprecated[0];
        expect('className' in v1.migrate({ className: 'wp-block-column' })).toBe(false);
        expect(v1.migrate({ className: 'wp-block-column is-style-boxed' }).className).toBe('is-style-boxed');
    });

    it('v1 renders a div wrapper with flex-basis style', () => {
        const v1 = deprecated[0];
        const html = renderToStaticMarkup(
            v1.save({ attributes: { width: 50, verticalAlignment: 'top' } })
        );
        expect(html).toContain('<div');
        expect(html).toContain('flex-basis');
        expect(html).toContain('is-vertically-aligned-top');
    });
});
