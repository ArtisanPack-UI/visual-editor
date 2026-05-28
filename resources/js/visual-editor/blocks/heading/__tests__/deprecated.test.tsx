/**
 * Locks the deprecation chain for `artisanpack/heading`.
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
            ...rest
        }: { value?: string; tagName?: string } & Record<string, unknown>) => {
            const Tag = (tagName ?? 'h2') as keyof JSX.IntrinsicElements;
            return value !== undefined ? (
                <Tag {...rest} dangerouslySetInnerHTML={{ __html: value }} />
            ) : null;
        },
    }),
    getColorClassName: (prefix: string, slug?: string) =>
        slug ? `has-${slug}-${prefix}` : undefined,
}));

import deprecated from '../deprecated';

describe('heading deprecation chain', () => {
    it('has 6 historical entries', () => {
        expect(deprecated).toHaveLength(6);
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

    it('v6 (entries[0]) renders the heading tag with has-text-align-* class', () => {
        const entry = deprecated[0] as {
            save: (props: { attributes: Record<string, unknown> }) => React.ReactElement;
        };
        const html = renderToStaticMarkup(
            entry.save({
                attributes: { textAlign: 'center', content: 'hi', level: 3 },
            })
        );
        expect(html).toContain('<h3');
        expect(html).toContain('has-text-align-center');
        expect(html).toContain('hi');
    });

    it('v6 migrates textAlign into style.typography.textAlign', () => {
        const entry = deprecated[0] as {
            migrate: (attrs: Record<string, unknown>) => Record<string, unknown>;
        };
        const migrated = entry.migrate({
            textAlign: 'center',
            content: 'x',
            level: 2,
        });
        expect(migrated.style).toEqual({
            typography: { textAlign: 'center' },
        });
    });

    it('v1 (entries[5]) renders RichText.Content with color classes', () => {
        const entry = deprecated[5] as {
            save: (props: { attributes: Record<string, unknown> }) => React.ReactElement;
        };
        const html = renderToStaticMarkup(
            entry.save({
                attributes: {
                    align: 'right',
                    content: 'body',
                    level: 2,
                    textColor: 'primary',
                },
            })
        );
        expect(html).toContain('has-primary-color');
    });
});
