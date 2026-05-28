/**
 * Transforms tests for `artisanpack/media-text`.
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
        innerBlocks: unknown[];
    };
}

function findFrom(blockName: string): BlockTransform {
    return transforms.from.find(
        (entry) =>
            entry.type === 'block' &&
            Array.isArray((entry as { blocks?: string[] }).blocks) &&
            (entry as { blocks: string[] }).blocks[0] === blockName
    ) as unknown as BlockTransform;
}

function findTo(blockName: string): BlockTransform {
    return transforms.to.find(
        (entry) =>
            entry.type === 'block' &&
            Array.isArray((entry as { blocks?: string[] }).blocks) &&
            (entry as { blocks: string[] }).blocks[0] === blockName
    ) as unknown as BlockTransform;
}

describe('media-text transforms', () => {
    it('declares from/to arrays', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('core/image → artisanpack/media-text maps to image media', () => {
        const t = findFrom('core/image');
        const result = t.transform({
            alt: 'pic',
            url: 'https://example.com/pic.jpg',
            id: 7,
        });
        expect(result.name).toBe('artisanpack/media-text');
        expect(result.attributes).toMatchObject({
            mediaAlt: 'pic',
            mediaId: 7,
            mediaUrl: 'https://example.com/pic.jpg',
            mediaType: 'image',
        });
    });

    it('core/video → artisanpack/media-text maps to video media', () => {
        const t = findFrom('core/video');
        const result = t.transform({
            src: 'https://example.com/clip.mp4',
            id: 9,
        });
        expect(result.name).toBe('artisanpack/media-text');
        expect(result.attributes).toMatchObject({
            mediaUrl: 'https://example.com/clip.mp4',
            mediaId: 9,
            mediaType: 'video',
        });
    });

    it('core/media-text → artisanpack/media-text round-trips attributes + innerBlocks', () => {
        const t = findFrom('core/media-text');
        const inner = [{ name: 'core/paragraph', attributes: {} }];
        const result = t.transform(
            {
                mediaUrl: 'https://example.com/pic.jpg',
                mediaType: 'image',
                mediaPosition: 'right',
                mediaWidth: 60,
            },
            inner
        );
        expect(result.name).toBe('artisanpack/media-text');
        expect(result.attributes).toMatchObject({
            mediaUrl: 'https://example.com/pic.jpg',
            mediaType: 'image',
            mediaPosition: 'right',
            mediaWidth: 60,
        });
        expect(result.innerBlocks).toEqual(inner);
    });

    it('artisanpack/media-text → core/media-text round-trips attributes + innerBlocks', () => {
        const t = findTo('core/media-text');
        const inner = [{ name: 'core/paragraph', attributes: {} }];
        const result = t.transform(
            {
                mediaUrl: 'https://example.com/pic.jpg',
                mediaType: 'image',
                mediaWidth: 75,
            },
            inner
        );
        expect(result.name).toBe('core/media-text');
        expect(result.attributes).toMatchObject({
            mediaUrl: 'https://example.com/pic.jpg',
            mediaType: 'image',
            mediaWidth: 75,
        });
        expect(result.innerBlocks).toEqual(inner);
    });

    it('to core/image only matches when mediaType is image or unset', () => {
        const t = findTo('core/image');
        expect(t.isMatch?.({ mediaType: 'image' })).toBe(true);
        expect(t.isMatch?.({ mediaType: undefined, mediaUrl: undefined })).toBe(
            true
        );
        expect(t.isMatch?.({ mediaType: 'video', mediaUrl: 'x' })).toBe(false);
    });

    it('to core/video only matches when mediaType is video or unset', () => {
        const t = findTo('core/video');
        expect(t.isMatch?.({ mediaType: 'video' })).toBe(true);
        expect(t.isMatch?.({ mediaType: undefined, mediaUrl: undefined })).toBe(
            true
        );
        expect(t.isMatch?.({ mediaType: 'image', mediaUrl: 'x' })).toBe(false);
    });

    it('to core/cover preserves dimRatio based on media presence', () => {
        const t = findTo('core/cover');
        const withMedia = t.transform(
            {
                mediaUrl: 'https://example.com/pic.jpg',
                mediaType: 'image',
            },
            []
        );
        expect(withMedia.attributes.dimRatio).toBe(50);

        const withoutMedia = t.transform({}, []);
        expect(withoutMedia.attributes.dimRatio).toBe(100);
    });
});
