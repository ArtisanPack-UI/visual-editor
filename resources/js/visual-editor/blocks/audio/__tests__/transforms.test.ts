/**
 * Transforms tests for `artisanpack/audio`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blob', () => ({
    createBlobURL: (file: File) => `blob:mock/${file.name}`,
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
    transform: (files: File[]) => { name: string; attributes: Record<string, unknown> };
}

describe('audio transforms', () => {
    it('declares from/to arrays', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('files transform matches a single audio file', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'files'
        ) as unknown as FilesTransform;
        const file = new File(['x'], 'song.mp3', { type: 'audio/mpeg' });
        expect(t.isMatch([file])).toBe(true);
        const result = t.transform([file]);
        expect(result.name).toBe('artisanpack/audio');
        expect(result.attributes.blob).toBe('blob:mock/song.mp3');
    });

    it('files transform rejects two files or non-audio types', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'files'
        ) as unknown as FilesTransform;
        const audio = new File(['x'], 'a.mp3', { type: 'audio/mpeg' });
        const image = new File(['x'], 'a.png', { type: 'image/png' });
        expect(t.isMatch([audio, audio])).toBe(false);
        expect(t.isMatch([image])).toBe(false);
    });

    it('core/audio → artisanpack/audio round-trips attributes', () => {
        const fromBlock = transforms.from.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/audio'
        ) as unknown as BlockTransform;
        const result = fromBlock.transform({
            src: 'https://example.com/song.mp3',
            loop: true,
        });
        expect(result.name).toBe('artisanpack/audio');
        expect(result.attributes).toMatchObject({
            src: 'https://example.com/song.mp3',
            loop: true,
        });
    });

    it('artisanpack/audio → core/audio round-trips attributes', () => {
        const toBlock = transforms.to.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/audio'
        ) as unknown as BlockTransform;
        const result = toBlock.transform({
            src: 'https://example.com/song.mp3',
            autoplay: true,
        });
        expect(result.name).toBe('core/audio');
        expect(result.attributes).toMatchObject({
            src: 'https://example.com/song.mp3',
            autoplay: true,
        });
    });
});
