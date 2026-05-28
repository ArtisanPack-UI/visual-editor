/**
 * Transforms tests for `artisanpack/gallery`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blob', () => ({
    createBlobURL: (file: File) => `blob:mock/${file.name}`,
}));

vi.mock('@wordpress/blocks', () => ({
    createBlock: (
        name: string,
        attributes?: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: innerBlocks ?? [],
    }),
}));

vi.mock('@wordpress/hooks', () => ({
    addFilter: vi.fn(),
}));

import transforms from '../transforms';

interface BlockTransform {
    type: string;
    blocks?: string[];
    isMultiBlock?: boolean;
    transform: (
        attrs: Record<string, unknown> | Record<string, unknown>[],
        innerBlocks?: unknown[]
    ) => {
        name: string;
        attributes: Record<string, unknown>;
        innerBlocks: unknown[];
    };
}

interface FilesTransform {
    type: string;
    priority?: number;
    isMatch: (files: File[]) => boolean;
    transform: (files: File[]) => {
        name: string;
        innerBlocks: unknown[];
        attributes: Record<string, unknown>;
    };
}

interface ShortcodeTransform {
    type: string;
    tag: string;
    transform: (args: { named: Record<string, string | number> }) => {
        name: string;
        attributes: Record<string, unknown>;
        innerBlocks: unknown[];
    };
    isMatch: (args: { named: Record<string, string | number> }) => boolean;
}

describe('gallery transforms', () => {
    it('declares from/to arrays', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('files transform matches multiple image files', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'files'
        ) as unknown as FilesTransform;
        const a = new File(['x'], 'a.png', { type: 'image/png' });
        const b = new File(['x'], 'b.png', { type: 'image/png' });
        expect(t.isMatch([a, b])).toBe(true);
        const result = t.transform([a, b]);
        expect(result.name).toBe('artisanpack/gallery');
        expect(result.innerBlocks).toHaveLength(2);
    });

    it('files transform rejects single files (defers to core/image)', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'files'
        ) as unknown as FilesTransform;
        const single = new File(['x'], 'a.png', { type: 'image/png' });
        expect(t.isMatch([single])).toBe(false);
    });

    it('files transform rejects non-image files', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'files'
        ) as unknown as FilesTransform;
        const a = new File(['x'], 'a.png', { type: 'image/png' });
        const b = new File(['x'], 'b.mp3', { type: 'audio/mpeg' });
        expect(t.isMatch([a, b])).toBe(false);
    });

    it('shortcode transform parses [gallery ids=...]', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'shortcode'
        ) as unknown as ShortcodeTransform;
        expect(t.isMatch({ named: { ids: '1,2,3' } })).toBe(true);
        const block = t.transform({
            named: { ids: '1,2,3', columns: 3, link: 'file' },
        });
        expect(block.name).toBe('artisanpack/gallery');
        expect(block.attributes.columns).toBe(3);
        expect(block.attributes.linkTo).toBe('media');
        expect(block.innerBlocks).toHaveLength(3);
    });

    it('core/gallery → artisanpack/gallery forwards attributes + innerBlocks', () => {
        const fromBlock = transforms.from.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/gallery'
        ) as unknown as BlockTransform;
        const inner = [
            {
                name: 'core/image',
                attributes: { url: 'a.png' },
                innerBlocks: [],
            },
        ];
        const result = fromBlock.transform({ columns: 2 }, inner);
        expect(result.name).toBe('artisanpack/gallery');
        expect(result.attributes).toMatchObject({ columns: 2 });
        expect(result.innerBlocks).toEqual(inner);
    });

    it('artisanpack/gallery → core/gallery forwards attributes + innerBlocks', () => {
        const toBlock = transforms.to.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/gallery'
        ) as unknown as BlockTransform;
        const inner = [
            {
                name: 'core/image',
                attributes: { url: 'a.png' },
                innerBlocks: [],
            },
        ];
        const result = toBlock.transform({ imageCrop: false }, inner);
        expect(result.name).toBe('core/gallery');
        expect(result.attributes).toMatchObject({ imageCrop: false });
        expect(result.innerBlocks).toEqual(inner);
    });

    it('core/image multi → artisanpack/gallery transform creates inner image blocks', () => {
        const fromImage = transforms.from.find(
            (entry) =>
                entry.type === 'block' &&
                (entry as BlockTransform).isMultiBlock &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/image'
        ) as unknown as BlockTransform;
        const attrs = [
            { url: 'a.png', align: 'wide' },
            { url: 'b.png', align: 'wide' },
        ];
        const result = fromImage.transform(attrs as Record<string, unknown>[]);
        expect(result.name).toBe('artisanpack/gallery');
        expect(result.attributes.align).toBe('wide');
        expect(result.innerBlocks).toHaveLength(2);
    });
});
