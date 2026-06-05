/**
 * Media & Text — vendored media-container.
 *
 * Ported from `@wordpress/block-library/src/media-text/media-container.js`
 * (v9.43.0). Behaviour parity is the goal — the container drives the
 * editor-side selection, replacement, and resize interactions for the
 * media figure. Logic is byte-equivalent to upstream.
 */

import type { CSSProperties, ReactElement } from 'react';
import { forwardRef, type ForwardedRef, type Ref } from 'react';
import clsx from 'clsx';
import {
    Placeholder,
    ResizableBox,
    Spinner,
} from '@wordpress/components';
import {
    BlockControls,
    BlockIcon,
    MediaPlaceholder,
    MediaReplaceFlow,
    store as blockEditorStore,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useViewportMatch } from '@wordpress/compose';
import { useDispatch } from '@wordpress/data';
import { createBlobURL, isBlobURL } from '@wordpress/blob';
import { store as noticesStore } from '@wordpress/notices';
import { media as icon } from '@wordpress/icons';

import { imageFillStyles, type FocalPoint } from './image-fill';

const ALLOWED_MEDIA_TYPES = ['image', 'video'] as const;
const noop = (): null => null;

interface MediaSelection {
    readonly id?: number;
    readonly url?: string;
    readonly alt?: string;
    readonly link?: string;
    readonly type?: string;
    readonly media_type?: string;
    readonly [key: string]: unknown;
}

interface ResizableBoxContainerProps {
    isSelected: boolean;
    isStackedOnMobile?: boolean;
    [key: string]: unknown;
}

const ResizableBoxContainer = forwardRef<HTMLElement, ResizableBoxContainerProps>(
    ({ isSelected, isStackedOnMobile, ...props }, ref) => {
        const isMobile = useViewportMatch('small', '<');
        return (
            <ResizableBox
                ref={ref as Ref<HTMLElement>}
                showHandle={
                    isSelected && (!isMobile || !isStackedOnMobile)
                }
                {...(props as Record<string, unknown>)}
            />
        );
    }
);

interface ToolbarEditButtonProps {
    mediaId?: number;
    mediaUrl?: string;
    onSelectMedia: (media: MediaSelection | undefined) => void;
    toggleUseFeaturedImage?: () => void;
    useFeaturedImage?: boolean;
}

function ToolbarEditButton({
    mediaId,
    mediaUrl,
    onSelectMedia,
    toggleUseFeaturedImage,
    useFeaturedImage,
}: ToolbarEditButtonProps): ReactElement {
    return (
        <BlockControls group="other">
            <MediaReplaceFlow
                mediaId={mediaId}
                mediaURL={mediaUrl}
                allowedTypes={ALLOWED_MEDIA_TYPES as unknown as string[]}
                onSelect={onSelectMedia}
                onToggleFeaturedImage={toggleUseFeaturedImage}
                useFeaturedImage={useFeaturedImage}
                onReset={() => onSelectMedia(undefined)}
            />
        </BlockControls>
    );
}

interface PlaceholderContainerProps {
    className?: string;
    mediaUrl?: string;
    onSelectMedia: (media: MediaSelection | undefined) => void;
    toggleUseFeaturedImage?: () => void;
}

function PlaceholderContainer({
    className,
    mediaUrl,
    onSelectMedia,
    toggleUseFeaturedImage,
}: PlaceholderContainerProps): ReactElement {
    const { createErrorNotice } = useDispatch(noticesStore);

    const onUploadError = (message: string): void => {
        createErrorNotice(message, { type: 'snackbar' });
    };

    const onFilesPreUpload = (files: readonly File[]): void => {
        if (files.length === 1) {
            onSelectMedia({ url: createBlobURL(files[0]) });
        }
    };

    return (
        <MediaPlaceholder
            icon={<BlockIcon icon={icon} />}
            labels={{
                title: __('Media area'),
            }}
            className={className}
            onSelect={onSelectMedia}
            onToggleFeaturedImage={toggleUseFeaturedImage}
            allowedTypes={ALLOWED_MEDIA_TYPES as unknown as string[]}
            onFilesPreUpload={onFilesPreUpload}
            onError={onUploadError}
            disableMediaButtons={!!mediaUrl}
        />
    );
}

