/**
 * Transforms tests for `artisanpack/file`.
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

vi.mock('@wordpress/data', () => ({
    select: () => ({
        getEntityRecord: () => ({ mime_type: 'application/pdf' }),
    }),
}));

vi.mock('@wordpress/core-data', () => ({
    store: 'core-store',
}));

vi.mock('@wordpress/url', () => ({
    getFilename: (url?: string) => (url ?? '').split('/').pop() ?? '',
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

describe('file transforms', () => {
    it('declares from/to arrays', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('files transform matches any non-empty file list', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'files'
        ) as unknown as FilesTransform;
        const file = new File(['x'], 'doc.pdf', { type: 'application/pdf' });
        expect(t.isMatch([file])).toBe(true);
        expect(t.isMatch([])).toBe(false);
    });

    it('files transform routes mime types to the right block names', () => {
        const t = transforms.from.find(
            (entry) => entry.type === 'files'
        ) as unknown as FilesTransform;
        const pdf = new File(['x'], 'doc.pdf', { type: 'application/pdf' });
        const audio = new File(['x'], 'song.mp3', { type: 'audio/mpeg' });
        const video = new File(['x'], 'movie.mp4', { type: 'video/mp4' });
        const image = new File(['x'], 'pic.png', { type: 'image/png' });

        const blocks = t.transform([pdf, audio, video, image]);
        expect(blocks).toHaveLength(4);
        expect(blocks[0].name).toBe('artisanpack/file');
        expect(blocks[0].attributes.fileName).toBe('doc.pdf');
        expect(blocks[1].name).toBe('core/audio');
        expect(blocks[2].name).toBe('core/video');
        expect(blocks[3].name).toBe('core/image');
    });

    it('core/file → artisanpack/file round-trips attributes', () => {
        const fromBlock = transforms.from.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/file'
        ) as unknown as BlockTransform;
        const result = fromBlock.transform({
            href: 'https://example.com/doc.pdf',
            fileName: 'doc.pdf',
            showDownloadButton: true,
        });
        expect(result.name).toBe('artisanpack/file');
        expect(result.attributes).toMatchObject({
            href: 'https://example.com/doc.pdf',
            fileName: 'doc.pdf',
            showDownloadButton: true,
        });
    });

    it('artisanpack/file → core/file round-trips attributes', () => {
        const toBlock = transforms.to.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/file'
        ) as unknown as BlockTransform;
        const result = toBlock.transform({
            href: 'https://example.com/doc.pdf',
            fileName: 'doc.pdf',
            showDownloadButton: true,
        });
        expect(result.name).toBe('core/file');
        expect(result.attributes).toMatchObject({
            href: 'https://example.com/doc.pdf',
            fileName: 'doc.pdf',
        });
    });

    it('core/audio → artisanpack/file maps src to href and caption to fileName', () => {
        const fromBlock = transforms.from.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/audio'
        ) as unknown as BlockTransform;
        const result = fromBlock.transform({
            src: 'https://example.com/song.mp3',
            caption: 'My Song',
            id: 42,
        });
        expect(result.name).toBe('artisanpack/file');
        expect(result.attributes.href).toBe('https://example.com/song.mp3');
        expect(result.attributes.fileName).toBe('My Song');
        expect(result.attributes.id).toBe(42);
    });
});
