import { describe, expect, it } from 'vitest';

import {
    allowedTypesToBridgeTypes,
    mediaListToGutenberg,
    mediaToGutenberg,
} from '../adapter';
import type { BridgeMedia } from '../types';

function buildMedia(overrides: Partial<BridgeMedia> = {}): BridgeMedia {
    return {
        id: 42,
        url: 'https://cdn.example.test/images/photo.jpg',
        mime_type: 'image/jpeg',
        alt_text: 'A photograph',
        caption: 'Taken at golden hour',
        width: 1920,
        height: 1080,
        is_image: true,
        file_name: 'photo.jpg',
        metadata: null,
        ...overrides,
    };
}

describe('mediaToGutenberg', () => {
    it('maps core attachment fields', () => {
        const result = mediaToGutenberg(buildMedia());

        expect(result).toMatchObject({
            id: 42,
            url: 'https://cdn.example.test/images/photo.jpg',
            alt: 'A photograph',
            caption: 'Taken at golden hour',
            mime: 'image/jpeg',
            media_type: 'image',
            width: 1920,
            height: 1080,
            filename: 'photo.jpg',
        });
    });

    it('coerces null alt and caption to empty strings', () => {
        const result = mediaToGutenberg(
            buildMedia({ alt_text: null, caption: null })
        );

        expect(result.alt).toBe('');
        expect(result.caption).toBe('');
    });

    it('omits width/height when the source lacks dimensions', () => {
        const result = mediaToGutenberg(
            buildMedia({ width: null, height: null })
        );

        expect(result.width).toBeUndefined();
        expect(result.height).toBeUndefined();
    });

    it('uses mime_type prefix to infer media_type when flags are missing', () => {
        expect(
            mediaToGutenberg(
                buildMedia({
                    is_image: undefined,
                    mime_type: 'video/mp4',
                })
            ).media_type
        ).toBe('video');

        expect(
            mediaToGutenberg(
                buildMedia({
                    is_image: undefined,
                    mime_type: 'audio/ogg',
                })
            ).media_type
        ).toBe('audio');

        expect(
            mediaToGutenberg(
                buildMedia({
                    is_image: undefined,
                    mime_type: 'application/pdf',
                })
            ).media_type
        ).toBe('file');
    });

    it('prefers explicit is_image / is_video / is_audio flags', () => {
        const media = buildMedia({
            is_image: false,
            is_video: true,
            mime_type: 'image/jpeg',
        });

        expect(mediaToGutenberg(media).media_type).toBe('video');
    });

    it('carries through image sizes exposed in metadata', () => {
        const media = buildMedia({
            metadata: {
                sizes: {
                    thumbnail: {
                        url: 'https://cdn.example.test/thumb.jpg',
                        width: 150,
                        height: 150,
                    },
                    medium: 'https://cdn.example.test/medium.jpg',
                },
            },
        });

        const result = mediaToGutenberg(media);

        expect(result.sizes).toEqual({
            thumbnail: {
                url: 'https://cdn.example.test/thumb.jpg',
                width: 150,
                height: 150,
            },
            medium: { url: 'https://cdn.example.test/medium.jpg' },
        });
    });

    it('ignores malformed size entries in metadata', () => {
        const media = buildMedia({
            metadata: {
                sizes: {
                    missing: { width: 10 },
                    broken: 123,
                    good: { url: 'https://cdn.example.test/ok.jpg' },
                } as unknown as Record<string, unknown>,
            },
        });

        const result = mediaToGutenberg(media);

        expect(result.sizes).toEqual({
            good: { url: 'https://cdn.example.test/ok.jpg' },
        });
    });
});

describe('mediaListToGutenberg', () => {
    it('maps every entry without mutating inputs', () => {
        const list = [
            buildMedia({ id: 1, url: 'a' }),
            buildMedia({ id: 2, url: 'b', is_image: false, is_video: true }),
        ];

        const result = mediaListToGutenberg(list);

        expect(result).toHaveLength(2);
        expect(result[0]).toMatchObject({ id: 1, url: 'a' });
        expect(result[1]).toMatchObject({ id: 2, media_type: 'video' });
        expect(list[0].url).toBe('a');
    });
});

describe('allowedTypesToBridgeTypes', () => {
    it('returns undefined when no hint is supplied', () => {
        expect(allowedTypesToBridgeTypes()).toBeUndefined();
        expect(allowedTypesToBridgeTypes([])).toBeUndefined();
    });

    it('passes through the four supported categories', () => {
        expect(
            allowedTypesToBridgeTypes(['image', 'video', 'audio', 'document'])
        ).toEqual(['image', 'video', 'audio', 'document']);
    });

    it('collapses mime types onto their category', () => {
        expect(
            allowedTypesToBridgeTypes(['image/png', 'image/jpeg'])
        ).toEqual(['image']);
    });

    it('recognises application/* and text/* as documents', () => {
        expect(
            allowedTypesToBridgeTypes(['application/pdf', 'text/csv'])
        ).toEqual(['document']);
    });

    it('drops unknown values rather than widening the filter', () => {
        expect(
            allowedTypesToBridgeTypes(['mystery', 'image'])
        ).toEqual(['image']);

        expect(allowedTypesToBridgeTypes(['mystery'])).toBeUndefined();
    });
});
