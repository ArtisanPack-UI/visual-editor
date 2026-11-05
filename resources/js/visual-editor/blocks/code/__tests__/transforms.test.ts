/**
 * Transforms tests for `artisanpack/code`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
    getBlockAttributes: (_name: string, html: string) => ({
        content: html.replace(/<[^>]+>/g, ''),
    }),
}));

vi.mock('@wordpress/rich-text', () => ({
    create: (input: { text: string }) => ({ text: input.text }),
    toHTMLString: ({ value }: { value: { text: string } }) => value.text,
}));

import transforms from '../transforms';

describe('code transforms', () => {
    it('declares from/to arrays', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('input transform on ``` creates an empty code block', () => {
        const input = transforms.from.find(
            (t: { type: string }) => t.type === 'input'
        ) as { transform: () => { name: string } };
        expect(input.transform().name).toBe('artisanpack/code');
    });

    it('paragraph → code preserves content', () => {
        const fromParagraph = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.includes('core/paragraph')
        ) as {
            transform: (a: Record<string, unknown>) => {
                name: string;
                attributes: Record<string, unknown>;
            };
        };
        const result = fromParagraph.transform({ content: 'hi' });
        expect(result.name).toBe('artisanpack/code');
        expect(result.attributes).toMatchObject({ content: 'hi' });
    });

    describe('raw transform', () => {
        const raw = transforms.from.find(
            (t: { type: string }) => t.type === 'raw'
        ) as unknown as {
            transform: (node: HTMLElement) => {
                attributes: Record<string, unknown>;
            };
        };

        const pre = (html: string): HTMLElement => {
            const element = document.createElement('pre');
            element.innerHTML = html;
            return element;
        };

        it.each([
            ['language-php', 'php'],
            ['hljs language-js', 'javascript'],
            ['php language-php', 'php'],
            ['lang-ts', 'typescript'],
        ])('reads the language from class "%s"', (className, language) => {
            const { attributes } = raw.transform(
                pre(`<code class="${className}">x</code>`)
            );
            expect(attributes).toEqual({ content: 'x', language });
        });

        it('omits a language the block does not list', () => {
            const { attributes } = raw.transform(
                pre('<code class="language-cobol">x</code>')
            );
            expect(attributes).toEqual({ content: 'x' });
        });

        it('omits the language when the code has no class', () => {
            const { attributes } = raw.transform(pre('<code>x</code>'));
            expect(attributes).toEqual({ content: 'x' });
        });
    });

    it('block transform from core/code → artisanpack/code', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' &&
                t.blocks?.length === 1 &&
                t.blocks[0] === 'core/code'
        ) as {
            transform: (a: Record<string, unknown>) => { name: string };
        };
        expect(fromBlock.transform({ content: 'x' }).name).toBe('artisanpack/code');
    });

    it('block transform to core/code', () => {
        const toBlock = transforms.to.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.[0] === 'core/code'
        ) as {
            transform: (a: Record<string, unknown>) => { name: string };
        };
        expect(toBlock.transform({ content: 'x' }).name).toBe('core/code');
    });
});
