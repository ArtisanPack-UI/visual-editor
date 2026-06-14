/**
 * Tests for the `artisanpack/cover` edit component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@testing-library/react';

// #578 — capture the handlers passed to the placeholder's ColorPalette
// and MediaPlaceholder so the regression tests below can invoke them
// directly and assert that the cover-block edit component calls
// `setAttributes` synchronously (without awaiting `getMediaColor`).
const capturedProps: {
    placeholderColorPaletteOnChange?: (color: string | undefined) => void;
    placeholderMediaOnSelect?: (media: unknown) => void;
} = {};

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/icons', () => ({
    cover: 'cover-icon',
    link: 'link-icon',
}));

vi.mock('@wordpress/data', () => ({
    useDispatch: () => ({
        createErrorNotice: vi.fn(),
        __unstableMarkNextChangeAsNotPersistent: vi.fn(),
    }),
    useSelect: (selector: unknown) => {
        const store = {
            getEntityRecord: () => null,
            getEmbedPreview: () => undefined,
            isRequestingEmbedPreview: () => false,
            getSettings: () => ({ imageSizes: [] }),
            getBlock: () => ({ innerBlocks: [] }),
        };
        if (typeof selector === 'function') {
            return (selector as (s: unknown) => unknown)(() => store);
        }
        // Called as useSelect(store, deps) — return the store directly.
        return store;
    },
}));

vi.mock('@wordpress/core-data', () => ({
    store: 'core-store',
    useEntityProp: () => [undefined, vi.fn()],
}));

vi.mock('@wordpress/notices', () => ({
    store: 'notices-store',
}));

vi.mock('@wordpress/blob', () => ({
    isBlobURL: () => false,
    createBlobURL: (file: File) => `blob:mock/${file.name}`,
}));

vi.mock('@wordpress/blocks', () => ({
    createBlock: (
        name: string,
        attributes?: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => ({ name, attributes: attributes ?? {}, innerBlocks: innerBlocks ?? [] }),
    getBlockVariations: () => [],
}));

vi.mock('@wordpress/element', async (importOriginal) => {
    const actual = (await importOriginal()) as Record<string, unknown>;
    return actual;
});

vi.mock('@wordpress/hooks', () => ({
    applyFilters: (_hook: string, value: unknown) => value,
}));

vi.mock('@wordpress/components', () => ({
    Placeholder: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="placeholder">{children}</div>
    ),
    Spinner: () => <div data-testid="spinner" />,
    ColorPalette: () => null,
    Button: ({ children }: { children?: React.ReactNode }) => (
        <button>{children}</button>
    ),
    MenuItem: ({ children }: { children?: React.ReactNode }) => (
        <div>{children}</div>
    ),
    Notice: ({ children }: { children?: React.ReactNode }) => (
        <div>{children}</div>
    ),
    TextControl: () => null,
    TextareaControl: () => null,
    SelectControl: () => null,
    RangeControl: () => null,
    ToggleControl: () => null,
    FocalPointPicker: () => null,
    ExternalLink: ({ children }: { children?: React.ReactNode }) => (
        <a>{children}</a>
    ),
    ResizableBox: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="resizable">{children}</div>
    ),
    __experimentalConfirmDialog: ({
        children,
    }: {
        children?: React.ReactNode;
    }) => <div>{children}</div>,
    __experimentalVStack: ({ children }: { children?: React.ReactNode }) => (
        <div>{children}</div>
    ),
    __experimentalUseCustomUnits: () => [],
    __experimentalToolsPanel: ({
        children,
    }: {
        children?: React.ReactNode;
    }) => <div>{children}</div>,
    __experimentalToolsPanelItem: ({
        children,
    }: {
        children?: React.ReactNode;
    }) => <div>{children}</div>,
    __experimentalUnitControl: () => null,
    __experimentalParseQuantityAndUnitFromRawValue: () => [undefined, 'px'],
}));

vi.mock('@wordpress/compose', () => ({
    useInstanceId: () => 'instance-1',
}));

vi.mock('@wordpress/block-editor', () => {
    const useBlockProps = Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    );
    const useInnerBlocksProps = Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    );
    return {
        BlockControls: ({ children }: { children?: React.ReactNode }) => (
            <div data-testid="block-controls">{children}</div>
        ),
        BlockIcon: () => null,
        InspectorControls: ({
            children,
        }: {
            children?: React.ReactNode;
        }) => <div data-testid="inspector">{children}</div>,
        MediaPlaceholder: ({
            children,
            onSelect,
        }: {
            children?: React.ReactNode;
            onSelect?: (media: unknown) => void;
        }) => {
            capturedProps.placeholderMediaOnSelect = onSelect;
            return <div data-testid="placeholder">{children}</div>;
        },
        MediaReplaceFlow: ({
            children,
        }: {
            children?: React.ReactNode;
        }) => <div data-testid="replace-flow">{children}</div>,
        MediaUpload: ({ render }: { render: (args: { open: () => void }) => React.ReactNode }) =>
            <>{render({ open: () => {} })}</>,
        MediaUploadCheck: ({ children }: { children?: React.ReactNode }) => (
            <>{children}</>
        ),
        ColorPalette: ({
            onChange,
        }: {
            onChange?: (color: string | undefined) => void;
        }) => {
            capturedProps.placeholderColorPaletteOnChange = onChange;
            return null;
        },
        RichText: ({ value }: { value?: string }) => (
            <span dangerouslySetInnerHTML={{ __html: value ?? '' }} />
        ),
        useBlockProps,
        useInnerBlocksProps,
        useSettings: () => [[]],
        useBlockEditingMode: () => 'default',
        getColorClassName: (prefix: string, name?: string) =>
            name ? `has-${name}-${prefix}` : undefined,
        store: 'block-editor-store',
        withColors:
            () =>
            (Component: React.ComponentType<unknown>) =>
            (props: Record<string, unknown>) => (
                <Component
                    overlayColor={{ color: undefined, class: undefined }}
                    setOverlayColor={() => {}}
                    {...props}
                />
            ),
        __experimentalUseGradient: () => ({
            gradientClass: undefined,
            gradientValue: undefined,
            setGradient: () => {},
        }),
        __experimentalGetGradientClass: (name?: string) =>
            name ? `has-${name}-gradient-background` : undefined,
        __experimentalColorGradientSettingsDropdown: ({
            children,
        }: {
            children?: React.ReactNode;
        }) => <div>{children}</div>,
        __experimentalUseMultipleOriginColorsAndGradients: () => ({
            hasColorsOrGradients: false,
        }),
        __experimentalBlockAlignmentMatrixControl: () => null,
        __experimentalBlockFullHeightAligmentControl: () => null,
    };
});

vi.mock('colord', () => ({
    colord: (value: string) => ({
        toRgb: () => ({ r: 0, g: 0, b: 0, a: 1 }),
        alpha: () => ({ toRgb: () => ({ r: 0, g: 0, b: 0, a: 0.5 }) }),
        isDark: () => true,
    }),
    extend: () => {},
}));

vi.mock('colord/plugins/names', () => ({ default: {} }));

vi.mock('fast-average-color', () => ({
    FastAverageColor: class {
        async getColorAsync(): Promise<{ hex: string }> {
            return { hex: '#000000' };
        }
    },
}));

vi.mock('memize', () => ({
    default: (fn: unknown) => fn,
}));

(globalThis as { React?: unknown }).React = require('react');

import CoverEdit from '../edit';

describe('CoverEdit', () => {
    beforeEach(() => {
        capturedProps.placeholderColorPaletteOnChange = undefined;
        capturedProps.placeholderMediaOnSelect = undefined;
    });

    it('renders the placeholder TagName when there is no background and no inner blocks', () => {
        const setAttributes = vi.fn();
        const { container } = render(
            <CoverEdit
                attributes={{ tagName: 'div', dimRatio: 100 }}
                clientId="abc"
                isSelected
                overlayColor={{ color: undefined, class: undefined }}
                setAttributes={setAttributes}
                setOverlayColor={() => {}}
                toggleSelection={() => {}}
            />
        );
        expect(container.querySelector('.is-placeholder')).not.toBeNull();
    });

    it('renders an image background when backgroundType=image and url is set', () => {
        const setAttributes = vi.fn();
        const { container } = render(
            <CoverEdit
                attributes={{
                    tagName: 'div',
                    backgroundType: 'image',
                    url: 'https://example.com/photo.jpg',
                    dimRatio: 50,
                }}
                clientId="abc"
                isSelected
                overlayColor={{ color: '#fff', class: undefined }}
                setAttributes={setAttributes}
                setOverlayColor={() => {}}
                toggleSelection={() => {}}
            />
        );
        const img = container.querySelector('img.wp-block-cover__image-background');
        expect(img).not.toBeNull();
        expect(img?.getAttribute('src')).toBe('https://example.com/photo.jpg');
    });

    // #578 — regression: the picker click must apply the overlay color
    // synchronously. Awaiting `getMediaColor` here piles RAF callbacks
    // from the upstream contrast checker faster than React can flush,
    // hangs the editor, and eventually crashes the block via
    // `BlockCrashBoundary`. The handler now commits the picked color
    // and `isUserOverlayColor` flag in the same tick, then refines
    // `isDark` in a background task.
    it('onSetOverlayColor: commits the overlay color and isUserOverlayColor synchronously', () => {
        const setAttributes = vi.fn();
        const setOverlayColor = vi.fn();

        render(
            <CoverEdit
                attributes={{ tagName: 'div', dimRatio: 100 }}
                clientId="abc"
                isSelected
                overlayColor={{ color: undefined, class: undefined }}
                setAttributes={setAttributes}
                setOverlayColor={setOverlayColor}
                toggleSelection={() => {}}
            />
        );

        expect(capturedProps.placeholderColorPaletteOnChange).toBeDefined();

        capturedProps.placeholderColorPaletteOnChange?.('#ff0000');

        // Both calls must land before the test yields to the
        // microtask / RAF queue — otherwise the editor render loop
        // can't settle and the RAF storm reproduces.
        expect(setOverlayColor).toHaveBeenCalledWith('#ff0000');
        expect(setAttributes).toHaveBeenCalledWith({
            isUserOverlayColor: true,
        });
    });

    // #578 — race-guard: if the user picks a second overlay color
    // before the first pick's background `getMediaColor` resolves, the
    // first task must NOT overwrite the second pick's `isDark`. The
    // handler bumps a version ref and the background task bails out
    // when the captured version no longer matches.
    it('onSetOverlayColor: a superseded background task does not write isDark', async () => {
        const setAttributes = vi.fn();
        const setOverlayColor = vi.fn();

        render(
            <CoverEdit
                attributes={{ tagName: 'div', dimRatio: 100 }}
                clientId="abc"
                isSelected
                overlayColor={{ color: undefined, class: undefined }}
                setAttributes={setAttributes}
                setOverlayColor={setOverlayColor}
                toggleSelection={() => {}}
            />
        );

        // Two picks before any microtask flush — second supersedes first.
        capturedProps.placeholderColorPaletteOnChange?.('#ff0000');
        capturedProps.placeholderColorPaletteOnChange?.('#00ff00');

        // Flush microtasks so both background tasks resolve.
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();

        // Both sync calls happened, in order.
        expect(setOverlayColor).toHaveBeenNthCalledWith(1, '#ff0000');
        expect(setOverlayColor).toHaveBeenNthCalledWith(2, '#00ff00');

        // Only the second background task should have written isDark —
        // the first one's captured version was already stale.
        const isDarkCalls = setAttributes.mock.calls.filter(
            (args) =>
                typeof args[0] === 'object' &&
                args[0] !== null &&
                'isDark' in (args[0] as Record<string, unknown>)
        );
        expect(isDarkCalls.length).toBeLessThanOrEqual(1);
    });

    // #578 — regression: the media-select click must apply the
    // media-driven attributes (url, backgroundType, id, etc.)
    // synchronously. Anything else lets the modal close while the
    // block remains in placeholder state — the editor then freezes
    // during the await and crashes via `BlockCrashBoundary`.
    it('onSelectMedia: commits the media attributes synchronously', () => {
        const setAttributes = vi.fn();
        const setOverlayColor = vi.fn();

        render(
            <CoverEdit
                attributes={{ tagName: 'div', dimRatio: 100 }}
                clientId="abc"
                isSelected
                overlayColor={{ color: undefined, class: undefined }}
                setAttributes={setAttributes}
                setOverlayColor={setOverlayColor}
                toggleSelection={() => {}}
            />
        );

        expect(capturedProps.placeholderMediaOnSelect).toBeDefined();

        capturedProps.placeholderMediaOnSelect?.({
            id: 42,
            url: 'https://example.com/photo.jpg',
            type: 'image',
            media_type: 'image',
            mime: 'image/jpeg',
        });

        expect(setAttributes).toHaveBeenCalled();
        const callArgs = setAttributes.mock.calls[0]?.[0] as Record<
            string,
            unknown
        >;
        expect(callArgs.url).toBe('https://example.com/photo.jpg');
        expect(callArgs.id).toBe(42);
        expect(callArgs.focalPoint).toBeUndefined();
        expect(callArgs.useFeaturedImage).toBeUndefined();
    });
});
