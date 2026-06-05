/**
 * Locks the (intentionally simplified) deprecation chain for
 * `artisanpack/button`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(
        () => null,
        {
            Content: (props: Record<string, unknown> & { tagName?: string }) => {
                const Tag = (props.tagName as 'a') || 'a';
                return <Tag {...(props as Record<string, unknown>)} />;
            },
        }
    ),
    getColorClassName: (prefix: string, slug?: string) =>
        slug ? `has-${slug}-${prefix}` : undefined,
}));

import deprecated from '../deprecated';

describe('button deprecation chain (simplified fork)', () => {
    it('ships exactly the two ported entries', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(2);
    });

    it('width entry is eligible only for legacy percentage values', () => {
        const widthEntry = deprecated[0];
        expect(widthEntry.isEligible({ width: 25 })).toBe(true);
        expect(widthEntry.isEligible({ width: 50 })).toBe(true);
        expect(widthEntry.isEligible({ width: 33 })).toBe(false);
        expect(widthEntry.isEligible({})).toBe(false);
    });

    it('width entry migrates legacy width number → style.dimensions.width %', () => {
        const widthEntry = deprecated[0];
        const migrated = widthEntry.migrate({ width: 50 });
        expect(migrated.width).toBeUndefined();
        expect(migrated.style?.dimensions?.width).toBe('50%');
    });

    it('v1 entry is eligible when legacy customBackgroundColor / customTextColor are set', () => {
        const v1 = deprecated[1];
        expect(v1.isEligible({ customBackgroundColor: '#fff' })).toBe(true);
        expect(v1.isEligible({ customTextColor: '#000' })).toBe(true);
        expect(v1.isEligible({})).toBe(false);
    });

    it('v1 entry migrates customColor attrs → style.color block', () => {
        const v1 = deprecated[1];
        const migrated = v1.migrate({
            customBackgroundColor: '#aabbcc',
            customTextColor: '#112233',
            text: 'Hi',
            url: '/',
        });
        expect(migrated.customBackgroundColor).toBeUndefined();
        expect(migrated.customTextColor).toBeUndefined();
        expect(migrated.style).toEqual({
            color: { background: '#aabbcc', text: '#112233' },
        });
    });

    it('v1 save renders an <a> wrapped in the block div', () => {
        const v1 = deprecated[1];
        const html = renderToStaticMarkup(
            v1.save({
                attributes: {
                    text: 'X',
                    url: '/y',
                    customBackgroundColor: '#abcdef',
                },
            })
        );
        expect(html).toContain('<div');
        expect(html).toContain('<a');
        expect(html).toContain('wp-block-button__link');
    });
});
