/**
 * Per-block integration coverage for the MediaUpload slot-fill.
 *
 * The seven core media blocks each call Gutenberg's `<MediaUpload>` with
 * a different combination of `allowedTypes`, `multiple`, and `gallery`.
 * Rather than load the full `@wordpress/block-library` graph inside
 * jsdom — which pulls in `block-compare` and its extension-less
 * `diff/lib/*` imports, broken under Node's strict ESM resolver — each
 * test case replays the exact props a given block sends and verifies:
 *
 *   1. the bridge opens with the correct `multiSelect` + `allowedTypes`,
 *   2. selection is translated back into Gutenberg's attachment shape,
 *   3. the block's `onSelect` callback receives a single record or an
 *      array according to the block's contract.
 *
 * End-to-end verification of each block inside a real editor is covered
 * by the sandbox at `/sandbox/` (see `sandbox/sandbox-editor.tsx` and
 * docs/gutenberg-adoption.md).
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaUploadBridge } from '../media-upload';
import {
    __resetMediaBridgeForTests,
    ensureMediaBridgeFilter,
    registerMediaBridge,
} from '../state';
import type {
    BridgeMedia,
    GutenbergMedia,
    GutenbergMediaUploadProps,
    MediaBridgeComponent,
    MediaBridgeComponentProps,
    MediaUploader,
} from '../types';

const noopUploader: MediaUploader = () =>
    Promise.reject(new Error('uploader should not be called'));

/**
 * Reference media returned by the host bridge for each supported type.
 * The shape mirrors what `artisanpack-ui/media-library` emits so the
 * adapter exercises realistic input.
 */
const MEDIA_FIXTURES: Record<
    'image' | 'video' | 'audio' | 'file',
    BridgeMedia
> = {
    image: {
        id: 101,
        url: 'https://cdn.example.test/pic.jpg',
        mime_type: 'image/jpeg',
        alt_text: 'alt',
        caption: 'caption',
        width: 1024,
        height: 768,
        is_image: true,
        file_name: 'pic.jpg',
    },
    video: {
        id: 202,
        url: 'https://cdn.example.test/clip.mp4',
        mime_type: 'video/mp4',
        alt_text: null,
        caption: null,
        is_video: true,
        file_name: 'clip.mp4',
    },
    audio: {
        id: 303,
        url: 'https://cdn.example.test/track.mp3',
        mime_type: 'audio/mpeg',
        alt_text: null,
        caption: null,
        is_audio: true,
        file_name: 'track.mp3',
    },
    file: {
        id: 404,
        url: 'https://cdn.example.test/doc.pdf',
        mime_type: 'application/pdf',
        alt_text: null,
        caption: null,
        is_document: true,
        file_name: 'doc.pdf',
    },
};

/**
 * The exact `<MediaUpload>` invocation each core block emits. These
 * snapshots come from `@wordpress/block-library`'s block source
 * (`image/edit.js`, `gallery/edit.js`, etc.).
 */
interface BlockScenario {
    blockName: string;
    uploadProps: Pick<
        GutenbergMediaUploadProps,
        'allowedTypes' | 'multiple' | 'gallery'
    >;
    /** Media the bridge returns after the user selects. */
    selection: BridgeMedia[];
    expectedMultiSelect: boolean;
    expectedAllowedTypes: string[] | undefined;
    /** True when `onSelect` should receive an array, false for a single record. */
    expectsArray: boolean;
}

