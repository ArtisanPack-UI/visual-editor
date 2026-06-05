/**
 * Transforms tests for `artisanpack/cover`.
 */

import { describe, it, expect, vi } from 'vitest';

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

import transforms from '../transforms';

interface BlockTransform {
    type: string;
    blocks?: string[];
    isMatch?: (attrs: Record<string, unknown>) => boolean;
    transform: (
        attrs: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => {
        name: string;
        attributes: Record<string, unknown>;
        innerBlocks?: unknown[];
    };
}

describe('cover transforms', () => {
    it('declares from/to arrays', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('image → cover preserves url and sets dimRatio 50', () => {
        const t = transforms.from.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/image'
        ) as unknown as BlockTransform;
        const result = t.transform({
            url: 'https://example.com/photo.jpg',
            alt: 'alt',
            id: 5,
            caption: 'Caption',
        });
        expect(result.name).toBe('artisanpack/cover');
        expect(result.attributes.url).toBe('https://example.com/photo.jpg');
        expect(result.attributes.dimRatio).toBe(50);
    });

    it('video → cover sets backgroundType=video', () => {
        const t = transforms.from.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/video'
        ) as unknown as BlockTransform;
        const result = t.transform({
            src: 'https://example.com/clip.mp4',
            id: 7,
            caption: 'Caption',
        });
        expect(result.name).toBe('artisanpack/cover');
        expect(result.attributes.url).toBe('https://example.com/clip.mp4');
        expect(result.attributes.backgroundType).toBe('video');
    });

    it('core/cover → artisanpack/cover round-trips attributes and innerBlocks', () => {
        const fromBlock = transforms.from.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/cover'
        ) as unknown as BlockTransform;
        const result = fromBlock.transform(
            {
                url: 'https://example.com/photo.jpg',
                dimRatio: 50,
                tagName: 'section',
            },
            [{ name: 'core/paragraph', attributes: {} }]
        );
        expect(result.name).toBe('artisanpack/cover');
        expect(result.attributes).toMatchObject({
            url: 'https://example.com/photo.jpg',
            tagName: 'section',
        });
        expect(result.innerBlocks).toHaveLength(1);
    });

    it('artisanpack/cover → core/cover round-trips attributes and innerBlocks', () => {
        const toBlock = transforms.to.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/cover'
        ) as unknown as BlockTransform;
        const result = toBlock.transform(
            {
                url: 'https://example.com/photo.jpg',
                dimRatio: 50,
            },
            [{ name: 'core/paragraph', attributes: {} }]
        );
        expect(result.name).toBe('core/cover');
        expect(result.attributes).toMatchObject({
            url: 'https://example.com/photo.jpg',
            dimRatio: 50,
        });
        expect(result.innerBlocks).toHaveLength(1);
    });

    it('cover → image isMatch passes only when backgroundType=image (or no url/overlay/gradient)', () => {
        const toImage = transforms.to.find(
            (entry) =>
                entry.type === 'block' &&
                Array.isArray((entry as { blocks?: string[] }).blocks) &&
                (entry as { blocks: string[] }).blocks[0] === 'core/image'
        ) as unknown as BlockTransform;
        expect(
            toImage.isMatch!({
                url: 'https://example.com/x.jpg',
                backgroundType: 'image',
            })
        ).toBe(true);
        expect(
            toImage.isMatch!({
                url: 'https://example.com/x.mp4',
                backgroundType: 'video',
            })
        ).toBe(false);
        expect(toImage.isMatch!({})).toBe(true);
    });
});
