/**
 * Locks the deprecation chain for `artisanpack/media-text`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    useInnerBlocksProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    InnerBlocks: Object.assign(() => null, {
        Content: () => null,
    }),
    getColorClassName: (_prefix: string, slug?: string) =>
        slug ? `has-${slug}-background-color` : undefined,
}));

vi.mock('@wordpress/compose', () => ({
    compose: (...fns: Array<(value: unknown) => unknown>) =>
        (value: unknown) =>
            fns.reduceRight((acc, fn) => fn(acc), value),
}));

import deprecated from '../deprecated';

describe('media-text deprecation chain', () => {
    it('ships seven deprecation entries matching upstream (v7…v1)', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(7);
    });

    it('locks the v7 align default to "none"', () => {
        const v7 = deprecated[0];
        expect((v7.attributes as Record<string, { default?: string }>).align.default).toBe('none');
    });

    it('locks the v6 align default to "wide"', () => {
        const v6 = deprecated[1];
        expect((v6.attributes as Record<string, { default?: string }>).align.default).toBe('wide');
    });

    it('v6 isEligible returns true only when align is undefined and finalized className includes alignwide', () => {
        const v6 = deprecated[1] as unknown as {
            isEligible: (
                attrs: Record<string, unknown>,
                innerBlocks: unknown,
                ctx: { block: { attributes: Record<string, unknown> } }
            ) => boolean;
        };
        expect(
            v6.isEligible(
                {},
                [],
                { block: { attributes: { className: 'alignwide' } } }
            )
        ).toBe(true);
        expect(
            v6.isEligible(
                { align: 'wide' },
                [],
                { block: { attributes: { className: 'alignwide' } } }
            )
        ).toBe(false);
        expect(
            v6.isEligible(
                {},
                [],
                { block: { attributes: { className: 'something-else' } } }
            )
        ).toBe(false);
    });

    it('migrateDefaultAlign (used by v1, v4, v5, v6) sets align to "wide" when missing', () => {
        const v1 = deprecated[6] as unknown as {
            migrate: (attrs: Record<string, unknown>) => Record<string, unknown>;
        };
        const result = v1.migrate({ mediaWidth: 50 });
        expect(result.align).toBe('wide');

        const passthrough = v1.migrate({ align: 'full', mediaWidth: 50 });
        expect(passthrough.align).toBe('full');
    });

    it('migrateCustomColors (used by v2, v3) lifts customBackgroundColor into style.color.background', () => {
        const v3 = deprecated[4] as unknown as {
            migrate: (attrs: Record<string, unknown>) => Record<string, unknown>;
        };
        const result = v3.migrate({
            customBackgroundColor: '#abc123',
        });
        // The composed pipeline (custom colors → default align) returns:
        //   { style: { color: { background: '#abc123' } }, align: 'wide' }
        expect((result.style as { color: { background: string } }).color.background).toBe(
            '#abc123'
        );
        expect(result.customBackgroundColor).toBeUndefined();
        expect(result.align).toBe('wide');
    });

    it('renders v7 markup with the legacy is-image-fill background structure when imageFill is set', () => {
        const v7 = deprecated[0];
        const html = renderToStaticMarkup(
            v7.save({
                attributes: {
                    mediaUrl: 'https://example.com/pic.jpg',
                    mediaType: 'image',
                    imageFill: true,
                    focalPoint: { x: 0.25, y: 0.75 },
                },
            })
        );
        expect(html).toContain('is-image-fill');
        // v7 uses background-image inline styles (not object-position).
        expect(html).toContain('background-image');
    });

    it('renders v1 markup with the inner-blocks legacy content container', () => {
        const v1 = deprecated[6];
        const html = renderToStaticMarkup(
            v1.save({
                attributes: {
                    mediaUrl: 'https://example.com/pic.jpg',
                    mediaType: 'image',
                    mediaAlt: 'pic',
                },
            })
        );
        expect(html).toContain('wp-block-media-text__media');
        expect(html).toContain('wp-block-media-text__content');
    });
});