const BLOCK_SCENARIOS: BlockScenario[] = [
    {
        blockName: 'core/image',
        uploadProps: { allowedTypes: ['image'] },
        selection: [MEDIA_FIXTURES.image],
        expectedMultiSelect: false,
        expectedAllowedTypes: ['image'],
        expectsArray: false,
    },
    {
        blockName: 'core/gallery',
        uploadProps: { allowedTypes: ['image'], multiple: true, gallery: true },
        selection: [
            MEDIA_FIXTURES.image,
            { ...MEDIA_FIXTURES.image, id: 102 },
        ],
        expectedMultiSelect: true,
        expectedAllowedTypes: ['image'],
        expectsArray: true,
    },
    {
        blockName: 'core/video',
        uploadProps: { allowedTypes: ['video'] },
        selection: [MEDIA_FIXTURES.video],
        expectedMultiSelect: false,
        expectedAllowedTypes: ['video'],
        expectsArray: false,
    },
    {
        blockName: 'core/audio',
        uploadProps: { allowedTypes: ['audio'] },
        selection: [MEDIA_FIXTURES.audio],
        expectedMultiSelect: false,
        expectedAllowedTypes: ['audio'],
        expectsArray: false,
    },
    {
        blockName: 'core/file',
        uploadProps: {},
        selection: [MEDIA_FIXTURES.file],
        expectedMultiSelect: false,
        expectedAllowedTypes: undefined,
        expectsArray: false,
    },
    {
        blockName: 'core/cover',
        uploadProps: { allowedTypes: ['image', 'video'] },
        selection: [MEDIA_FIXTURES.image],
        expectedMultiSelect: false,
        expectedAllowedTypes: ['image', 'video'],
        expectsArray: false,
    },
    {
        blockName: 'core/media-text',
        uploadProps: { allowedTypes: ['image', 'video'] },
        selection: [MEDIA_FIXTURES.video],
        expectedMultiSelect: false,
        expectedAllowedTypes: ['image', 'video'],
        expectsArray: false,
    },
];

function makeBridge(): {
    Bridge: MediaBridgeComponent;
    lastProps: () => MediaBridgeComponentProps | null;
} {
    let last: MediaBridgeComponentProps | null = null;
    const Bridge: MediaBridgeComponent = (props) => {
        if (props.open) {
            last = props;
        }
        return props.open ? <div data-testid="bridge">open</div> : null;
    };
    return { Bridge, lastProps: () => last };
}

beforeEach(() => {
    ensureMediaBridgeFilter();
});

afterEach(() => {
    __resetMediaBridgeForTests();
    vi.restoreAllMocks();
});

describe('core media blocks drive MediaUpload through the bridge', () => {
    it.each(BLOCK_SCENARIOS)(
        '$blockName opens the bridge with the right constraints',
        async (scenario) => {
            const { Bridge, lastProps } = makeBridge();
            registerMediaBridge({
                MediaBridge: Bridge,
                uploadMedia: noopUploader,
            });

            const onSelect = vi.fn();

            render(
                <MediaUploadBridge
                    {...scenario.uploadProps}
                    onSelect={onSelect}
                    render={({ open }) => (
                        <button type="button" onClick={open}>
                            Media Library
                        </button>
                    )}
                />
            );

            await userEvent.click(
                screen.getByRole('button', { name: 'Media Library' })
            );

            expect(screen.getByTestId('bridge')).toBeInTheDocument();

            const props = lastProps();
            expect(props).not.toBeNull();
            expect(props?.multiSelect).toBe(scenario.expectedMultiSelect);
            expect(props?.allowedTypes).toEqual(scenario.expectedAllowedTypes);

            act(() => {
                props?.onSelect(scenario.selection, props.context ?? '');
            });

            expect(onSelect).toHaveBeenCalledTimes(1);
            const received = onSelect.mock.calls[0]?.[0];

            if (scenario.expectsArray) {
                expect(Array.isArray(received)).toBe(true);
                const list = received as GutenbergMedia[];
                expect(list).toHaveLength(scenario.selection.length);
                for (const item of list) {
                    expect(item).toMatchObject({
                        id: expect.any(Number),
                        url: expect.any(String),
                        mime: expect.any(String),
                    });
                }
            } else {
                expect(Array.isArray(received)).toBe(false);
                const single = received as GutenbergMedia;
                expect(single).toMatchObject({
                    id: scenario.selection[0]?.id,
                    url: scenario.selection[0]?.url,
                    mime: scenario.selection[0]?.mime_type,
                });
            }
        }
    );
});
