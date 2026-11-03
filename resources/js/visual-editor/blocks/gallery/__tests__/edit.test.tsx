/**
 * Tests for the `artisanpack/gallery` edit component.
 */

import { afterEach, describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
    sprintf: (fmt: string, ...args: unknown[]) =>
        fmt.replace(/%s/g, () => String(args.shift())),
}));

vi.mock('@wordpress/icons', () => ({
    link: 'link-icon',
    customLink: 'custom-link-icon',
    image: 'image-icon',
    linkOff: 'link-off-icon',
    fullscreen: 'fullscreen-icon',
    gallery: 'gallery-icon',
}));

vi.mock('@wordpress/blob', () => ({
    createBlobURL: (file: File) => `blob:mock/${file.name ?? 'file'}`,
}));

vi.mock('@wordpress/blocks', () => ({
    createBlock: (
        name: string,
        attributes?: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: innerBlocks ?? [],
        clientId: `client-${Math.random()}`,
    }),
}));

// Mutable so a test can put images in the gallery and watch what the
// new-image effect writes to them. The defaults are an empty gallery.
const galleryStore = vi.hoisted(() => ({
    innerBlocks: [] as unknown[],
    media: [] as unknown[],
    lightbox: { allowEditing: true, enabled: false },
    updateBlockAttributes: vi.fn(),
}));

vi.mock('../../image/use-lightbox-setting', () => ({
    useLightboxSetting: () => galleryStore.lightbox,
}));

vi.mock('@wordpress/data', () => {
    const fakeStore = {
        getBlockName: () => '',
        getMultiSelectedBlockClientIds: () => [],
        getSettings: () => ({ imageSizes: [] }),
        getBlock: () => ({ innerBlocks: galleryStore.innerBlocks }),
        wasBlockJustInserted: () => false,
        getEntityRecords: () => galleryStore.media,
    };
    const select = () => fakeStore;
    return {
        useDispatch: () => ({
            __unstableMarkNextChangeAsNotPersistent: vi.fn(),
            replaceInnerBlocks: vi.fn(),
            updateBlockAttributes: galleryStore.updateBlockAttributes,
            selectBlock: vi.fn(),
            createSuccessNotice: vi.fn(),
            createErrorNotice: vi.fn(),
        }),
        useSelect: (cb: (s: typeof select) => unknown) => cb(select),
    };
});

vi.mock('@wordpress/notices', () => ({
    store: 'notices-store',
}));

vi.mock('@wordpress/core-data', () => ({
    store: 'core-data-store',
}));

vi.mock('@wordpress/compose', () => ({
    useViewportMatch: () => false,
}));

vi.mock('@wordpress/element', async () => {
    const React = await import('react');
    return {
        useEffect: React.useEffect,
        useMemo: React.useMemo,
        useState: React.useState,
    };
});

vi.mock('@wordpress/components', () => {
    const Pass = ({ children }: { children?: React.ReactNode }) => (
        <>{children}</>
    );
    return {
        SelectControl: () => null,
        ToggleControl: () => null,
        RangeControl: () => null,
        MenuGroup: Pass,
        MenuItem: Pass,
        __experimentalToolsPanel: Pass,
        __experimentalToolsPanelItem: Pass,
        __experimentalToggleGroupControl: Pass,
        __experimentalToggleGroupControlOption: () => null,
        ToolbarDropdownMenu: ({
            children,
        }: {
            children:
                | React.ReactNode
                | ((args: { onClose: () => void }) => React.ReactNode);
        }) => (
            <div data-testid="toolbar-dropdown">
                {typeof children === 'function'
                    ? children({ onClose: () => {} })
                    : children}
            </div>
        ),
    };
});

vi.mock('@wordpress/block-editor', () => ({
    store: 'block-editor-store',
    BlockControls: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="block-controls">{children}</div>
    ),
    BlockIcon: () => null,
    InspectorControls: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    MediaPlaceholder: () => <div data-testid="placeholder" />,
    MediaReplaceFlow: () => <div data-testid="replace-flow" />,
    RichText: Object.assign(
        ({ value }: { value?: string }) => (
            <span dangerouslySetInnerHTML={{ __html: value ?? '' }} />
        ),
        {
            isEmpty: (value?: string) => !value || value === '',
        }
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    useInnerBlocksProps: (props?: Record<string, unknown>) => ({
        ...props,
        children: null,
    }),
    useSettings: () => [undefined, undefined, undefined, undefined],
    __experimentalGetGapCSSValue: (val: string) => val,
    useStyleOverride: () => undefined,
}));

(globalThis as { React?: unknown }).React = require('react');

import GalleryEdit, { defaultLinkDestination, linkOptionsList } from '../edit';
import { followsThemeLightbox, getHrefAndDestination } from '../utils';

describe('GalleryEdit', () => {
    it('renders the MediaPlaceholder when no inner image blocks exist', () => {
        const setAttributes = vi.fn();
        const { getByTestId } = render(
            <GalleryEdit
                attributes={{}}
                setAttributes={setAttributes}
                clientId="test-gallery-id"
                isSelected
            />
        );
        expect(getByTestId('placeholder')).toBeTruthy();
    });
});

