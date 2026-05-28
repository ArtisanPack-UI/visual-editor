/**
 * Transforms tests for `artisanpack/video`.
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
    transform: (files: File[]) => {
        name: string;
        attributes: Record<string, unknown>;
    };
}

describe('video transforms', () => {
    it('declares from/to arrays', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('files transform matches a single video file', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'files'
        ) as unknown as FilesTransform;
        const file = new File(['x'], 'clip.mp4', { type: 'video/mp4' });
        expect(t.isMatch([file])).toBe(true);
        const result = t.transform([file]);
        expect(result.name).toBe('artisanpack/video');
        expect(result.attributes.blob).toBe('blob:mock/clip.mp4');
    });

    it('files transform rejects two files or non-video types', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'files'
        ) as unknown as FilesTransform;
        const video = new File(['x'], 'a.mp4', { type: 'video/mp4' });
        const image = new File(['x'], 'a.png', { type: 'image/png' });
        expect(t.isMatch([video, video])).toBe(false);
        expect(t.isMatch([image])).toBe(false);
    });

    it('core/video → artisanpack/video round-trips attributes', () => {
        const fromBlock = transforms.from.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/video'
        ) as unknown as BlockTransform;
        const result = fromBlock.transform({
            src: 'https://example.com/clip.mp4',
            loop: true,
            controls: true,
            poster: 'https://example.com/poster.jpg',
        });
        expect(result.name).toBe('artisanpack/video');
        expect(result.attributes).toMatchObject({
            src: 'https://example.com/clip.mp4',
            loop: true,
            controls: true,
            poster: 'https://example.com/poster.jpg',
        });
    });

    it('artisanpack/video → core/video round-trips attributes', () => {
        const toBlock = transforms.to.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/video'
        ) as unknown as BlockTransform;
        const result = toBlock.transform({
            src: 'https://example.com/clip.mp4',
            autoplay: true,
            playsInline: true,
        });
        expect(result.name).toBe('core/video');
        expect(result.attributes).toMatchObject({
            src: 'https://example.com/clip.mp4',
            autoplay: true,
            playsInline: true,
        });
    });
});
