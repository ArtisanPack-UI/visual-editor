/**
 * Media & Text — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/media-text/edit.js` (v9.43.0).
 * Behaviour parity is the goal for everything the saved markup depends on:
 * the media + grid attributes round-trip losslessly across editor sessions.
 *
 * Intentional divergences (documented in `upstream-state.json` under
 * `knownDivergences`):
 *
 *   - `useToolsPanelDropdownMenuProps` (from `@wordpress/block-library`'s
 *     internal `utils/hooks`) is replaced with an inline no-op stub that
 *     returns an empty object. The ToolsPanel reset menu still works; only
 *     the optional "Display more …" affordance the hook adds is absent.
 *   - The image-size resolution tool (`ResolutionTool` from
 *     `blockEditorPrivateApis` via the `unlock` helper) is omitted. That
 *     control is gated on private block-editor APIs that are not part of
 *     the package's `exports` field. Image size selection still works via
 *     the standard image controls; the per-block size picker is the only
 *     loss.
 */

import type { CSSProperties, ReactElement, ReactNode, Ref } from 'react';
import clsx from 'clsx';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useRef, useState } from '@wordpress/element';
import {
    BlockControls,
    BlockVerticalAlignmentControl,
    InspectorControls,
    useBlockEditingMode,
    useBlockProps,
    useInnerBlocksProps,
    __experimentalImageURLInputUI as ImageURLInputUI,
    store as blockEditorStore,
} from '@wordpress/block-editor';
import {
    ExternalLink,
    FocalPointPicker,
    RangeControl,
    TextareaControl,
    ToggleControl,
    ToolbarButton,
    __experimentalToolsPanel as ToolsPanel,
    __experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { getBlobTypeByURL, isBlobURL } from '@wordpress/blob';
import { pullLeft, pullRight } from '@wordpress/icons';
import { store as coreStore, useEntityProp } from '@wordpress/core-data';

import MediaContainer from './media-container';
import {
    DEFAULT_MEDIA_SIZE_SLUG,
    LINK_DESTINATION_ATTACHMENT,
    LINK_DESTINATION_MEDIA,
    LINK_DESTINATION_NONE,
    TEMPLATE,
    WIDTH_CONSTRAINT_PERCENTAGE,
} from './constants';
import type { FocalPoint } from './image-fill';

interface MediaTextAttributes {
    readonly focalPoint?: FocalPoint;
    readonly href?: string;
    readonly imageFill?: boolean;
    readonly isStackedOnMobile?: boolean;
    readonly linkClass?: string;
    readonly linkDestination?: string;
    readonly linkTarget?: string;
    readonly mediaAlt?: string;
    readonly mediaId?: number;
    readonly mediaPosition?: string;
    readonly mediaType?: string;
    readonly mediaUrl?: string;
    readonly mediaWidth?: number;
    readonly mediaSizeSlug?: string;
    readonly rel?: string;
    readonly verticalAlignment?: string;
    readonly allowedBlocks?: readonly string[];
    readonly useFeaturedImage?: boolean;
    readonly mediaLink?: string;
}

interface MediaSelection {
    readonly id?: number;
    readonly url?: string;
    readonly alt?: string;
    readonly link?: string;
    readonly type?: string;
    readonly media_type?: string;
    readonly sizes?: { readonly large?: { readonly url?: string } };
    readonly media_details?: {
        readonly sizes?: Record<
            string,
            { readonly source_url?: string } | undefined
        >;
    };
    readonly [key: string]: unknown;
}

interface MediaTextEditProps {
    readonly attributes: MediaTextAttributes;
    readonly isSelected: boolean;
    readonly setAttributes: (next: Partial<MediaTextAttributes>) => void;
    readonly context?: {
        readonly postId?: number;
        readonly postType?: string;
    };
}

interface AttachmentEntity {
    readonly source_url?: string;
    readonly alt_text?: string;
    readonly link?: string;
    readonly media_details?: {
        readonly sizes?: Record<
            string,
            { readonly source_url?: string } | undefined
        >;
    };
}

// Inline no-op replacement for upstream's
// `useToolsPanelDropdownMenuProps` (lives in block-library's private
// `utils/hooks` module). Returning an empty object preserves the
// ToolsPanel reset behaviour; only the optional dropdown affordance the
// hook would add is absent.
function useToolsPanelDropdownMenuProps(): Record<string, never> {
    return {};
}

// this limits the resize to a safe zone to avoid making broken layouts
const applyWidthConstraints = (width: number): number =>
    Math.max(
        WIDTH_CONSTRAINT_PERCENTAGE,
        Math.min(width, 100 - WIDTH_CONSTRAINT_PERCENTAGE)
    );

function getImageSourceUrlBySizeSlug(
    image: AttachmentEntity | null | undefined,
    slug: string
): string | undefined {
    return image?.media_details?.sizes?.[slug]?.source_url;
}

interface AttributesFromMediaArgs {
    readonly attributes: MediaTextAttributes;
    readonly setAttributes: (next: Partial<MediaTextAttributes>) => void;
}

function attributesFromMedia({
    attributes: { linkDestination, href },
    setAttributes,
}: AttributesFromMediaArgs) {
    return (media: MediaSelection | undefined): void => {
        if (!media || !media.url) {
            setAttributes({
                mediaAlt: undefined,
                mediaId: undefined,
                mediaType: undefined,
                mediaUrl: undefined,
                mediaLink: undefined,
                href: undefined,
                focalPoint: undefined,
                useFeaturedImage: false,
            });
            return;
        }

        const resolvedMedia = media as MediaSelection & { type?: string };
        if (isBlobURL(media.url)) {
            (resolvedMedia as { type?: string }).type =
                getBlobTypeByURL(media.url) ?? undefined;
        }

        let mediaType: string | undefined;
        let src: string | undefined;
        // For media selections originated from a file upload.
        if (resolvedMedia.media_type) {
            if (resolvedMedia.media_type === 'image') {
                mediaType = 'image';
            } else {
                // only images and videos are accepted so if the media_type is
                // not an image we can assume it is a video. video contain the
                // media type of 'file' in the object returned from the rest
                // api.
                mediaType = 'video';
            }
        } else {
            // For media selections originated from existing files in the
            // media library.
            mediaType = resolvedMedia.type;
        }

        if (mediaType === 'image') {
            // Try the "large" size URL, falling back to the "full" size URL
            // below.
            src =
                resolvedMedia.sizes?.large?.url ||
                resolvedMedia.media_details?.sizes?.large?.source_url;
        }

        let newLinkDestination = linkDestination;
        let newHref = href;

        // Only apply default link behavior for images (not videos).
        if (mediaType === 'image') {
            // Check if default link setting should be used.
            if (!newLinkDestination) {
                // Use the WordPress option to determine the proper default.
                // The constants used in Gutenberg do not match WP options so
                // a little more complicated than ideal.
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
                switch (defaultLink) {
                    case 'file':
                    case LINK_DESTINATION_MEDIA:
                        newLinkDestination = LINK_DESTINATION_MEDIA;
                        break;
                    case 'post':
                    case LINK_DESTINATION_ATTACHMENT:
                        newLinkDestination = LINK_DESTINATION_ATTACHMENT;
                        break;
                    case LINK_DESTINATION_NONE:
                    default:
                        newLinkDestination = LINK_DESTINATION_NONE;
                        break;
                }
            }

            // Set href based on linkDestination.
            switch (newLinkDestination) {
                case LINK_DESTINATION_MEDIA:
                    newHref = media.url;
                    break;
                case LINK_DESTINATION_ATTACHMENT:
                    newHref = media.link;
                    break;
            }
        }

        setAttributes({
            mediaAlt: media.alt,
            mediaId: media.id,
            mediaType,
            mediaUrl: src || media.url,
            mediaLink: media.link || undefined,
            href: newHref,
            linkDestination: newLinkDestination,
            focalPoint: undefined,
            useFeaturedImage: false,
        });
    };
}

function MediaTextEdit({
    attributes,
    isSelected,
    setAttributes,
    context: { postId, postType } = {},
}: MediaTextEditProps): ReactElement {
    const {
        focalPoint,
        href,
        imageFill,
        isStackedOnMobile,
        linkClass,
        linkDestination,
        linkTarget,
        mediaAlt,
        mediaId,
        mediaPosition,
        mediaType,
        mediaUrl,
        mediaWidth,
        mediaSizeSlug: _mediaSizeSlug,
        rel,
        verticalAlignment,
        allowedBlocks,
        useFeaturedImage,
    } = attributes;

    const [featuredImage] = useEntityProp(
        'postType',
        postType,
        'featured_media',
        postId
    );

    const { featuredImageMedia } = useSelect(
        (select) => {
            return {
                featuredImageMedia:
                    featuredImage && useFeaturedImage
                        ? (
                              select(coreStore) as {
                                  getEntityRecord: (
                                      kind: string,
                                      name: string,
                                      id: number,
                                      query?: Record<string, unknown>
                                  ) => AttachmentEntity | undefined;
                              }
                          ).getEntityRecord(
                              'postType',
                              'attachment',
                              featuredImage as number,
                              { context: 'view' }
                          )
                        : undefined,
            };
        },
        [featuredImage, useFeaturedImage]
    );

    const { image } = useSelect(
        (select) => {
            return {
                image:
                    mediaId && isSelected
                        ? (
                              select(coreStore) as {
                                  getEntityRecord: (
                                      kind: string,
                                      name: string,
                                      id: number,
                                      query?: Record<string, unknown>
                                  ) => AttachmentEntity | null;
                              }
                          ).getEntityRecord(
                              'postType',
                              'attachment',
                              mediaId,
                              { context: 'view' }
                          )
                        : null,
            };
        },
        [isSelected, mediaId]
    );

    const featuredImageURL = useFeaturedImage
        ? featuredImageMedia?.source_url
        : '';
    const featuredImageAlt = useFeaturedImage
        ? featuredImageMedia?.alt_text
        : '';

    const toggleUseFeaturedImage = (): void => {
        setAttributes({
            imageFill: false,
            mediaType: 'image',
            mediaId: undefined,
            mediaUrl: undefined,
            mediaAlt: undefined,
            mediaLink: undefined,
            linkDestination: undefined,
            linkTarget: undefined,
            linkClass: undefined,
            rel: undefined,
            href: undefined,
            useFeaturedImage: !useFeaturedImage,
        });
    };

    const refMedia = useRef<HTMLImageElement | HTMLVideoElement | null>(null);
    const imperativeFocalPointPreview = (value: FocalPoint): void => {
        if (!refMedia.current) {
            return;
        }
        const { style } = refMedia.current;
        const { x, y } = value;
        style.objectPosition = `${x * 100}% ${y * 100}%`;
    };

    const [temporaryMediaWidth, setTemporaryMediaWidth] = useState<
        number | null
    >(null);

    const onSelectMedia = attributesFromMedia({ attributes, setAttributes });

    const onWidthChange = (width: number): void => {
        setTemporaryMediaWidth(applyWidthConstraints(width));
    };
    const commitWidthChange = (width: number): void => {
        setAttributes({
            mediaWidth: applyWidthConstraints(width),
        });
        setTemporaryMediaWidth(null);
    };

    const classNames = clsx({
        'has-media-on-the-right': 'right' === mediaPosition,
        'is-selected': isSelected,
        'is-stacked-on-mobile': isStackedOnMobile,
        [`is-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
        'is-image-fill-element': imageFill,
    });
    const resolvedMediaWidth = mediaWidth ?? 50;
    const widthString = `${temporaryMediaWidth || resolvedMediaWidth}%`;
    const gridTemplateColumns =
        'right' === mediaPosition
            ? `1fr ${widthString}`
            : `${widthString} 1fr`;
    const style: CSSProperties & { msGridColumns?: string } = {
        gridTemplateColumns,
        msGridColumns: gridTemplateColumns,
    };
    const onMediaAltChange = (newMediaAlt: string): void => {
        setAttributes({ mediaAlt: newMediaAlt });
    };
    const onVerticalAlignmentChange = (alignment: string | undefined): void => {
        setAttributes({ verticalAlignment: alignment });
    };
    const updateImage = (newMediaSizeSlug: string): null | void => {
        const newUrl = getImageSourceUrlBySizeSlug(image, newMediaSizeSlug);

        if (!newUrl) {
            return null;
        }

        setAttributes({
            mediaUrl: newUrl,
            mediaSizeSlug: newMediaSizeSlug,
        });
    };
    const dropdownMenuProps = useToolsPanelDropdownMenuProps();

    const mediaTextGeneralSettings: ReactNode = (
        <ToolsPanel
            label={__('Settings')}
            resetAll={() => {
                setAttributes({
                    isStackedOnMobile: true,
                    imageFill: false,
                    mediaAlt: '',
                    focalPoint: undefined,
                    mediaWidth: 50,
                });
                updateImage(DEFAULT_MEDIA_SIZE_SLUG);
            }}
            dropdownMenuProps={dropdownMenuProps}
        >
            <ToolsPanelItem
                label={__('Media width')}
                isShownByDefault
                hasValue={() => resolvedMediaWidth !== 50}
                onDeselect={() => setAttributes({ mediaWidth: 50 })}
            >
                <RangeControl
                    __next40pxDefaultSize
                    label={__('Media width')}
                    value={temporaryMediaWidth || resolvedMediaWidth}
                    onChange={(value?: number) =>
                        commitWidthChange(value ?? 50)
                    }
                    min={WIDTH_CONSTRAINT_PERCENTAGE}
                    max={100 - WIDTH_CONSTRAINT_PERCENTAGE}
                />
            </ToolsPanelItem>
            <ToolsPanelItem
                label={__('Stack on mobile')}
                isShownByDefault
                hasValue={() => !isStackedOnMobile}
                onDeselect={() =>
                    setAttributes({ isStackedOnMobile: true })
                }
            >
                <ToggleControl
                    label={__('Stack on mobile')}
                    checked={!!isStackedOnMobile}
                    onChange={() =>
                        setAttributes({
                            isStackedOnMobile: !isStackedOnMobile,
                        })
                    }
                />
            </ToolsPanelItem>
            {mediaType === 'image' && (
                <ToolsPanelItem
                    label={__('Crop image to fill')}
                    isShownByDefault
                    hasValue={() => !!imageFill}
                    onDeselect={() => setAttributes({ imageFill: false })}
                >
                    <ToggleControl
                        label={__('Crop image to fill')}
                        checked={!!imageFill}
                        onChange={() =>
                            setAttributes({
                                imageFill: !imageFill,
                            })
                        }
                    />
                </ToolsPanelItem>
            )}
            {imageFill &&
                (mediaUrl || featuredImageURL) &&
                mediaType === 'image' && (
                    <ToolsPanelItem
                        label={__('Focal point')}
                        isShownByDefault
                        hasValue={() => !!focalPoint}
                        onDeselect={() =>
                            setAttributes({ focalPoint: undefined })
                        }
                    >
                        <FocalPointPicker
                            label={__('Focal point')}
                            url={
                                useFeaturedImage && featuredImageURL
                                    ? featuredImageURL
                                    : mediaUrl
                            }
                            value={focalPoint}
                            onChange={(value?: FocalPoint) =>
                                setAttributes({ focalPoint: value })
                            }
                            onDragStart={imperativeFocalPointPreview}
                            onDrag={imperativeFocalPointPreview}
                        />
                    </ToolsPanelItem>
                )}
            {mediaType === 'image' && mediaUrl && !useFeaturedImage && (
                <ToolsPanelItem
                    label={__('Alternative text')}
                    isShownByDefault
                    hasValue={() => !!mediaAlt}
                    onDeselect={() => setAttributes({ mediaAlt: '' })}
                >
                    <TextareaControl
                        label={__('Alternative text')}
                        value={mediaAlt ?? ''}
                        onChange={onMediaAltChange}
                        help={
                            <>
                                <ExternalLink
                                    href={
                                        // translators: Localized tutorial, if one exists. W3C Web Accessibility Initiative link has list of existing translations.
                                        __(
                                            'https://www.w3.org/WAI/tutorials/images/decision-tree/'
                                        )
                                    }
                                >
                                    {__('Describe the purpose of the image.')}
                                </ExternalLink>
                                <br />
                                {__('Leave empty if decorative.')}
                            </>
                        }
                    />
                </ToolsPanelItem>
            )}
            {/*
             * Upstream renders a ResolutionTool here (from
             * `blockEditorPrivateApis` via `unlock`). That control is
             * intentionally omitted; see file-level comment.
             */}
        </ToolsPanel>
    );

    const blockProps = useBlockProps({
        className: classNames,
        style,
    });

    const innerBlocksProps = useInnerBlocksProps(
        { className: 'wp-block-media-text__content' },
        { template: TEMPLATE as unknown as unknown[], allowedBlocks }
    );

    const blockEditingMode = useBlockEditingMode();

    // Use a selector to read image settings for downstream image-size
    // bookkeeping; mirrors upstream's pattern of subscribing to
    // `getSettings()` so editor selections stay reactive.
    useSelect(
        (select) => {
            const { getSettings } = select(blockEditorStore) as {
                getSettings: () => { imageSizes?: unknown[] };
            };
            return getSettings().imageSizes;
        },
        []
    );

    return (
        <>
            <InspectorControls>
                {mediaTextGeneralSettings}
            </InspectorControls>
            <BlockControls group="block">
                {blockEditingMode === 'default' && (
                    <>
                        <BlockVerticalAlignmentControl
                            onChange={onVerticalAlignmentChange}
                            value={verticalAlignment}
                        />
                        <ToolbarButton
                            icon={pullLeft}
                            title={__('Show media on left')}
                            isActive={mediaPosition === 'left'}
                            onClick={() =>
                                setAttributes({ mediaPosition: 'left' })
                            }
                        />
                        <ToolbarButton
                            icon={pullRight}
                            title={__('Show media on right')}
                            isActive={mediaPosition === 'right'}
                            onClick={() =>
                                setAttributes({ mediaPosition: 'right' })
                            }
                        />
                    </>
                )}

                {mediaType === 'image' && !useFeaturedImage && (
                    <ImageURLInputUI
                        url={href || ''}
                        onChangeUrl={(props: Partial<MediaTextAttributes>) =>
                            setAttributes(props)
                        }
                        linkDestination={linkDestination}
                        mediaType={mediaType}
                        mediaUrl={image && image.source_url}
                        mediaLink={image && image.link}
                        linkTarget={linkTarget}
                        linkClass={linkClass}
                        rel={rel}
                    />
                )}
            </BlockControls>
            <div {...blockProps}>
                {mediaPosition === 'right' && (
                    <div {...innerBlocksProps} />
                )}
                <MediaContainer
                    className="wp-block-media-text__media"
                    onSelectMedia={onSelectMedia}
                    onWidthChange={onWidthChange}
                    commitWidthChange={commitWidthChange}
                    refMedia={
                        refMedia as Ref<HTMLImageElement | HTMLVideoElement>
                    }
                    enableResize={blockEditingMode === 'default'}
                    toggleUseFeaturedImage={toggleUseFeaturedImage}
                    focalPoint={focalPoint}
                    imageFill={imageFill}
                    isSelected={isSelected}
                    isStackedOnMobile={isStackedOnMobile}
                    mediaAlt={mediaAlt}
                    mediaId={mediaId}
                    mediaPosition={mediaPosition}
                    mediaType={mediaType}
                    mediaUrl={mediaUrl}
                    mediaWidth={resolvedMediaWidth}
                    useFeaturedImage={useFeaturedImage}
                    featuredImageURL={featuredImageURL ?? undefined}
                    featuredImageAlt={featuredImageAlt ?? undefined}
                />
                {mediaPosition !== 'right' && (
                    <div {...innerBlocksProps} />
                )}
            </div>
        </>
    );
}

export default MediaTextEdit;
