/**
 * Locks the deprecation chain for `artisanpack/verse`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(() => null, {
        Content: ({
            value,
            tagName,
        }: { value?: string; tagName?: string }) => {
            const Tag = (tagName ?? 'pre') as keyof JSX.IntrinsicElements;
            return value !== undefined ? (
                <Tag dangerouslySetInnerHTML={{ __html: value }} />
            ) : null;
        },
    }),
}));

import deprecated from '../deprecated';

describe('verse deprecation chain', () => {
    it('has 3 historical entries', () => {
        expect(deprecated).toHaveLength(3);
    });

    it('every entry exposes a save callable', () => {
        deprecated.forEach((entry, i) => {
            expect(
                typeof (entry as { save?: unknown }).save,
                `entry ${i}`
            ).toBe('function');
        });
    });

    it('v3 (entries[0]) renders pre with has-text-align-* class', () => {
        const entry = deprecated[0] as {
            save: (p: { attributes: Record<string, unknown> }) => React.ReactElement;
        };
        const html = renderToStaticMarkup(
            entry.save({ attributes: { textAlign: 'center', content: 'x' } })
        );
        expect(html).toContain('<pre');
        expect(html).toContain('has-text-align-center');
    });

    it('v3 migrates textAlign into style.typography.textAlign', () => {
        const entry = deprecated[0] as {
            migrate: (a: Record<string, unknown>) => Record<string, unknown>;
        };
        const migrated = entry.migrate({ textAlign: 'right', content: 'x' });
        expect(migrated.style).toEqual({ typography: { textAlign: 'right' } });
    });
});
