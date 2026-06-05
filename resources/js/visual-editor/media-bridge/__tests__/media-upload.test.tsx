import { afterEach, describe, expect, it, vi } from 'vitest';
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
    MediaBridgeComponentProps,
    MediaUploader,
} from '../types';

function buildMedia(overrides: Partial<BridgeMedia> = {}): BridgeMedia {
    return {
        id: 101,
        url: 'https://cdn.example.test/image.jpg',
        mime_type: 'image/jpeg',
        alt_text: 'alt',
        caption: 'caption',
        width: 640,
        height: 480,
        is_image: true,
        ...overrides,
    };
}

const noopUploader: MediaUploader = () =>
    Promise.reject(new Error('uploader should not be called'));

function makeBridge(
    onOpen: (props: MediaBridgeComponentProps) => void
): (props: MediaBridgeComponentProps) => JSX.Element {
    return (props) => {
        if (props.open) {
            onOpen(props);
        }
        return (
            <div data-testid="bridge">
                bridge-open={String(props.open)}
            </div>
        );
    };
}

afterEach(() => {
    __resetMediaBridgeForTests();
    vi.restoreAllMocks();
});

describe('MediaUploadBridge without a registered bridge', () => {
    it('alerts and skips render when the user tries to open the picker', async () => {
        ensureMediaBridgeFilter();
        const alertSpy = vi
            .spyOn(window, 'alert')
            .mockImplementation(() => undefined);

        render(
            <MediaUploadBridge
                render={({ open }) => (
                    <button type="button" onClick={open}>
                        Open
                    </button>
                )}
            />
        );

        await userEvent.click(screen.getByRole('button', { name: 'Open' }));

        expect(alertSpy).toHaveBeenCalledTimes(1);
        expect(alertSpy.mock.calls[0]?.[0]).toMatch(
            /registerMediaBridge/i
        );
        expect(screen.queryByTestId('bridge')).not.toBeInTheDocument();
    });
});

describe('MediaUploadBridge with a registered bridge', () => {
    it('opens the bridge and converts a single selection', async () => {
        const captured: MediaBridgeComponentProps[] = [];
        const Bridge = makeBridge((props) => captured.push(props));

        registerMediaBridge({ MediaBridge: Bridge, uploadMedia: noopUploader });

        const onSelect = vi.fn();

        render(
            <MediaUploadBridge
                allowedTypes={['image']}
                onSelect={onSelect}
                render={({ open }) => (
                    <button type="button" onClick={open}>
                        Open
                    </button>
                )}
            />
        );

        await userEvent.click(screen.getByRole('button', { name: 'Open' }));

        expect(screen.getByTestId('bridge')).toBeInTheDocument();
        expect(captured).toHaveLength(1);
        expect(captured[0]?.multiSelect).toBe(false);
        expect(captured[0]?.allowedTypes).toEqual(['image']);

        act(() => {
            captured[0]?.onSelect([buildMedia()], captured[0].context ?? '');
        });

        expect(onSelect).toHaveBeenCalledTimes(1);
        expect(onSelect).toHaveBeenCalledWith(
            expect.objectContaining({
                id: 101,
                alt: 'alt',
                mime: 'image/jpeg',
                media_type: 'image',
            })
        );
    });

    it('passes an array when multiple is true', async () => {
        const captured: MediaBridgeComponentProps[] = [];
        const Bridge = makeBridge((props) => captured.push(props));
        registerMediaBridge({ MediaBridge: Bridge, uploadMedia: noopUploader });

        const onSelect = vi.fn();

        render(
            <MediaUploadBridge
                multiple
                onSelect={onSelect}
                render={({ open }) => (
                    <button type="button" onClick={open}>
                        Open
                    </button>
                )}
            />
        );

        await userEvent.click(screen.getByRole('button', { name: 'Open' }));

        expect(captured[0]?.multiSelect).toBe(true);

        act(() => {
            captured[0]?.onSelect(
                [buildMedia({ id: 1 }), buildMedia({ id: 2 })],
                ''
            );
        });

        expect(onSelect).toHaveBeenCalledTimes(1);
        const received = onSelect.mock.calls[0]?.[0] as Array<{ id: number }>;
        expect(received).toHaveLength(2);
        expect(received[0]?.id).toBe(1);
        expect(received[1]?.id).toBe(2);
    });

    it('treats gallery=true as a multi-select intent', async () => {
        const captured: MediaBridgeComponentProps[] = [];
        const Bridge = makeBridge((props) => captured.push(props));
        registerMediaBridge({ MediaBridge: Bridge, uploadMedia: noopUploader });

        render(
            <MediaUploadBridge
                gallery
                onSelect={() => undefined}
                render={({ open }) => (
                    <button type="button" onClick={open}>
                        Open
                    </button>
                )}
            />
        );

        await userEvent.click(screen.getByRole('button', { name: 'Open' }));

        expect(captured[0]?.multiSelect).toBe(true);
    });

    it('wires the cloned child onClick when no render prop is supplied', async () => {
        const captured: MediaBridgeComponentProps[] = [];
        const Bridge = makeBridge((props) => captured.push(props));
        registerMediaBridge({ MediaBridge: Bridge, uploadMedia: noopUploader });

        const childOnClick = vi.fn();

        render(
            <MediaUploadBridge>
                <button type="button" onClick={childOnClick}>
                    Custom child
                </button>
            </MediaUploadBridge>
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Custom child' })
        );

        expect(childOnClick).toHaveBeenCalledTimes(1);
        expect(captured).toHaveLength(1);
    });

    it('skips opening the bridge when the child calls preventDefault', async () => {
        const captured: MediaBridgeComponentProps[] = [];
        const Bridge = makeBridge((props) => captured.push(props));
        registerMediaBridge({ MediaBridge: Bridge, uploadMedia: noopUploader });

        render(
            <MediaUploadBridge>
                <button
                    type="button"
                    onClick={(event) => {
                        event.preventDefault();
                    }}
                >
                    Blocks default
                </button>
            </MediaUploadBridge>
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Blocks default' })
        );

        expect(captured).toHaveLength(0);
        expect(screen.queryByTestId('bridge')).not.toBeInTheDocument();
    });

    it('resolves the registered bridge at click time, not render time', async () => {
        const alertSpy = vi
            .spyOn(window, 'alert')
            .mockImplementation(() => undefined);

        render(
            <MediaUploadBridge
                render={({ open }) => (
                    <button type="button" onClick={open}>
                        Open
                    </button>
                )}
            />
        );

        // Component first renders with no bridge, then one is registered.
        const captured: MediaBridgeComponentProps[] = [];
        const Bridge = makeBridge((props) => captured.push(props));
        registerMediaBridge({ MediaBridge: Bridge, uploadMedia: noopUploader });

        await userEvent.click(screen.getByRole('button', { name: 'Open' }));

        expect(alertSpy).not.toHaveBeenCalled();
        expect(captured).toHaveLength(1);
    });
});