export interface MediaContainerProps {
    className?: string;
    commitWidthChange: (width: number) => void;
    focalPoint?: FocalPoint;
    imageFill?: boolean;
    isSelected: boolean;
    isStackedOnMobile?: boolean;
    mediaAlt?: string;
    mediaId?: number;
    mediaPosition?: string;
    mediaType?: string;
    mediaUrl?: string;
    mediaWidth: number;
    onSelectMedia: (media: MediaSelection | undefined) => void;
    onWidthChange: (width: number) => void;
    enableResize: boolean;
    toggleUseFeaturedImage?: () => void;
    useFeaturedImage?: boolean;
    featuredImageURL?: string;
    featuredImageAlt?: string;
    refMedia?: Ref<HTMLImageElement | HTMLVideoElement>;
}

function MediaContainer(
    props: MediaContainerProps,
    ref: ForwardedRef<HTMLElement>
): ReactElement {
    const {
        className,
        commitWidthChange,
        focalPoint,
        imageFill,
        isSelected,
        isStackedOnMobile,
        mediaAlt,
        mediaId,
        mediaPosition,
        mediaType,
        mediaUrl,
        mediaWidth,
        onSelectMedia,
        onWidthChange,
        enableResize,
        toggleUseFeaturedImage,
        useFeaturedImage,
        featuredImageURL,
        featuredImageAlt,
        refMedia,
    } = props;

    const isTemporaryMedia = !mediaId && isBlobURL(mediaUrl ?? '');

    const { toggleSelection } = useDispatch(blockEditorStore);

    if (mediaUrl || featuredImageURL || useFeaturedImage) {
        const onResizeStart = (): void => {
            toggleSelection(false);
        };
        const onResize = (
            _event: unknown,
            _direction: unknown,
            elt: HTMLElement
        ): void => {
            onWidthChange(parseInt(elt.style.width, 10));
        };
        const onResizeStop = (
            _event: unknown,
            _direction: unknown,
            elt: HTMLElement
        ): void => {
            toggleSelection(true);
            commitWidthChange(parseInt(elt.style.width, 10));
        };
        const enablePositions = {
            right: enableResize && mediaPosition === 'left',
            left: enableResize && mediaPosition === 'right',
        };

        const positionStyles: CSSProperties =
            mediaType === 'image' && imageFill
                ? (imageFillStyles(
                      mediaUrl || featuredImageURL,
                      focalPoint
                  ) as CSSProperties)
                : {};

        const mediaTypeRenderers: Record<string, () => ReactElement | null> = {
            image: () =>
                useFeaturedImage && featuredImageURL ? (
                    <img
                        ref={refMedia as Ref<HTMLImageElement>}
                        src={featuredImageURL}
                        alt={featuredImageAlt}
                        style={positionStyles}
                    />
                ) : mediaUrl ? (
                    <img
                        ref={refMedia as Ref<HTMLImageElement>}
                        src={mediaUrl}
                        alt={mediaAlt}
                        style={positionStyles}
                    />
                ) : null,
            video: () => (
                <video
                    controls
                    ref={refMedia as Ref<HTMLVideoElement>}
                    src={mediaUrl}
                />
            ),
        };

        return (
            <ResizableBoxContainer
                as="figure"
                className={clsx(
                    className,
                    'editor-media-container__resizer',
                    { 'is-transient': isTemporaryMedia }
                )}
                size={{ width: mediaWidth + '%' }}
                minWidth="10%"
                maxWidth="100%"
                enable={enablePositions}
                onResizeStart={onResizeStart}
                onResize={onResize}
                onResizeStop={onResizeStop}
                axis="x"
                isSelected={isSelected}
                isStackedOnMobile={isStackedOnMobile}
                ref={ref}
            >
                <ToolbarEditButton
                    onSelectMedia={onSelectMedia}
                    mediaUrl={
                        useFeaturedImage && featuredImageURL
                            ? featuredImageURL
                            : mediaUrl
                    }
                    mediaId={mediaId}
                    toggleUseFeaturedImage={toggleUseFeaturedImage}
                    useFeaturedImage={useFeaturedImage}
                />
                {(mediaTypeRenderers[mediaType ?? ''] || noop)()}
                {isTemporaryMedia && <Spinner />}
                {!useFeaturedImage && <PlaceholderContainer {...props} />}
                {!featuredImageURL && useFeaturedImage && (
                    <Placeholder
                        className="wp-block-media-text--placeholder-image"
                        style={positionStyles}
                        withIllustration
                    />
                )}
            </ResizableBoxContainer>
        );
    }

    return <PlaceholderContainer {...props} />;
}

export default forwardRef(MediaContainer);
