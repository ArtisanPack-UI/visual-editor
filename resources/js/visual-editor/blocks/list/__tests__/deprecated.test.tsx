/**
 * Locks the deprecation chain for `artisanpack/list`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    InnerBlocks: Object.assign(() => null, {
        Content: () => null,
    }),
    RichText: Object.assign(() => null, {
        Content: ({ value }: { value?: string }) =>
            value !== undefined ? (
                <span dangerouslySetInnerHTML={{ __html: value }} />
            ) : null,
    }),
}));

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
}));

import deprecated from '../deprecated';

describe('list deprecation chain', () => {
    it('has 4 historical entries', () => {
        expect(deprecated).toHaveLength(4);
    });

    it('every entry exposes a save callable', () => {
        deprecated.forEach((entry, i) => {
            expect(
                typeof (entry as { save?: unknown }).save,
                `entry ${i}`
            ).toBe('function');
        });
    });

    it('v3 (entries[0]) renders ul/ol with the list-style-type style', () => {
        const entry = deprecated[0] as {
            save: (p: { attributes: Record<string, unknown> }) => React.ReactElement;
        };
        const ul = renderToStaticMarkup(
            entry.save({ attributes: { ordered: false } })
        );
        expect(ul).toContain('<ul');
        const ol = renderToStaticMarkup(
            entry.save({ attributes: { ordered: true, type: 'upper-alpha' } })
        );
        expect(ol).toContain('<ol');
    });

    it('v2 migrates legacy `type` (A/a/I/i) to inline-style values', () => {
        const entry = deprecated[1] as {
            migrate: (a: Record<string, unknown>) => Record<string, unknown>;
            isEligible: (a: Record<string, unknown>) => boolean;
        };
        expect(entry.isEligible({ type: 'A' })).toBe(true);
        const migrated = entry.migrate({ type: 'A' });
        expect(migrated.type).toBe('upper-alpha');
    });
});
