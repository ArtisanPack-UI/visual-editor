import { afterEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { registerArtisanpackMediaBridge } from '../artisanpack-default';
import { MediaUploadBridge } from '../media-upload';
import { __resetMediaBridgeForTests, getMediaUploader } from '../state';
import type {
    BridgeMedia,
    MediaBridgeComponent,
    MediaBridgeComponentProps,
    MediaUploader,
} from '../types';

function makeModal(
    captured: MediaBridgeComponentProps[]
): MediaBridgeComponent {
    return (props: MediaBridgeComponentProps) => {
        if (props.open) {
            captured.push(props);
            return <div data-testid="media-modal">open</div>;
        }
        return null;
    };
}

const uploader: MediaUploader = vi.fn(() =>
    Promise.resolve({
        data: {
            id: 1,
            url: 'x',
            mime_type: 'image/jpeg',
        } as BridgeMedia,
    })
);

afterEach(() => {
    __resetMediaBridgeForTests();
    vi.restoreAllMocks();
});

describe('registerArtisanpackMediaBridge', () => {
    it('wires MediaModal into the bridge slot-fill component', async () => {
        const captured: MediaBridgeComponentProps[] = [];
        registerArtisanpackMediaBridge({
            MediaModal: makeModal(captured),
            uploadMedia: uploader,
        });

        const onSelect = vi.fn();

        render(
            <MediaUploadBridge
                allowedTypes={['image']}
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

        expect(screen.getByTestId('media-modal')).toBeInTheDocument();
        expect(captured).toHaveLength(1);
        expect(captured[0]?.allowedTypes).toEqual(['image']);

        act(() => {
            captured[0]?.onSelect(
                [
                    {
                        id: 7,
                        url: 'https://cdn.example.test/a.jpg',
                        mime_type: 'image/jpeg',
                        alt_text: 'a',
                        caption: 'b',
                        is_image: true,
                    },
                ],
                captured[0].context ?? ''
            );
        });

        expect(onSelect).toHaveBeenCalledWith(
            expect.objectContaining({
                id: 7,
                alt: 'a',
                caption: 'b',
                mime: 'image/jpeg',
                media_type: 'image',
            })
        );
    });

    it('registers uploadMedia as the active uploader', () => {
        registerArtisanpackMediaBridge({
            MediaModal: makeModal([]),
            uploadMedia: uploader,
        });

        expect(getMediaUploader()).toBe(uploader);
    });
});
