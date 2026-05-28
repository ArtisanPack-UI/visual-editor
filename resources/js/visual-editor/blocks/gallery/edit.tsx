/**
 * Gallery — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/gallery/edit.js` (v9.43.0).
 * Behaviour parity is the goal for everything the saved markup depends
 * on: the `columns`, `imageCrop`, `linkTo`, `sizeSlug`, `aspectRatio`,
 * `caption` etc. attributes round-trip losslessly across editor
 * sessions, and the inner `core/image` blocks are kept in sync with
 * gallery-wide settings.
 *
 * Intentional divergences (documented in `upstream-state.json` under
 * `knownDivergences`):
 *
 *   - The shared `Caption` component from
 *     `@wordpress/block-library/src/utils/caption` is replaced by an
 *     inline `RichText` figcaption — the upstream module isn't
 *     reachable via the package's `exports`.
 *   - The `useToolsPanelDropdownMenuProps` hook is inlined from the
 *     upstream `utils/hooks.js` module for the same reason.
 *   - Native (`Platform.isNative`) branches are dropped — this fork
 *     ships web-only.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    SelectControl,
    ToggleControl,
    RangeControl,
    MenuGroup,
    MenuItem,
    __experimentalToolsPanel as ToolsPanel,
    __experimentalToolsPanelItem as ToolsPanelItem,
    __experimentalToggleGroupControl as ToggleGroupControl,
    __experimentalToggleGroupControlOption as ToggleGroupControlOption,
    ToolbarDropdownMenu,
} from '@wordpress/components';
import {
    store as blockEditorStore,
    MediaPlaceholder,
    InspectorControls,
    useBlockProps,
    useInnerBlocksProps,
    BlockControls,
    MediaReplaceFlow,
    useSettings,
    RichText,
} from '@wordpress/block-editor';
import { useEffect, useMemo } from '@wordpress/element';
import { __, _x, sprintf } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { createBlobURL } from '@wordpress/blob';
import { store as noticesStore } from '@wordpress/notices';
import { useViewportMatch } from '@wordpress/compose';
import {
    link as linkIcon,
    customLink,
    image as imageIcon,
    linkOff,
    fullscreen,
} from '@wordpress/icons';

import { sharedIcon } from './shared-icon';
import { defaultColumnsNumber, pickRelevantMediaFiles } from './shared';
import {
    getHrefAndDestination,
    getUpdatedLinkTargetSettings,
    getImageSizeAttributes,
} from './utils';
import {
    LINK_DESTINATION_ATTACHMENT,
    LINK_DESTINATION_MEDIA,
    LINK_DESTINATION_NONE,
    LINK_DESTINATION_LIGHTBOX,
    DEFAULT_MEDIA_SIZE_SLUG,
} from './constants';
import useImageSizes from './use-image-sizes';
import useGetNewImages from './use-get-new-images';
import useGetMedia from './use-get-media';
import GapStyles from './gap-styles';

const MAX_COLUMNS = 8;

interface LinkOption {
    icon: unknown;
    label: string;
    value: string;
    noticeText: string;
    infoText?: string;
}

const LINK_OPTIONS: LinkOption[] = [
    {
        icon: customLink,
        label: __('Link images to attachment pages'),
        value: LINK_DESTINATION_ATTACHMENT,
        noticeText: __('Attachment Pages'),
    },
    {
        icon: imageIcon,
        label: __('Link images to media files'),
        value: LINK_DESTINATION_MEDIA,
        noticeText: __('Media Files'),
    },
    {
        icon: fullscreen,
        label: __('Enlarge on click'),
        value: LINK_DESTINATION_LIGHTBOX,
        noticeText: __('Lightbox effect'),
        infoText: __('Scale images with a lightbox effect'),
    },
    {
        icon: linkOff,
        label: _x('None', 'Media item link option'),
        value: LINK_DESTINATION_NONE,
        noticeText: __('None'),
    },
];

const NAVIGATION_BUTTON_TYPE_OPTIONS = [
    { label: __('Icon'), value: 'icon' },
    { label: __('Text'), value: 'text' },
    { label: __('Both'), value: 'both' },
];

const ALLOWED_MEDIA_TYPES = ['image'];

const PLACEHOLDER_TEXT = __(
    'Drag and drop images, upload, or choose from your library.'
);

const DEFAULT_BLOCK = { name: 'core/image' };
const EMPTY_ARRAY: readonly never[] = [];

// Inlined from `@wordpress/block-library/src/utils/hooks.js` so the fork
// does not depend on block-library internals (see file-level comment).
function useToolsPanelDropdownMenuProps(): Record<string, unknown> {
    const isMobile = useViewportMatch('medium', '<');
    return !isMobile
        ? {
              popoverProps: {
                  placement: 'left-start',
                  offset: 259,
              },
          }
        : {};
}

interface InnerImageBlock {
    readonly clientId: string;
    readonly attributes: {
        readonly id?: number | string;
        readonly url?: string;
        readonly lightbox?: { enabled?: boolean };
        readonly [key: string]: unknown;
    };
    readonly originalContent?: string;
}

interface GalleryAttributes {
    readonly navigationButtonType?: string;
    readonly columns?: number;
    readonly imageCrop?: boolean;
    readonly randomOrder?: boolean;
    readonly linkTarget?: string;
    readonly linkTo?: string;
    readonly sizeSlug?: string;
    readonly aspectRatio?: string;
    readonly caption?: string;
    readonly style?: { readonly spacing?: { readonly blockGap?: unknown } };
    readonly lightbox?: { enabled?: boolean };
    [key: string]: unknown;
}

interface GalleryEditProps {
    readonly setAttributes: (next: Partial<GalleryAttributes>) => void;
    readonly attributes: GalleryAttributes;
    readonly className?: string;
    readonly clientId: string;
    readonly isSelected: boolean;
    readonly insertBlocksAfter?: (block: unknown) => void;
    readonly isContentLocked?: boolean;
    readonly onFocus?: () => void;
}

interface GalleryImage {
    clientId: string;
    id?: number | string;
    url?: string;
    attributes: InnerImageBlock['attributes'];
    fromSavedContent: boolean;
}

interface FileOrMediaItem {
    type?: string;
    id?: number | string;
    url?: string;
    blob?: string;
    caption?: string;
    alt?: string;
}

export default function GalleryEdit(props: GalleryEditProps): ReactElement {
    const {
        setAttributes,
        attributes,
        className,
        clientId,
        isSelected,
        insertBlocksAfter,
        isContentLocked,
    } = props;

    const settingsResult = useSettings(
        'blocks.core/image.lightbox',
        'dimensions.aspectRatios.default',
        'dimensions.aspectRatios.theme',
        'dimensions.defaultAspectRatios'
    ) as readonly [
        { allowEditing?: boolean; enabled?: boolean } | undefined,
        ReadonlyArray<{ name: string; ratio: string }> | undefined,
        ReadonlyArray<{ name: string; ratio: string }> | undefined,
        boolean | undefined,
    ];
    const [lightboxSetting, defaultRatios, themeRatios, showDefaultRatios] =
        settingsResult;

    const linkOptions = !lightboxSetting?.allowEditing
        ? LINK_OPTIONS.filter(
              (option) => option.value !== LINK_DESTINATION_LIGHTBOX
          )
        : LINK_OPTIONS;

    const {
        navigationButtonType,
        columns,
        imageCrop,
        randomOrder,
        linkTarget,
        linkTo,
        sizeSlug,
        aspectRatio,
        caption,
    } = attributes;

    const {
        __unstableMarkNextChangeAsNotPersistent,
        replaceInnerBlocks,
        updateBlockAttributes,
        selectBlock,
    } = useDispatch(blockEditorStore) as unknown as {
        __unstableMarkNextChangeAsNotPersistent: () => void;
        replaceInnerBlocks: (
            clientId: string,
            blocks: ReadonlyArray<unknown>
        ) => void;
        updateBlockAttributes: (
            clientIds: string | string[],
            attrs: Record<string, unknown>,
            uniqueByBlockOrFlag?: { uniqueByBlock?: boolean } | boolean
        ) => void;
        selectBlock: (clientId: string) => void;
    };
    const { createSuccessNotice, createErrorNotice } = useDispatch(
        noticesStore
    ) as unknown as {
        createSuccessNotice: (
            message: string,
            options: Record<string, unknown>
        ) => void;
        createErrorNotice: (
            message: string,
            options: Record<string, unknown>
        ) => void;
    };

    const {
        getBlock,
        getSettings,
        innerBlockImages,
        multiGallerySelection,
    } = useSelect(
        (select) => {
            const store = select(blockEditorStore) as unknown as {
                getBlockName: (clientId: string) => string;
                getMultiSelectedBlockClientIds: () => readonly string[];
                getSettings: () => { imageSizes: ReadonlyArray<{ name: string; slug: string }> };
                getBlock: (clientId: string) => {
                    innerBlocks: readonly InnerImageBlock[];
                } | null;
                wasBlockJustInserted: (
                    clientId: string,
                    source: string
                ) => boolean;
            };
            const multiSelectedClientIds =
                store.getMultiSelectedBlockClientIds();

            return {
                getBlock: store.getBlock,
                getSettings: store.getSettings,
                innerBlockImages:
                    (store.getBlock(clientId)
                        ?.innerBlocks as readonly InnerImageBlock[]) ??
                    (EMPTY_ARRAY as readonly InnerImageBlock[]),
                blockWasJustInserted: store.wasBlockJustInserted(
                    clientId,
                    'inserter_menu'
                ),
                multiGallerySelection:
                    multiSelectedClientIds.length > 0 &&
                    multiSelectedClientIds.every(
                        (_clientId) =>
                            store.getBlockName(_clientId) ===
                                'core/gallery' ||
                            store.getBlockName(_clientId) ===
                                'artisanpack/gallery'
                    ),
            };
        },
        [clientId]
    ) as {
        getBlock: (clientId: string) => {
            innerBlocks: readonly InnerImageBlock[];
        } | null;
        getSettings: () => { imageSizes: ReadonlyArray<{ name: string; slug: string }> };
        innerBlockImages: readonly InnerImageBlock[];
        blockWasJustInserted: boolean;
        multiGallerySelection: boolean;
    };

    const images: GalleryImage[] = useMemo(
        () =>
            innerBlockImages?.map((block) => ({
                clientId: block.clientId,
                id: block.attributes.id,
                url: block.attributes.url,
                attributes: block.attributes,
                fromSavedContent: Boolean(block.originalContent),
            })) ?? [],
        [innerBlockImages]
    );

    const imageData = useGetMedia(innerBlockImages);

    const newImages = useGetNewImages(images, imageData);

    const hasLightboxImages = lightboxSetting?.enabled
        ? images.filter(
              (image) =>
                  image.attributes?.lightbox?.enabled === undefined ||
                  image.attributes?.lightbox?.enabled === true
          ).length > 0
        : images.filter((image) => image.attributes.lightbox?.enabled).length >
          0;

    const themeOptions = themeRatios?.map(({ name, ratio }) => ({
        label: name,
        value: ratio,
    }));
    const defaultOptions = defaultRatios?.map(({ name, ratio }) => ({
        label: name,
        value: ratio,
    }));
    const aspectRatioOptions = [
        {
            label: _x('Original', 'Aspect ratio option for dimensions control'),
            value: 'auto',
        },
        ...(showDefaultRatios ? defaultOptions || [] : []),
        ...(themeOptions || []),
    ];

    useEffect(() => {
        newImages?.forEach((newImage) => {
            __unstableMarkNextChangeAsNotPersistent();
            updateBlockAttributes(newImage.clientId as string, {
                ...buildImageAttributes(newImage.attributes),
                id: newImage.id,
                align: undefined,
            });
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [newImages]);

    const imageSizeOptions = useImageSizes(
        imageData as unknown as Parameters<typeof useImageSizes>[0],
        isSelected,
        getSettings
    );

    function buildImageAttributes(
        imageAttributes: InnerImageBlock['attributes'] & {
            className?: string;
            linkTarget?: string;
            rel?: string;
            caption?: string | { length?: number };
            alt?: string;
            linkDestination?: string;
        }
    ): Record<string, unknown> {
        const image = imageAttributes.id
            ? (imageData.find(
                  (m) => (m as { id?: number | string }).id === imageAttributes.id
              ) as Record<string, unknown> | undefined)
            : null;

        let newClassName: string | undefined;
        if (imageAttributes.className && imageAttributes.className !== '') {
            newClassName = imageAttributes.className;
        }

        let newLinkTarget: { linkTarget?: string; rel?: string };
        if (imageAttributes.linkTarget || imageAttributes.rel) {
            newLinkTarget = {
                linkTarget: imageAttributes.linkTarget,
                rel: imageAttributes.rel,
            };
        } else {
            newLinkTarget = getUpdatedLinkTargetSettings(linkTarget, attributes);
        }

        const incomingCaption = imageAttributes.caption as
            | string
            | { length?: number }
            | undefined;
        const captionLength =
            typeof incomingCaption === 'string'
                ? incomingCaption.length
                : (incomingCaption?.length ?? 0);

        return {
            ...pickRelevantMediaFiles(
                image as Parameters<typeof pickRelevantMediaFiles>[0],
                sizeSlug
            ),
            ...getHrefAndDestination(
                image as Parameters<typeof getHrefAndDestination>[0],
                linkTo,
                (imageAttributes as { linkDestination?: string }).linkDestination
            ),
            ...newLinkTarget,
            className: newClassName,
            sizeSlug,
            caption:
                captionLength > 0
                    ? incomingCaption
                    : (image as { caption?: { raw?: string } } | undefined)
                          ?.caption?.raw,
            alt:
                imageAttributes.alt ||
                (image as { alt_text?: string } | undefined)?.alt_text,
            aspectRatio: aspectRatio === 'auto' ? undefined : aspectRatio,
        };
    }

    function isValidFileType(file: FileOrMediaItem): boolean {
        return (
            ALLOWED_MEDIA_TYPES.some(
                (mediaType) => file.type?.indexOf(mediaType) === 0
            ) || !!file.blob
        );
    }

    function updateImages(
        selectedImages: FileList | readonly FileOrMediaItem[]
    ): void {
        const newFileUploads =
            Object.prototype.toString.call(selectedImages) ===
            '[object FileList]';

        const imageArray: FileOrMediaItem[] = newFileUploads
            ? Array.from(selectedImages as FileList).map((file) => {
                  const f = file as unknown as FileOrMediaItem;
                  if (!f.url) {
                      return {
                          blob: createBlobURL(file as File),
                      } as FileOrMediaItem;
                  }
                  return f;
              })
            : Array.from(selectedImages as readonly FileOrMediaItem[]);

        if (!imageArray.every(isValidFileType)) {
            createErrorNotice(
                __(
                    'If uploading to a gallery all files need to be image formats'
                ),
                { id: 'gallery-upload-invalid-file', type: 'snackbar' }
            );
        }

        const processedImages = imageArray
            .filter((file) => file.url || isValidFileType(file))
            .map((file) => {
                if (!file.url) {
                    return {
                        blob:
                            file.blob ||
                            createBlobURL(file as unknown as File),
                    } as FileOrMediaItem;
                }
                return file;
            });

        const newOrderMap = processedImages.reduce<Record<string, number>>(
            (result, image, index) => {
                if (image.id !== undefined) {
                    result[String(image.id)] = index;
                }
                return result;
            },
            {}
        );

        const existingImageBlocks = !newFileUploads
            ? innerBlockImages.filter((block) =>
                  processedImages.find((img) => img.id === block.attributes.id)
              )
            : innerBlockImages;

        const newImageList = processedImages.filter(
            (img) =>
                !existingImageBlocks.find(
                    (existingImg) => img.id === existingImg.attributes.id
                )
        );

        const newBlocks = newImageList.map((image) =>
            createBlock('core/image', {
                id: image.id,
                blob: image.blob,
                url: image.url,
                caption: image.caption,
                alt: image.alt,
            })
        );

        replaceInnerBlocks(
            clientId,
            (existingImageBlocks as ReadonlyArray<unknown>)
                .concat(newBlocks)
                .sort((a, b) => {
                    const aId = String(
                        (a as InnerImageBlock).attributes?.id ?? ''
                    );
                    const bId = String(
                        (b as InnerImageBlock).attributes?.id ?? ''
                    );
                    return (newOrderMap[aId] ?? 0) - (newOrderMap[bId] ?? 0);
                })
        );

        if (newBlocks?.length > 0) {
            selectBlock((newBlocks[0] as { clientId: string }).clientId);
        }
    }

    function onUploadError(message: string): void {
        createErrorNotice(message, { type: 'snackbar' });
    }

    function setLinkTo(value: string): void {
        setAttributes({ linkTo: value });
        const changedAttributes: Record<string, Record<string, unknown>> = {};
        const blocks: string[] = [];
        getBlock(clientId)?.innerBlocks.forEach((block) => {
            blocks.push(block.clientId);
            const image = block.attributes.id
                ? (imageData.find(
                      (m) => (m as { id?: number | string }).id ===
                          block.attributes.id
                  ) as Record<string, unknown> | undefined)
                : null;

            changedAttributes[block.clientId] = getHrefAndDestination(
                image as Parameters<typeof getHrefAndDestination>[0],
                value,
                false,
                block.attributes as Parameters<typeof getHrefAndDestination>[3],
                lightboxSetting
            );
        });
        updateBlockAttributes(blocks, changedAttributes, {
            uniqueByBlock: true,
        });
        const linkToText = [...linkOptions].find(
            (linkType) => linkType.value === value
        );

        createSuccessNotice(
            sprintf(
                /* translators: %s: image size settings */
                __('All gallery image links updated to: %s'),
                linkToText?.noticeText ?? value
            ),
            {
                id: 'gallery-attributes-linkTo',
                type: 'snackbar',
            }
        );
    }

    function setColumnsNumber(value: number | undefined): void {
        setAttributes({ columns: value });
    }

    function toggleImageCrop(): void {
        setAttributes({ imageCrop: !imageCrop });
    }

    function toggleRandomOrder(): void {
        setAttributes({ randomOrder: !randomOrder });
    }

    function toggleOpenInNewTab(openInNewTab: boolean): void {
        const newLinkTarget = openInNewTab ? '_blank' : undefined;
        setAttributes({ linkTarget: newLinkTarget });
        const changedAttributes: Record<string, Record<string, unknown>> = {};
        const blocks: string[] = [];
        getBlock(clientId)?.innerBlocks.forEach((block) => {
            blocks.push(block.clientId);
            changedAttributes[block.clientId] = getUpdatedLinkTargetSettings(
                newLinkTarget,
                block.attributes as { rel?: string }
            );
        });
        updateBlockAttributes(blocks, changedAttributes, {
            uniqueByBlock: true,
        });
        const noticeText = openInNewTab
            ? __('All gallery images updated to open in new tab')
            : __('All gallery images updated to not open in new tab');
        createSuccessNotice(noticeText, {
            id: 'gallery-attributes-openInNewTab',
            type: 'snackbar',
        });
    }

    function updateImagesSize(newSizeSlug: string): void {
        setAttributes({ sizeSlug: newSizeSlug });
        const changedAttributes: Record<string, Record<string, unknown>> = {};
        const blocks: string[] = [];
        getBlock(clientId)?.innerBlocks.forEach((block) => {
            blocks.push(block.clientId);
            const image = block.attributes.id
                ? (imageData.find(
                      (m) => (m as { id?: number | string }).id ===
                          block.attributes.id
                  ) as Record<string, unknown> | undefined)
                : null;
            changedAttributes[block.clientId] = getImageSizeAttributes(
                image as Parameters<typeof getImageSizeAttributes>[0],
                newSizeSlug
            );
        });
        updateBlockAttributes(blocks, changedAttributes, {
            uniqueByBlock: true,
        });
        const imageSize = imageSizeOptions?.find(
            (size) => size.value === newSizeSlug
        );

        createSuccessNotice(
            sprintf(
                /* translators: %s: image size settings */
                __('All gallery image sizes updated to: %s'),
                imageSize?.label ?? newSizeSlug
            ),
            {
                id: 'gallery-attributes-sizeSlug',
                type: 'snackbar',
            }
        );
    }

    function setAspectRatio(value: string): void {
        setAttributes({ aspectRatio: value });

        const changedAttributes: Record<string, Record<string, unknown>> = {};
        const blocks: string[] = [];

        getBlock(clientId)?.innerBlocks.forEach((block) => {
            blocks.push(block.clientId);
            changedAttributes[block.clientId] = {
                aspectRatio: value === 'auto' ? undefined : value,
            };
        });

        updateBlockAttributes(blocks, changedAttributes, true);

        const aspectRatioText = aspectRatioOptions.find(
            (option) => option.value === value
        );

        createSuccessNotice(
            sprintf(
                /* translators: %s: aspect ratio setting */
                __('All gallery images updated to aspect ratio: %s'),
                aspectRatioText?.label || value
            ),
            {
                id: 'gallery-attributes-aspectRatio',
                type: 'snackbar',
            }
        );
    }

    useEffect(() => {
        // linkTo attribute must be saved so blocks don't break when changing
        // image_default_link_type in options.php.
        if (!linkTo) {
            __unstableMarkNextChangeAsNotPersistent();
            const defaultLink =
                (
                    window as unknown as {
                        wp?: {
                            media?: {
                                view?: {
                                    settings?: {
                                        defaultProps?: { link?: string };
                                    };
                                };
                            };
                        };
                    }
                ).wp?.media?.view?.settings?.defaultProps?.link ||
                LINK_DESTINATION_NONE;
            setAttributes({ linkTo: defaultLink });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [linkTo]);

    const hasImages = !!images.length;
    const hasImageIds = hasImages && images.some((image) => !!image.id);
    const imagesUploading = images.some(
        (img) => !img.id && img.url?.indexOf('blob:') === 0
    );

    const mediaPlaceholder = (
        <MediaPlaceholder
            handleUpload={false}
            icon={sharedIcon}
            labels={{
                title: __('Gallery'),
                instructions: PLACEHOLDER_TEXT,
            }}
            onSelect={
                updateImages as unknown as (media: unknown) => void
            }
            allowedTypes={ALLOWED_MEDIA_TYPES}
            multiple
            onError={onUploadError}
            addToGallery={false}
            disableMediaButtons={imagesUploading}
            value={{} as unknown as never[]}
        />
    );

    const blockProps = useBlockProps({
        className: clsx(className, 'has-nested-images'),
    });

    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        defaultBlock: DEFAULT_BLOCK,
        directInsert: true,
        orientation: 'horizontal',
        renderAppender: false,
    });

    const dropdownMenuProps = useToolsPanelDropdownMenuProps();

    if (!hasImages) {
        return (
            <div {...innerBlocksProps}>
                {(innerBlocksProps as { children?: React.ReactNode }).children}
                {mediaPlaceholder}
            </div>
        );
    }

    const hasLinkTo = linkTo && linkTo !== 'none';

    return (
        <>
            <InspectorControls>
                <ToolsPanel
                    label={__('Settings')}
                    resetAll={() => {
                        setAttributes({
                            navigationButtonType: 'icon',
                            columns: undefined,
                            imageCrop: true,
                            randomOrder: false,
                        });

                        setAspectRatio('auto');

                        if (sizeSlug !== DEFAULT_MEDIA_SIZE_SLUG) {
                            updateImagesSize(DEFAULT_MEDIA_SIZE_SLUG);
                        }

                        if (linkTarget) {
                            toggleOpenInNewTab(false);
                        }
                    }}
                    dropdownMenuProps={dropdownMenuProps}
                >
                    {images.length > 1 && (
                        <ToolsPanelItem
                            isShownByDefault
                            label={__('Columns')}
                            hasValue={() =>
                                !!columns && columns !== images.length
                            }
                            onDeselect={() => setColumnsNumber(undefined)}
                        >
                            <RangeControl
                                label={__('Columns')}
                                value={
                                    columns
                                        ? columns
                                        : defaultColumnsNumber(images.length)
                                }
                                onChange={
                                    setColumnsNumber as (value: number) => void
                                }
                                min={1}
                                max={Math.min(MAX_COLUMNS, images.length)}
                                required
                                __next40pxDefaultSize
                            />
                        </ToolsPanelItem>
                    )}
                    {(imageSizeOptions?.length ?? 0) > 0 && (
                        <ToolsPanelItem
                            isShownByDefault
                            label={__('Resolution')}
                            hasValue={() => sizeSlug !== DEFAULT_MEDIA_SIZE_SLUG}
                            onDeselect={() =>
                                updateImagesSize(DEFAULT_MEDIA_SIZE_SLUG)
                            }
                        >
                            <SelectControl
                                label={__('Resolution')}
                                help={__(
                                    'Select the size of the source images.'
                                )}
                                value={sizeSlug}
                                options={
                                    imageSizeOptions as Array<{
                                        label: string;
                                        value: string;
                                    }>
                                }
                                onChange={updateImagesSize}
                                hideCancelButton
                                size="__unstable-large"
                            />
                        </ToolsPanelItem>
                    )}
                    <ToolsPanelItem
                        isShownByDefault
                        label={__('Crop images to fit')}
                        hasValue={() => !imageCrop}
                        onDeselect={() => setAttributes({ imageCrop: true })}
                    >
                        <ToggleControl
                            label={__('Crop images to fit')}
                            checked={!!imageCrop}
                            onChange={toggleImageCrop}
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        isShownByDefault
                        label={__('Randomize order')}
                        hasValue={() => !!randomOrder}
                        onDeselect={() => setAttributes({ randomOrder: false })}
                    >
                        <ToggleControl
                            label={__('Randomize order')}
                            checked={!!randomOrder}
                            onChange={toggleRandomOrder}
                        />
                    </ToolsPanelItem>
                    {hasLinkTo && (
                        <ToolsPanelItem
                            isShownByDefault
                            label={__('Open images in new tab')}
                            hasValue={() => !!linkTarget}
                            onDeselect={() => toggleOpenInNewTab(false)}
                        >
                            <ToggleControl
                                label={__('Open images in new tab')}
                                checked={linkTarget === '_blank'}
                                onChange={toggleOpenInNewTab}
                            />
                        </ToolsPanelItem>
                    )}
                    {aspectRatioOptions.length > 1 && (
                        <ToolsPanelItem
                            hasValue={() =>
                                !!aspectRatio && aspectRatio !== 'auto'
                            }
                            label={__('Aspect ratio')}
                            onDeselect={() => setAspectRatio('auto')}
                            isShownByDefault
                        >
                            <SelectControl
                                __next40pxDefaultSize
                                label={__('Aspect ratio')}
                                help={__(
                                    'Set a consistent aspect ratio for all images in the gallery.'
                                )}
                                value={aspectRatio}
                                options={aspectRatioOptions}
                                onChange={setAspectRatio}
                            />
                        </ToolsPanelItem>
                    )}
                    <ToolsPanelItem
                        label={__('Navigation button type')}
                        isShownByDefault
                        hasValue={() => navigationButtonType !== 'icon'}
                        onDeselect={() =>
                            setAttributes({ navigationButtonType: 'icon' })
                        }
                    >
                        {hasLightboxImages && (
                            <ToggleGroupControl
                                label={__('Navigation button type')}
                                value={navigationButtonType}
                                onChange={(value: string | number) =>
                                    setAttributes({
                                        navigationButtonType: String(value),
                                    })
                                }
                                isBlock
                                __next40pxDefaultSize
                                help={__(
                                    'Adjust the appearance of buttons in the lightbox.'
                                )}
                            >
                                {NAVIGATION_BUTTON_TYPE_OPTIONS.map(
                                    (option) => (
                                        <ToggleGroupControlOption
                                            key={option.value}
                                            value={option.value}
                                            label={option.label}
                                        />
                                    )
                                )}
                            </ToggleGroupControl>
                        )}
                    </ToolsPanelItem>
                </ToolsPanel>
            </InspectorControls>
            <BlockControls group="block">
                <ToolbarDropdownMenu icon={linkIcon} label={__('Link')}>
                    {({ onClose }: { onClose: () => void }) => (
                        <MenuGroup>
                            {linkOptions.map((linkItem) => {
                                const isOptionSelected = linkTo === linkItem.value;
                                return (
                                    <MenuItem
                                        key={linkItem.value}
                                        isSelected={isOptionSelected}
                                        className={clsx(
                                            'components-dropdown-menu__menu-item',
                                            {
                                                'is-active': isOptionSelected,
                                            }
                                        )}
                                        iconPosition="left"
                                        icon={
                                            linkItem.icon as Parameters<
                                                typeof MenuItem
                                            >[0]['icon']
                                        }
                                        onClick={() => {
                                            setLinkTo(linkItem.value);
                                            onClose();
                                        }}
                                        role="menuitemradio"
                                        info={linkItem.infoText}
                                    >
                                        {linkItem.label}
                                    </MenuItem>
                                );
                            })}
                        </MenuGroup>
                    )}
                </ToolbarDropdownMenu>
            </BlockControls>
            {!multiGallerySelection && (
                <BlockControls group="other">
                    <MediaReplaceFlow
                        allowedTypes={ALLOWED_MEDIA_TYPES}
                        handleUpload={false}
                        onSelect={
                            updateImages as unknown as (
                                media: unknown
                            ) => void
                        }
                        name={__('Add')}
                        multiple
                        mediaIds={images
                            .filter((image) => image.id)
                            .map((image) => image.id as number)}
                        addToGallery={hasImageIds}
                        variant="toolbar"
                    />
                </BlockControls>
            )}
            <GapStyles
                blockGap={
                    attributes.style?.spacing?.blockGap as
                        | string
                        | undefined
                        | { top?: string; left?: string }
                }
                clientId={clientId}
            />
            <figure
                {...(innerBlocksProps as Record<string, unknown>)}
                className={clsx(
                    (innerBlocksProps as { className?: string }).className,
                    'blocks-gallery-grid',
                    {
                        [`columns-${columns}`]: columns !== undefined,
                        'columns-default': columns === undefined,
                        'is-cropped': imageCrop,
                    }
                )}
            >
                {(innerBlocksProps as { children?: React.ReactNode }).children}
                {isSelected &&
                    !(innerBlocksProps as { children?: React.ReactNode })
                        .children && (
                        <div className="blocks-gallery-media-placeholder-wrapper">
                            {mediaPlaceholder}
                        </div>
                    )}
                {(isSelected && !isContentLocked) ||
                !RichText.isEmpty(caption ?? '') ? (
                    <RichText
                        identifier="caption"
                        tagName="figcaption"
                        className="blocks-gallery-caption"
                        aria-label={__('Gallery caption text')}
                        placeholder={__('Add gallery caption')}
                        value={caption ?? ''}
                        onChange={(value: string) =>
                            setAttributes({ caption: value })
                        }
                        inlineToolbar
                        __unstableOnSplitAtEnd={() =>
                            insertBlocksAfter?.(
                                createBlock('core/paragraph')
                            )
                        }
                    />
                ) : null}
            </figure>
        </>
    );
}
