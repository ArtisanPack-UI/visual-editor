/**
 * Cover — editor-side render (entry).
 *
 * Ported from `@wordpress/block-library/src/cover/edit/index.js`
 * (v9.43.0). Behaviour parity is the goal for everything the saved markup
 * depends on: the cover attributes round-trip losslessly across editor
 * sessions. The block adapts upstream block-library internals (documented
 * in `upstream-state.json` under `knownDivergences`) by inlining
 * replacements via the sibling files in this `edit/` directory.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';

import { useEntityProp, store as coreStore } from '@wordpress/core-data';
import { useEffect, useMemo, useRef } from '@wordpress/element';
import { Placeholder, Spinner } from '@wordpress/components';
import {
    withColors,
    useBlockProps,
    useSettings,
    useInnerBlocksProps,
    // eslint-disable-next-line camelcase
    __experimentalColorGradientControl as ColorGradientControl,
    // eslint-disable-next-line camelcase
    __experimentalUseGradient as useGradient,
    store as blockEditorStore,
    useBlockEditingMode,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { isBlobURL } from '@wordpress/blob';
import { store as noticesStore } from '@wordpress/notices';

import {
    attributesFromMedia,
    IMAGE_BACKGROUND_TYPE,
    VIDEO_BACKGROUND_TYPE,
    EMBED_VIDEO_BACKGROUND_TYPE,
    dimRatioToClass,
    isContentPositionCenter,
    getPositionClassName,
    mediaPosition,
} from '../shared';
import CoverInspectorControls from './inspector-controls';
import CoverBlockControls from './block-controls';
import CoverPlaceholder from './cover-placeholder';
import ResizableCoverPopover from './resizable-cover-popover';
import {
    getMediaColor,
    compositeIsDark,
    DEFAULT_BACKGROUND_COLOR,
    DEFAULT_OVERLAY_COLOR,
} from './color-utils';
import { DEFAULT_MEDIA_SIZE_SLUG } from '../constants';
import { getIframeSrc, getBackgroundVideoSrc } from '../embed-video-utils';

interface FocalPoint {
    x: number;
    y: number;
}

interface CoverAttributes {
    contentPosition?: string;
    id?: number;
    url?: string;
    backgroundType?: string;
    useFeaturedImage?: boolean;
    dimRatio?: number;
    focalPoint?: FocalPoint;
    hasParallax?: boolean;
    isDark?: boolean;
    isRepeated?: boolean;
    minHeight?: number;
    minHeightUnit?: string;
    alt?: string;
    allowedBlocks?: string[];
    templateLock?: string | boolean;
    tagName?: string;
    isUserOverlayColor?: boolean;
    sizeSlug?: string;
    poster?: string;
    style?: {
        dimensions?: { aspectRatio?: string };
    } & Record<string, unknown>;
    [key: string]: unknown;
}

interface CoverEditProps {
    attributes: CoverAttributes;
    clientId: string;
    isSelected: boolean;
    overlayColor: { color?: string; class?: string };
    setAttributes: (next: Partial<CoverAttributes>) => void;
    setOverlayColor: (color: string | undefined) => void;
    toggleSelection: (enabled: boolean) => void;
    context?: { postId?: number; postType?: string };
}

function getInnerBlocksTemplate(
    attributes: Record<string, unknown>
): unknown[][] {
    return [
        [
            'core/paragraph',
            {
                style: {
                    typography: {
                        textAlign: 'center',
                    },
                },
                placeholder: __('Write title…'),
                ...attributes,
            },
        ],
    ];
}

const isTemporaryMedia = (
    id: number | undefined,
    url: string | undefined
): boolean => !id && isBlobURL(url);

function CoverEdit({
    attributes,
    clientId,
    isSelected,
    overlayColor,
    setAttributes,
    setOverlayColor,
    toggleSelection,
    context,
}: CoverEditProps): ReactElement {
    const postId = context?.postId;
    const postType = context?.postType;

    const {
        contentPosition,
        id,
        url: originalUrl,
        backgroundType: originalBackgroundType,
        useFeaturedImage,
        dimRatio,
        focalPoint,
        hasParallax,
        isDark,
        isRepeated,
        minHeight,
        minHeightUnit,
        alt,
        allowedBlocks,
        templateLock,
        tagName: tagNameAttr,
        isUserOverlayColor,
        sizeSlug,
        poster,
    } = attributes;
    const TagName = (tagNameAttr ?? 'div') as keyof JSX.IntrinsicElements;

    const [featuredImage] = useEntityProp(
        'postType',
        postType ?? '',
        'featured_media',
        postId
    ) as [number | undefined];

    const { getSettings } = useSelect(blockEditorStore, []);
    const { __unstableMarkNextChangeAsNotPersistent } = useDispatch(
        blockEditorStore
    ) as {
        __unstableMarkNextChangeAsNotPersistent: () => void;
    };

    const { media } = useSelect(
        (select) => {
            return {
                media:
                    featuredImage && useFeaturedImage
                        ? (
                              select(coreStore) as unknown as {
                                  getEntityRecord: (
                                      kind: string,
                                      name: string,
                                      key: number,
                                      query?: { context?: string }
                                  ) => unknown;
                              }
                          ).getEntityRecord(
                              'postType',
                              'attachment',
                              featuredImage,
                              { context: 'view' }
                          )
                        : undefined,
            };
        },
        [featuredImage, useFeaturedImage]
    ) as {
        media?: {
            source_url?: string;
            media_details?: {
                sizes?: Record<string, { source_url: string }>;
            };
        };
    };

    const mediaUrl =
        media?.media_details?.sizes?.[sizeSlug ?? '']?.source_url ??
        media?.source_url;

    useEffect(() => {
        (async () => {
            if (!useFeaturedImage) {
                return;
            }

            const averageBackgroundColor = await getMediaColor(mediaUrl);

            let newOverlayColor = overlayColor.color;
            if (!isUserOverlayColor) {
                newOverlayColor = averageBackgroundColor;
                __unstableMarkNextChangeAsNotPersistent();
                setOverlayColor(newOverlayColor);
            }

            const newIsDark = compositeIsDark(
                dimRatio,
                newOverlayColor,
                averageBackgroundColor
            );
            __unstableMarkNextChangeAsNotPersistent();
            setAttributes({
                isDark: newIsDark,
                isUserOverlayColor: isUserOverlayColor || false,
            });
        })();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [mediaUrl]);

    const url = useFeaturedImage
        ? mediaUrl
        : originalUrl?.replaceAll('&amp;', '&');
    const backgroundType = useFeaturedImage
        ? IMAGE_BACKGROUND_TYPE
        : originalBackgroundType;

    const { createErrorNotice } = useDispatch(noticesStore) as {
        createErrorNotice: (
            message: string,
            options?: { type?: string }
        ) => void;
    };
    const { gradientClass, gradientValue, setGradient } = useGradient() as {
        gradientClass?: string;
        gradientValue?: string;
        setGradient: (next: string | undefined) => void;
    };

    const onSelectMedia = async (
        newMedia:
            | {
                  type?: string;
                  media_type?: string;
                  url?: string;
                  id?: number;
                  sizes?: Record<string, { url?: string }>;
                  media_details?: {
                      sizes?: Record<string, { source_url: string }>;
                  };
              }
            | undefined
    ): Promise<void> => {
        const mediaAttributes = attributesFromMedia(newMedia);
        if (!mediaAttributes) {
            return;
        }
        const isImage = [newMedia?.type, newMedia?.media_type].includes(
            IMAGE_BACKGROUND_TYPE
        );

        const averageBackgroundColor = await getMediaColor(
            isImage ? newMedia?.url : undefined
        );

        let newOverlayColor = overlayColor.color;
        if (!isUserOverlayColor) {
            newOverlayColor = averageBackgroundColor;
            setOverlayColor(newOverlayColor);
            __unstableMarkNextChangeAsNotPersistent();
        }

        const newDimRatio =
            originalUrl === undefined && dimRatio === 100 ? 50 : dimRatio;

        const newIsDark = compositeIsDark(
            newDimRatio,
            newOverlayColor,
            averageBackgroundColor
        );

        if (isImage && mediaAttributes?.id) {
            const { imageDefaultSize } = getSettings() as {
                imageDefaultSize?: string;
            };

            if (
                sizeSlug &&
                (newMedia?.sizes?.[sizeSlug] ||
                    newMedia?.media_details?.sizes?.[sizeSlug])
            ) {
                (mediaAttributes as { sizeSlug?: string }).sizeSlug = sizeSlug;
                mediaAttributes.url =
                    newMedia?.sizes?.[sizeSlug]?.url ||
                    newMedia?.media_details?.sizes?.[sizeSlug]?.source_url;
            } else if (
                imageDefaultSize &&
                (newMedia?.sizes?.[imageDefaultSize] ||
                    newMedia?.media_details?.sizes?.[imageDefaultSize])
            ) {
                (mediaAttributes as { sizeSlug?: string }).sizeSlug =
                    imageDefaultSize;
                mediaAttributes.url =
                    newMedia?.sizes?.[imageDefaultSize]?.url ||
                    newMedia?.media_details?.sizes?.[imageDefaultSize]
                        ?.source_url;
            } else {
                (mediaAttributes as { sizeSlug?: string }).sizeSlug =
                    DEFAULT_MEDIA_SIZE_SLUG;
            }
        }

        setAttributes({
            ...mediaAttributes,
            focalPoint: undefined,
            useFeaturedImage: undefined,
            dimRatio: newDimRatio,
            isDark: newIsDark,
            isUserOverlayColor: isUserOverlayColor || false,
        });
    };

    const onClearMedia = (): void => {
        let newOverlayColor = overlayColor.color;
        if (!isUserOverlayColor) {
            newOverlayColor = DEFAULT_OVERLAY_COLOR;
            setOverlayColor(undefined);
            __unstableMarkNextChangeAsNotPersistent();
        }

        const newIsDark = compositeIsDark(
            dimRatio,
            newOverlayColor,
            DEFAULT_BACKGROUND_COLOR
        );

        setAttributes({
            url: undefined,
            id: undefined,
            backgroundType: undefined,
            focalPoint: undefined,
            hasParallax: undefined,
            isRepeated: undefined,
            useFeaturedImage: undefined,
            isDark: newIsDark,
        });
    };

    const onSetOverlayColor = async (
        newOverlayColor: string | undefined
    ): Promise<void> => {
        const averageBackgroundColor = await getMediaColor(url);
        const newIsDark = compositeIsDark(
            dimRatio,
            newOverlayColor,
            averageBackgroundColor
        );

        setOverlayColor(newOverlayColor);
        __unstableMarkNextChangeAsNotPersistent();

        setAttributes({
            isUserOverlayColor: true,
            isDark: newIsDark,
        });
    };

    const onUpdateDimRatio = async (newDimRatio: number): Promise<void> => {
        const averageBackgroundColor = await getMediaColor(url);
        const newIsDark = compositeIsDark(
            newDimRatio,
            overlayColor.color,
            averageBackgroundColor
        );

        setAttributes({
            dimRatio: newDimRatio,
            isDark: newIsDark,
        });
    };

    const onUploadError = (message: string): void => {
        createErrorNotice(message, { type: 'snackbar' });
    };

    const onSelectEmbedUrl = (embedUrl: string): void => {
        const newDimRatio =
            originalUrl === undefined && dimRatio === 100 ? 50 : dimRatio;

        setAttributes({
            url: embedUrl,
            backgroundType: EMBED_VIDEO_BACKGROUND_TYPE,
            dimRatio: newDimRatio,
            id: undefined,
            focalPoint: undefined,
            hasParallax: undefined,
            isRepeated: undefined,
            useFeaturedImage: undefined,
        });
    };

    const { embedPreview, isFetchingEmbed } = useSelect(
        (select) => {
            if (
                backgroundType !== EMBED_VIDEO_BACKGROUND_TYPE ||
                !url
            ) {
                return {
                    embedPreview: undefined,
                    isFetchingEmbed: false,
                };
            }

            const store = select(coreStore) as unknown as {
                getEmbedPreview: (url: string) => unknown;
                isRequestingEmbedPreview: (url: string) => boolean;
            };

            return {
                embedPreview: store.getEmbedPreview(url),
                isFetchingEmbed: store.isRequestingEmbedPreview(url),
            };
        },
        [url, backgroundType]
    ) as {
        embedPreview?: { html?: string };
        isFetchingEmbed: boolean;
    };

    const embedSrc = useMemo(() => {
        if (
            backgroundType !== EMBED_VIDEO_BACKGROUND_TYPE ||
            !embedPreview?.html
        ) {
            return null;
        }

        const iframeSrc = getIframeSrc(embedPreview.html);
        if (!iframeSrc) {
            return null;
        }

        return getBackgroundVideoSrc(iframeSrc);
    }, [embedPreview, backgroundType]);

    const isUploadingMedia = isTemporaryMedia(id, url);

    const isImageBackground = IMAGE_BACKGROUND_TYPE === backgroundType;
    const isVideoBackground = VIDEO_BACKGROUND_TYPE === backgroundType;
    const isEmbedVideoBackground =
        EMBED_VIDEO_BACKGROUND_TYPE === backgroundType;

    const blockEditingMode = useBlockEditingMode();
    const hasNonContentControls = blockEditingMode === 'default';

    const minHeightWithUnit =
        minHeight && minHeightUnit
            ? `${minHeight}${minHeightUnit}`
            : minHeight;

    const isImgElement = !(hasParallax || isRepeated);

    const style: React.CSSProperties = {
        minHeight: minHeightWithUnit || undefined,
    };

    const backgroundImage = url ? `url(${url})` : undefined;
    const backgroundPosition = mediaPosition(focalPoint);

    const bgStyle: React.CSSProperties = {
        backgroundColor: overlayColor.color,
    };
    const mediaStyle: React.CSSProperties = {
        objectPosition:
            focalPoint && isImgElement
                ? mediaPosition(focalPoint)
                : undefined,
    };

    const hasBackground = !!(url || overlayColor.color || gradientValue);

    const hasInnerBlocks = useSelect(
        (select) =>
            ((
                select(blockEditorStore) as unknown as {
                    getBlock: (clientId: string) => {
                        innerBlocks?: unknown[];
                    } | null;
                }
            ).getBlock(clientId)?.innerBlocks?.length ?? 0) > 0,
        [clientId]
    ) as boolean;

    const ref = useRef<HTMLElement>(null);
    const blockProps = useBlockProps({
        ref: ref as unknown as React.RefObject<HTMLDivElement>,
    });

    const [fontSizes] = useSettings('typography.fontSizes') as [
        { slug: string }[] | undefined,
    ];
    const hasFontSizes = (fontSizes?.length ?? 0) > 0;
    const innerBlocksTemplate = getInnerBlocksTemplate({
        fontSize: hasFontSizes ? 'large' : undefined,
    });

    const innerBlocksProps = useInnerBlocksProps(
        {
            className: 'wp-block-cover__inner-container',
        },
        {
            template: !hasInnerBlocks ? innerBlocksTemplate : undefined,
            templateInsertUpdatesSelection: true,
            allowedBlocks,
            templateLock,
            dropZoneElement: ref.current,
        }
    );

    const mediaElement = useRef<HTMLElement>(null);
    const currentSettings = {
        isVideoBackground,
        isImageBackground,
        mediaElement,
        hasInnerBlocks,
        url,
        isImgElement,
        overlayColor,
    };

    const toggleUseFeaturedImage = async (): Promise<void> => {
        const newUseFeaturedImage = !useFeaturedImage;

        const averageBackgroundColor = newUseFeaturedImage
            ? await getMediaColor(mediaUrl)
            : DEFAULT_BACKGROUND_COLOR;

        const newOverlayColor = !isUserOverlayColor
            ? averageBackgroundColor
            : overlayColor.color;

        if (!isUserOverlayColor) {
            if (newUseFeaturedImage) {
                setOverlayColor(newOverlayColor);
            } else {
                setOverlayColor(undefined);
            }
            __unstableMarkNextChangeAsNotPersistent();
        }

        const newDimRatio = dimRatio === 100 ? 50 : dimRatio;
        const newIsDark = compositeIsDark(
            newDimRatio,
            newOverlayColor,
            averageBackgroundColor
        );

        setAttributes({
            id: undefined,
            url: undefined,
            useFeaturedImage: newUseFeaturedImage,
            dimRatio: newDimRatio,
            backgroundType: newUseFeaturedImage
                ? IMAGE_BACKGROUND_TYPE
                : undefined,
            isDark: newIsDark,
        });
    };

    const blockControls = (
        <CoverBlockControls
            attributes={attributes}
            setAttributes={setAttributes}
            onSelectMedia={onSelectMedia}
            onSelectEmbedUrl={onSelectEmbedUrl}
            currentSettings={currentSettings}
            toggleUseFeaturedImage={toggleUseFeaturedImage}
            onClearMedia={onClearMedia}
            blockEditingMode={blockEditingMode}
        />
    );

    const inspectorControls = (
        <CoverInspectorControls
            attributes={attributes}
            setAttributes={setAttributes}
            clientId={clientId}
            setOverlayColor={onSetOverlayColor}
            coverRef={ref as { current: HTMLElement | null }}
            currentSettings={currentSettings}
            toggleUseFeaturedImage={toggleUseFeaturedImage}
            updateDimRatio={onUpdateDimRatio}
            onClearMedia={onClearMedia}
            featuredImage={media}
        />
    );

    const resizableCoverProps = {
        className: 'block-library-cover__resize-container',
        clientId,
        minHeight: minHeightWithUnit,
        onResizeStart: () => {
            setAttributes({ minHeightUnit: 'px' });
            toggleSelection(false);
        },
        onResize: (value: number) => {
            setAttributes({ minHeight: value });
        },
        onResizeStop: (newMinHeight: number) => {
            toggleSelection(true);
            setAttributes({ minHeight: newMinHeight });
        },
        showHandle: !attributes.style?.dimensions?.aspectRatio,
        size: {
            height:
                minHeightUnit === 'px' && minHeight ? minHeight : 'auto',
            width: 'auto',
        },
    };

    if (!useFeaturedImage && !hasInnerBlocks && !hasBackground) {
        return (
            <>
                {blockControls}
                {inspectorControls}
                {hasNonContentControls && isSelected && (
                    <ResizableCoverPopover {...resizableCoverProps} />
                )}
                <TagName
                    {...(blockProps as Record<string, unknown>)}
                    className={clsx(
                        'is-placeholder',
                        (blockProps as { className?: string }).className
                    )}
                    style={{
                        ...((blockProps as { style?: React.CSSProperties })
                            .style ?? {}),
                        minHeight: minHeightWithUnit || undefined,
                    }}
                >
                    <CoverPlaceholder
                        onSelectMedia={
                            onSelectMedia as unknown as (media: {
                                url: string;
                            }) => void
                        }
                        onError={onUploadError}
                        toggleUseFeaturedImage={toggleUseFeaturedImage}
                    >
                        <div className="wp-block-cover__placeholder-background-options">
                            { /* #490 — surface Color | Gradient tabs here so the
                                 placeholder matches the full overlay picker
                                 in `inspector-controls.tsx`. Same control,
                                 same UX expectation across the editor. */ }
                            <ColorGradientControl
                                label={__('Overlay')}
                                showTitle={false}
                                colorValue={overlayColor.color}
                                gradientValue={gradientValue}
                                onColorChange={onSetOverlayColor}
                                onGradientChange={setGradient}
                                clearable={false}
                                enableAlpha={false}
                                disableCustomColors={true}
                                disableCustomGradients={false}
                                __experimentalIsRenderedInSidebar
                            />
                        </div>
                    </CoverPlaceholder>
                </TagName>
            </>
        );
    }

    const classes = clsx(
        {
            'is-dark-theme': isDark,
            'is-light': !isDark,
            'is-transient': isUploadingMedia,
            'has-parallax': hasParallax,
            'is-repeated': isRepeated,
            'has-custom-content-position':
                !isContentPositionCenter(contentPosition),
        },
        getPositionClassName(contentPosition)
    );

    const showOverlay =
        url || !useFeaturedImage || (useFeaturedImage && !url);

    return (
        <>
            {blockControls}
            {inspectorControls}
            <TagName
                {...(blockProps as Record<string, unknown>)}
                className={clsx(
                    classes,
                    (blockProps as { className?: string }).className
                )}
                style={{
                    ...style,
                    ...((blockProps as { style?: React.CSSProperties })
                        .style ?? {}),
                }}
                data-url={url}
            >
                {!url && useFeaturedImage && (
                    <Placeholder
                        className="wp-block-cover__image--placeholder-image"
                        withIllustration
                    />
                )}

                {url &&
                    isImageBackground &&
                    (isImgElement ? (
                        <img
                            ref={
                                mediaElement as unknown as React.RefObject<HTMLImageElement>
                            }
                            className="wp-block-cover__image-background"
                            alt={alt}
                            src={url}
                            style={mediaStyle}
                        />
                    ) : (
                        <div
                            ref={
                                mediaElement as unknown as React.RefObject<HTMLDivElement>
                            }
                            role={alt ? 'img' : undefined}
                            aria-label={alt ? alt : undefined}
                            className={clsx(
                                classes,
                                'wp-block-cover__image-background'
                            )}
                            style={{ backgroundImage, backgroundPosition }}
                        />
                    ))}
                {url && isVideoBackground && (
                    <video
                        ref={
                            mediaElement as unknown as React.RefObject<HTMLVideoElement>
                        }
                        className="wp-block-cover__video-background"
                        autoPlay
                        muted
                        loop
                        src={url}
                        poster={poster}
                        style={mediaStyle}
                    />
                )}
                {isEmbedVideoBackground && embedSrc && (
                    <div
                        ref={
                            mediaElement as unknown as React.RefObject<HTMLDivElement>
                        }
                        className="wp-block-cover__video-background wp-block-cover__embed-background"
                        style={mediaStyle}
                    >
                        <iframe
                            src={embedSrc}
                            title="Background video"
                            frameBorder="0"
                            allow="autoplay; fullscreen"
                        />
                    </div>
                )}
                {isEmbedVideoBackground &&
                    !embedSrc &&
                    isFetchingEmbed && <Spinner />}

                {showOverlay && (
                    <span
                        aria-hidden="true"
                        className={clsx(
                            'wp-block-cover__background',
                            dimRatioToClass(dimRatio),
                            {
                                [overlayColor.class as string]:
                                    !!overlayColor.class,
                                'has-background-dim':
                                    dimRatio !== undefined,
                                'wp-block-cover__gradient-background':
                                    !!url &&
                                    !!gradientValue &&
                                    dimRatio !== 0,
                                'has-background-gradient': !!gradientValue,
                                [gradientClass as string]: !!gradientClass,
                            }
                        )}
                        style={{
                            backgroundImage: gradientValue,
                            ...bgStyle,
                        }}
                    />
                )}

                {isUploadingMedia && <Spinner />}

                <CoverPlaceholder
                    disableMediaButtons
                    onSelectMedia={
                        onSelectMedia as unknown as (media: {
                            url: string;
                        }) => void
                    }
                    onError={onUploadError}
                    toggleUseFeaturedImage={toggleUseFeaturedImage}
                />
                <div {...(innerBlocksProps as Record<string, unknown>)} />
            </TagName>
            {hasNonContentControls && isSelected && (
                <ResizableCoverPopover {...resizableCoverProps} />
            )}
        </>
    );
}

export default withColors({ overlayColor: 'background-color' })(
    CoverEdit
) as unknown as (props: CoverEditProps) => ReactElement;
