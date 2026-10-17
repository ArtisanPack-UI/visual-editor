/**
 * Cover save() must read the wrapper's block props before serializing its
 * inner blocks (#908). WordPress's serializer keeps one shared block-props
 * provider; serializing each inner block repoints it, so a wrapper read
 * afterwards picks up the last child's props. The mocks below model that.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

const provider = { blockClass: 'wp-block-artisanpack-cover' };

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(() => ({}), {
        save: (props: { className?: string } = {}) => ({
            ...props,
            className: [provider.blockClass, props.className].filter(Boolean).join(' '),
        }),
    }),
    useInnerBlocksProps: Object.assign(() => ({}), {
        save: (props: Record<string, unknown> = {}) => {
            // Serializing the inner blocks runs the last child's save().
            provider.blockClass = 'wp-block-artisanpack-copy-command';

            return { ...props, children: null };
        },
    }),
    getColorClassName: () => undefined,
    __experimentalGetGradientClass: (name?: string) => (name ? `has-${name}-gradient-background` : undefined),
}));

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({ name, attributes: attributes ?? {} }),
}));

vi.mock('@wordpress/compose', () => ({
    compose:
        (...fns: ((value: unknown) => unknown)[]) =>
        (value: unknown) =>
            fns.reduceRight((acc, fn) => fn(acc), value),
}));

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

import save, { renderCoverSave } from '../save';
import deprecated from '../deprecated';

const attributes = { gradient: 'neon', isDark: false, tagName: 'div' } as const;

describe('cover save props order (#908)', () => {
    beforeEach(() => {
        provider.blockClass = 'wp-block-artisanpack-cover';
    });

    it('keeps the cover its own wrapper props when it has inner blocks', () => {
        const html = renderToStaticMarkup(save({ attributes } as Parameters<typeof save>[0]));

        expect(html).toMatch(/^<div class="wp-block-artisanpack-cover[ "]/);
        expect(html).not.toContain('copy-command');
    });

    it('reproduces the pre-1.15.2 order for the deprecation that recognises those saves', () => {
        const html = renderToStaticMarkup(renderCoverSave(attributes as never, { innerBlocksFirst: true }));

        expect(html).toMatch(/^<div class="wp-block-artisanpack-copy-command/);
    });

    it('lists that deprecation first', () => {
        provider.blockClass = 'wp-block-artisanpack-cover';
        const html = renderToStaticMarkup(deprecated[0].save({ attributes } as never));

        expect(html).toMatch(/^<div class="wp-block-artisanpack-copy-command/);
    });
});
