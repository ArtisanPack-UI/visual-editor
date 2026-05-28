/**
 * Transforms tests for `artisanpack/image`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blob', () => ({
    createBlobURL: (file: File) => `blob:mock/${file.name}`,
    isBlobURL: (url?: string) => !!url && url.startsWith('blob:'),
}));

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
    getBlockAttributes: (
        _name: string,
        _markup: string,
        extras?: Record<string, unknown>
    ) => ({
        ...extras,
    }),
}));

import transforms from '../transforms';

interface BlockTransform {
    type: string;
    blocks?: string[];
    transform: (attrs: Record<string, unknown>) => {
        name: string;
        attributes: Record<string, unknown>;
    };
}

interface FilesTransform {
    type: string;
    isMatch: (files: File[]) => boolean;
    transform: (files: File[]) => Array<{
        name: string;
        attributes: Record<string, unknown>;
    }>;
}

describe('image transforms', () => {
    it('declares from/to arrays', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('files transform matches any number of image files', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'files'
        ) as unknown as FilesTransform;
        const a = new File(['x'], 'a.jpg', { type: 'image/jpeg' });
        const b = new File(['x'], 'b.png', { type: 'image/png' });
        expect(t.isMatch([a])).toBe(true);
        expect(t.isMatch([a, b])).toBe(true);
        const blocks = t.transform([a, b]);
        expect(blocks).toHaveLength(2);
        expect(blocks[0].name).toBe('artisanpack/image');
        expect(blocks[0].attributes.blob).toBe('blob:mock/a.jpg');
    });

    it('files transform rejects non-image files', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'files'
        ) as unknown as FilesTransform;
        const audio = new File(['x'], 'a.mp3', { type: 'audio/mpeg' });
        expect(t.isMatch([audio])).toBe(false);
    });

    it('core/image → artisanpack/image round-trips attributes', () => {
        const fromBlock = transforms.from.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/image'
        ) as unknown as BlockTransform;
        const result = fromBlock.transform({
            url: 'https://example.com/photo.jpg',
            alt: 'A',
            id: 7,
        });
        expect(result.name).toBe('artisanpack/image');
        expect(result.attributes).toMatchObject({
            url: 'https://example.com/photo.jpg',
            alt: 'A',
            id: 7,
        });
    });

    it('artisanpack/image → core/image round-trips attributes', () => {
        const toBlock = transforms.to.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/image'
        ) as unknown as BlockTransform;
        const result = toBlock.transform({
            url: 'https://example.com/photo.jpg',
            href: 'https://example.com/page',
            linkTarget: '_blank',
        });
        expect(result.name).toBe('core/image');
        expect(result.attributes).toMatchObject({
            url: 'https://example.com/photo.jpg',
            href: 'https://example.com/page',
            linkTarget: '_blank',
        });
    });

    it('declares a raw figure → img matcher', () => {
        const raw = transforms.from.find((entry) => entry.type === 'raw') as
            | (typeof transforms.from)[number]
            | undefined;
        expect(raw).toBeDefined();
    });

    it('declares a caption-shortcode matcher', () => {
        const shortcode = transforms.from.find(
            (entry) => entry.type === 'shortcode'
        ) as { tag?: string } | undefined;
        expect(shortcode?.tag).toBe('caption');
    });
});
