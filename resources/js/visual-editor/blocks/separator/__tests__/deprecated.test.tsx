/**
 * Locks the deprecation chain for `artisanpack/separator`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    getColorClassName: (prefix: string, slug?: string) =>
        slug ? `has-${slug}-${prefix}` : undefined,
}));

import deprecated from '../deprecated';

describe('separator deprecation chain', () => {
    it('ships one deprecation entry (v1) matching upstream', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(1);
    });

    it('v1 declares the legacy color + customColor attribute schema', () => {
        const v1 = deprecated[0];
        expect((v1.attributes as Record<string, { type: string }>).color.type).toBe(
            'string'
        );
        expect(
            (v1.attributes as Record<string, { type: string }>).customColor.type
        ).toBe('string');
    });

    it('v1 migrate() upgrades the legacy color → backgroundColor + opacity:css', () => {
        const v1 = deprecated[0];
        const migrated = v1.migrate({ color: 'red', customColor: '#abcdef' });
        expect(migrated.backgroundColor).toBe('red');
        expect(migrated.opacity).toBe('css');
        expect(migrated.tagName).toBe('hr');
        expect(migrated.style).toEqual({ color: { background: '#abcdef' } });
    });

    it('v1 renders an <hr> tag in legacy markup', () => {
        const v1 = deprecated[0];
        const html = renderToStaticMarkup(
            v1.save({ attributes: { color: 'red', customColor: undefined } })
        );
        expect(html).toContain('<hr');
        expect(html).toContain('has-text-color');
    });
});
