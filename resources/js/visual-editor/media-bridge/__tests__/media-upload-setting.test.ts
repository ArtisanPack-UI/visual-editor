import { afterEach, describe, expect, it, vi } from 'vitest';

import { mediaUploadSetting } from '../media-upload-setting';
import {
    __resetMediaBridgeForTests,
    registerMediaBridge,
} from '../state';
import type { BridgeMedia, MediaUploader } from '../types';

function buildMedia(overrides: Partial<BridgeMedia> = {}): BridgeMedia {
    return {
        id: 7,
        url: 'https://cdn.example.test/u.jpg',
        mime_type: 'image/png',
        alt_text: null,
        caption: null,
        width: 200,
        height: 200,
        is_image: true,
        ...overrides,
    };
}

function textFile(
    name: string,
    body: string,
    type = 'text/plain'
): File {
    return new File([body], name, { type });
}

afterEach(() => {
    __resetMediaBridgeForTests();
    vi.restoreAllMocks();
});

describe('mediaUploadSetting', () => {
    it('reports an error when no uploader is registered', () => {
        const onError = vi.fn();

        mediaUploadSetting({
            filesList: [textFile('a.txt', 'body')],
            onError,
        });

        expect(onError).toHaveBeenCalledTimes(1);
        expect(onError.mock.calls[0]?.[0]).toMatch(/bridge/i);
    });

    it('invokes the uploader per file and forwards Gutenberg-shape results', async () => {
        const uploader: MediaUploader = vi
            .fn()
            .mockImplementation((file: File) =>
                Promise.resolve(
                    buildMedia({
                        id: file.name.length,
                        url: `https://cdn.example.test/${file.name}`,
                    })
                )
            );

        registerMediaBridge({
            MediaBridge: () => null,
            uploadMedia: uploader,
        });

        const onFileChange = vi.fn();

        mediaUploadSetting({
            filesList: [textFile('one.txt', '1'), textFile('two.txt', '2')],
            onFileChange,
        });

        await vi.waitFor(() => {
            expect(onFileChange).toHaveBeenCalledTimes(1);
        });

        expect(uploader as unknown as ReturnType<typeof vi.fn>).toHaveBeenCalledTimes(
            2
        );
        const received = onFileChange.mock.calls[0]?.[0] as Array<{
            id: number;
            url: string;
        }>;
        expect(received).toHaveLength(2);
        expect(received[0]?.id).toBe(7);
        expect(received[0]?.url).toContain('one.txt');
    });

    it('unwraps `{ data }` envelopes returned by uploadMedia', async () => {
        const uploader: MediaUploader = () =>
            Promise.resolve({ data: buildMedia({ id: 999 }) });

        registerMediaBridge({
            MediaBridge: () => null,
            uploadMedia: uploader,
        });

        const onFileChange = vi.fn();

        mediaUploadSetting({
            filesList: [textFile('wrapped.png', '0', 'image/png')],
            onFileChange,
        });

        await vi.waitFor(() => {
            expect(onFileChange).toHaveBeenCalledTimes(1);
        });

        const received = onFileChange.mock.calls[0]?.[0] as Array<{
            id: number;
        }>;
        expect(received[0]?.id).toBe(999);
    });

    it('rejects files that exceed maxUploadFileSize without calling the uploader', () => {
        const uploader = vi.fn();
        registerMediaBridge({
            MediaBridge: () => null,
            uploadMedia: uploader as unknown as MediaUploader,
        });

        const onError = vi.fn();

        const big = new File(['x'.repeat(2048)], 'big.txt', {
            type: 'text/plain',
        });

        mediaUploadSetting({
            filesList: [big],
            maxUploadFileSize: 1024,
            onError,
        });

        expect(uploader).not.toHaveBeenCalled();
        expect(onError).toHaveBeenCalledTimes(1);
    });

    it('surfaces uploader rejections via onError', async () => {
        const uploader: MediaUploader = () =>
            Promise.reject(new Error('boom'));

        registerMediaBridge({
            MediaBridge: () => null,
            uploadMedia: uploader,
        });

        const onError = vi.fn();

        mediaUploadSetting({
            filesList: [textFile('a.txt', 'a')],
            onError,
        });

        await vi.waitFor(() => {
            expect(onError).toHaveBeenCalledTimes(1);
        });

        expect(onError.mock.calls[0]?.[0]).toBe('boom');
    });

    it('short-circuits on an empty filesList', () => {
        const uploader = vi.fn();
        registerMediaBridge({
            MediaBridge: () => null,
            uploadMedia: uploader as unknown as MediaUploader,
        });

        const onError = vi.fn();
        const onFileChange = vi.fn();

        mediaUploadSetting({
            filesList: [],
            onError,
            onFileChange,
        });

        expect(uploader).not.toHaveBeenCalled();
        expect(onError).not.toHaveBeenCalled();
        expect(onFileChange).not.toHaveBeenCalled();
    });
});