describe('Gallery lightbox link option (#1046)', () => {
    const values = (allowLightbox: boolean): string[] =>
        linkOptionsList(allowLightbox).map((option) => option.value);

    it('offers the lightbox unless the theme turns off editing', () => {
        expect(values(true)).toContain('lightbox');
        expect(values(false)).not.toContain('lightbox');
    });

    it('turns the lightbox on for each image and clears its link', () => {
        expect(getHrefAndDestination({ url: '/a.jpg' }, 'lightbox', false, { lightbox: {} })).toEqual({
            href: undefined,
            lightbox: { enabled: true },
            linkDestination: 'none',
        });
    });

    it('leaves images on the theme default when that is on', () => {
        expect(
            getHrefAndDestination({ url: '/a.jpg' }, 'lightbox', false, {}, { enabled: true }).lightbox
        ).toBeUndefined();
    });

    it('starts a new gallery on the lightbox when the theme default is on', () => {
        expect(defaultLinkDestination(true)).toBe('lightbox');
        expect(defaultLinkDestination(false)).toBe('none');
    });

    it('turns a theme default off when the author picks None', () => {
        expect(
            getHrefAndDestination({ url: '/a.jpg' }, 'none', false, {}, { enabled: true }).lightbox
        ).toEqual({ enabled: false });
    });

    it('leaves None alone with no theme default', () => {
        expect(getHrefAndDestination({ url: '/a.jpg' }, 'none', false, {}).lightbox).toBeUndefined();
    });

    it('turns the lightbox off when a theme default is on and images link to media', () => {
        expect(
            getHrefAndDestination({ url: '/a.jpg' }, 'media', false, {}, { enabled: true }).lightbox
        ).toEqual({ enabled: false });
    });
});


describe('Gallery set to None with a theme lightbox default (#1071)', () => {
    const themeOn = { enabled: true };
    const unset = { attributes: {} };

    it('lets a new image follow siblings that leave the lightbox to the theme', () => {
        expect(followsThemeLightbox('none', [unset, { attributes: { lightbox: {} } }], themeOn)).toBe(true);
    });

    it('turns a new image off once any image has its own setting', () => {
        expect(followsThemeLightbox('none', [unset, { attributes: { lightbox: { enabled: false } } }], themeOn)).toBe(false);
        expect(followsThemeLightbox('none', [unset, { attributes: { lightbox: { enabled: true } } }], themeOn)).toBe(false);
    });

    it('only applies to a Gallery set to None', () => {
        expect(followsThemeLightbox('media', [unset], themeOn)).toBe(false);
        expect(followsThemeLightbox('lightbox', [unset], themeOn)).toBe(false);
    });

    it('does nothing without a theme default', () => {
        expect(followsThemeLightbox('none', [unset])).toBe(false);
        expect(followsThemeLightbox('none', [unset], { enabled: false })).toBe(false);
    });
});

describe('Gallery new images under a theme lightbox default (#1071)', () => {
    const image = (id: number, attributes: Record<string, unknown> = {}) => ({
        clientId: `image-${id}`,
        attributes: { id, url: `/${id}.jpg`, ...attributes },
    });

    function renderGallery(innerBlocks: unknown[]): Record<string, Record<string, unknown>> {
        galleryStore.innerBlocks = innerBlocks;
        galleryStore.media = innerBlocks.map((block) => ({
            id: (block as { attributes: { id: number } }).attributes.id,
            source_url: '/full.jpg',
        }));
        galleryStore.lightbox = { allowEditing: true, enabled: true };
        galleryStore.updateBlockAttributes.mockClear();

        render(
            <GalleryEdit
                attributes={{ linkTo: 'none' }}
                setAttributes={vi.fn()}
                clientId="test-gallery-id"
                isSelected={false}
            />
        );

        return Object.fromEntries(
            galleryStore.updateBlockAttributes.mock.calls.map(([clientId, attributes]) => [clientId, attributes])
        );
    }

    afterEach(() => {
        galleryStore.innerBlocks = [];
        galleryStore.media = [];
        galleryStore.lightbox = { allowEditing: true, enabled: false };
    });

    it('leaves the lightbox unset when no image has its own setting', () => {
        const writes = renderGallery([image(1), image(2)]);

        expect(writes['image-1']).toHaveProperty('lightbox', undefined);
        expect(writes['image-2']).toHaveProperty('lightbox', undefined);
        expect(writes['image-2']).toHaveProperty('linkDestination', 'none');
    });

    it('turns the lightbox off once an author picked None', () => {
        const writes = renderGallery([image(1, { lightbox: { enabled: false } }), image(2)]);

        expect(writes['image-2']).toHaveProperty('lightbox', { enabled: false });
    });

    it('still turns the lightbox off for an image with its own link', () => {
        const writes = renderGallery([image(1), image(2, { linkDestination: 'media' })]);

        expect(writes['image-1']).toHaveProperty('lightbox', undefined);
        expect(writes['image-2']).toHaveProperty('lightbox', { enabled: false });
        expect(writes['image-2']).toHaveProperty('href', '/full.jpg');
    });
});
