/**
 * Transforms tests for `artisanpack/embed`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
    getBlockType: () => ({ name: 'artisanpack/embed' }),
    getBlockVariations: () => [],
}));

vi.mock('@wordpress/element', () => ({
    renderToString: () => '<a href="x">x</a>',
}));

import transforms from '../transforms';

interface BlockTransform {
    type: string;
    blocks?: string[];
    isMatch?: (attrs: Record<string, unknown>) => boolean;
    transform: (attrs: Record<string, unknown>) => {
        name: string;
        attributes: Record<string, unknown>;
    };
}

interface RawTransform {
    type: string;
    isMatch: (node: { nodeName?: string; textContent?: string | null }) => boolean;
    transform: (node: { nodeName?: string; textContent?: string | null }) => {
        name: string;
        attributes: Record<string, unknown>;
    };
}

describe('embed transforms', () => {
    it('declares from/to arrays', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('raw transform matches a paragraph that contains only a URL', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'raw'
        ) as unknown as RawTransform;
        expect(
            t.isMatch({
                nodeName: 'P',
                textContent: 'https://example.com/post',
            })
        ).toBe(true);
        const result = t.transform({
            nodeName: 'P',
            textContent: '  https://example.com/post  ',
        });
        expect(result.name).toBe('artisanpack/embed');
        expect(result.attributes.url).toBe('https://example.com/post');
    });

    it('raw transform rejects paragraphs with multiple URLs', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'raw'
        ) as unknown as RawTransform;
        expect(
            t.isMatch({
                nodeName: 'P',
                textContent: 'https://a.com https://b.com',
            })
        ).toBe(false);
    });

    it('core/embed → artisanpack/embed round-trips attributes', () => {
        const fromBlock = transforms.from.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/embed'
        ) as unknown as BlockTransform;
        const result = fromBlock.transform({
            url: 'https://example.com/post',
            providerNameSlug: 'youtube',
            responsive: true,
        });
        expect(result.name).toBe('artisanpack/embed');
        expect(result.attributes).toMatchObject({
            url: 'https://example.com/post',
            providerNameSlug: 'youtube',
            responsive: true,
        });
    });

    it('artisanpack/embed → core/embed round-trips attributes', () => {
        const toBlock = transforms.to.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/embed'
        ) as unknown as BlockTransform;
        const result = toBlock.transform({
            url: 'https://example.com/post',
            type: 'video',
        });
        expect(result.name).toBe('core/embed');
        expect(result.attributes).toMatchObject({
            url: 'https://example.com/post',
            type: 'video',
        });
    });

    it('artisanpack/embed → core/paragraph builds a link, gated by url', () => {
        const toParagraph = transforms.to.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/paragraph'
        ) as unknown as BlockTransform;
        expect(toParagraph.isMatch?.({ url: 'https://example.com' })).toBe(
            true
        );
        expect(toParagraph.isMatch?.({})).toBe(false);
        const result = toParagraph.transform({
            url: 'https://example.com',
            caption: 'hi',
        });
        expect(result.name).toBe('core/paragraph');
        expect((result.attributes.content as string)).toContain(
            '<a href="https://example.com">'
        );
        expect((result.attributes.content as string)).toContain('<br />hi');
    });
});
