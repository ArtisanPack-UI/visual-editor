/**
 * Locks the deprecation chain for `artisanpack/paragraph`.
 *
 * CI fails if any entry's save shape changes — saved markup from old posts
 * (authored against `core/paragraph` versions 0–6) must keep deserializing
 * cleanly when those posts are opened in the editor that now registers
 * `artisanpack/paragraph`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(() => null, {
        Content: ({ value, tagName, ...rest }: { value?: string; tagName?: string } & Record<string, unknown>) => {
            const Tag = (tagName ?? 'p') as keyof JSX.IntrinsicElements;
            return value !== undefined ? (
                <Tag {...rest} dangerouslySetInnerHTML={{ __html: value }} />
            ) : null;
        },
    }),
    getColorClassName: (prefix: string, slug?: string) =>
        slug ? `has-${slug}-${prefix}` : undefined,
    getFontSizeClass: (slug?: string | number) =>
        slug ? `has-${slug}-font-size` : undefined,
}));

vi.mock('@wordpress/i18n', () => ({
    isRTL: () => false,
}));

vi.mock('@wordpress/element', async () => {
    const React = await import('react');
    return {
        RawHTML: ({ children }: { children?: React.ReactNode }) => (
            <span data-testid="raw-html" dangerouslySetInnerHTML={{ __html: String(children ?? '') }} />
        ),
    };
});

import deprecated from '../deprecated';

describe('paragraph deprecation chain', () => {
    it('has 7 historical entries (v6 → v0)', () => {
        expect(deprecated).toHaveLength(7);
    });

    it('every entry exposes a save callable', () => {
        deprecated.forEach((entry, index) => {
            expect(
                typeof (entry as { save?: unknown }).save,
                `entry ${index} save`
            ).toBe('function');
        });
    });

    it('every entry exposes attributes', () => {
        deprecated.forEach((entry, index) => {
            expect(
                (entry as { attributes?: unknown }).attributes,
                `entry ${index} attributes`
            ).toBeTruthy();
        });
    });

    it('v6 (entries[0]) migrates align → style.typography.textAlign', () => {
        const entry = deprecated[0] as {
            migrate: (attrs: Record<string, unknown>) => Record<string, unknown>;
        };
        const migrated = entry.migrate({ align: 'center', content: 'x' });
        expect(migrated).toEqual({
            content: 'x',
            style: { typography: { textAlign: 'center' } },
        });
    });

    it('v6 isEligible matches blocks that have align or has-text-align-* class', () => {
        const entry = deprecated[0] as {
            isEligible: (attrs: Record<string, unknown>) => boolean;
        };
        expect(entry.isEligible({ align: 'center' })).toBe(true);
        expect(entry.isEligible({ className: 'foo has-text-align-left bar' })).toBe(true);
        expect(entry.isEligible({ content: 'plain' })).toBe(false);
    });

    it('v6 save renders a <p> with the has-text-align-* class', () => {
        const entry = deprecated[0] as {
            save: (props: { attributes: Record<string, unknown> }) => React.ReactElement;
        };
        const html = renderToStaticMarkup(
            entry.save({
                attributes: { align: 'center', content: 'hi', dropCap: false },
            })
        );
        expect(html).toContain('<p');
        expect(html).toContain('has-text-align-center');
        expect(html).toContain('hi');
    });

    it('v4 (entries[2]) renders RichText.Content directly when fontSize class present', () => {
        const entry = deprecated[2] as {
            save: (props: { attributes: Record<string, unknown> }) => React.ReactElement;
        };
        const html = renderToStaticMarkup(
            entry.save({
                attributes: {
                    align: 'left',
                    content: 'body',
                    dropCap: false,
                    fontSize: 'large',
                    textColor: 'primary',
                },
            })
        );
        expect(html).toContain('has-large-font-size');
        expect(html).toContain('has-primary-color');
    });

    it('v0 (entries[6]) renders RawHTML for legacy string content', () => {
        const entry = deprecated[6] as {
            save: (props: { attributes: Record<string, unknown> }) => React.ReactElement;
        };
        const html = renderToStaticMarkup(
            entry.save({ attributes: { content: 'raw text' } })
        );
        expect(html).toContain('raw text');
    });

    it('migrateCustomColorsAndFontSizes lifts customTextColor into style', () => {
        const entry = deprecated[2] as {
            migrate: (attrs: Record<string, unknown>) => Record<string, unknown>;
        };
        const migrated = entry.migrate({
            content: 'x',
            customTextColor: '#ff0000',
            customFontSize: 20,
        });
        expect(migrated.style).toEqual({
            color: { text: '#ff0000' },
            typography: { fontSize: 20 },
        });
        expect(migrated).not.toHaveProperty('customTextColor');
        expect(migrated).not.toHaveProperty('customFontSize');
    });
});
