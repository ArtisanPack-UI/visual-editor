/**
 * Image — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/image/edit.js` (v9.43.0).
 * Behaviour parity is the goal for everything the saved markup depends on:
 * the `url`/`alt`/`caption`/`href`/`title`/`id`/`width`/`height`/`aspectRatio`/
 * `scale`/`focalPoint`/`sizeSlug`/`linkDestination`/`linkTarget`/`rel`/
 * `linkClass` attributes round-trip losslessly across editor sessions.
 *
 * Intentional divergences from upstream (documented in `upstream-state.json`
 * under `knownDivergences`):
 *
 *   - The blob-URL upload effect (`useUploadMediaFromBlobURL`) is omitted.
 *     Drag-and-dropped image files are not eagerly uploaded as soon as they
 *     hit the block; users upload via `MediaPlaceholder` / `MediaReplaceFlow`
 *     as usual.
 *   - The shared `Caption` component from `@wordpress/block-library/src/utils`
 *     is replaced by an inline `RichText` figcaption (inside
 *     `image-editor.tsx`) because the package's `exports` field does not
 *     expose `utils/caption`.
 *   - `useToolsPanelDropdownMenuProps` is omitted from the inspector
 *     `ToolsPanel`s for the same reason. The defaults are good enough.
 *   - The link-destination dropdown UI, the image cropper / image-editor
 *     popover, the lightbox toggle UI, and the `ResizableBox` are all
 *     omitted — they depend on private block-editor APIs unreachable through
 *     the package's `exports`. The corresponding attributes are still
 *     serializable and round-trip through transforms; consumers can set them
 *     via deserialization.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { isBlobURL, createBlobURL } from '@wordpress/blob';
import {
    BlockIcon,
    MediaPlaceholder,
    useBlockProps,
    useBlockEditingMode,
} from '@wordpress/block-editor';
import { useDispatch } from '@wordpress/data';
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { image as icon } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';

import Image from './image-editor';
import { useMaxWidthObserver } from './use-max-width-observer';
import { isValidFileType } from './utils';
import {
    ALLOWED_MEDIA_TYPES,
    DEFAULT_MEDIA_SIZE_SLUG,
} from './constants';

interface ImageEditAttributes {
    readonly blob?: string;
    readonly url?: string;
    readonly alt?: string;
    readonly caption?: string;
    readonly id?: number;
    readonly title?: string;
    readonly href?: string;
    readonly rel?: string;
    readonly linkClass?: string;
    readonly linkTarget?: string;
    readonly linkDestination?: string;
    readonly sizeSlug?: string;
    readonly width?: string;
    readonly height?: string;
    readonly aspectRatio?: string;
    readonly scale?: string;
    readonly focalPoint?: { x?: number; y?: number };
    readonly align?: string;
    readonly metadata?: Record<string, unknown>;
    readonly [key: string]: unknown;
}

interface MediaItem {
    readonly id?: number;
    readonly url?: string;
    readonly alt?: string;
    readonly caption?: string;
    readonly title?: string;
    readonly link?: string;
}

interface ImageEditProps {
    readonly attributes: ImageEditAttributes;
    readonly className?: string;
    readonly setAttributes: (next: Partial<ImageEditAttributes>) => void;
    readonly isSelected: boolean;
    readonly onReplace?: (block: unknown) => void;
    readonly insertBlocksAfter?: (block: unknown) => void;
    readonly clientId?: string;
    readonly context?: Record<string, unknown>;
}

export const isExternalImage = (
    id: number | undefined,
    url: string | undefined
): boolean => !!url && !id && !isBlobURL(url);

function ImageEdit({
    attributes,
    setAttributes,
    isSelected: isSingleSelected,
    className,
}: ImageEditProps): ReactElement {
    const { url = '', id, width, height, sizeSlug } = attributes;

    const [temporaryURL, setTemporaryURL] = useState<string | undefined>(
        attributes.blob
    );
    const containerRef = useRef<HTMLElement | null>(null);
    const [maxWidthObserver] = useMaxWidthObserver();
    const captionRef = useRef<string | undefined>(attributes.caption);

    useEffect(() => {
        captionRef.current = attributes.caption;
    }, [attributes.caption]);

    const blockEditingMode = useBlockEditingMode();
    const hasNonContentControls = blockEditingMode === 'default';

    const { createErrorNotice } = useDispatch(noticesStore);

    function onUploadError(message: string): void {
        createErrorNotice(message, { type: 'snackbar' });
        setTemporaryURL(undefined);
        setAttributes({
            id: undefined,
            url: undefined,
            blob: undefined,
        });
    }

    function onSelectImage(media: MediaItem | MediaItem[] | undefined): void {
        if (Array.isArray(media)) {
            // Multi-file selection is treated as a no-op in the fork. Upstream
            // would convert to a gallery; that path depends on private
            // block-editor APIs we don't have.
            const files = media.filter(
                (entry): entry is File => entry instanceof File
            );
            if (files.length && files.some((f) => !isValidFileType(f))) {
                createErrorNotice(
                    __('Only image files can be uploaded here.'),
                    { type: 'snackbar' }
                );
            }
            return;
        }

        if (!media || !media.url) {
            setAttributes({
                url: undefined,
                alt: undefined,
                id: undefined,
                title: undefined,
                caption: undefined,
                blob: undefined,
            });
            setTemporaryURL(undefined);
            return;
        }

        if (isBlobURL(media.url)) {
            setTemporaryURL(media.url);
            return;
        }

        const mediaAttributes: Partial<ImageEditAttributes> = {
            url: media.url,
            id: media.id,
            alt: media.alt,
            caption: media.caption,
            blob: undefined,
        };

        // Reset the dimension attributes if changing to a different image.
        const additionalAttributes: Partial<ImageEditAttributes> =
            !media.id || media.id !== id
                ? { sizeSlug: sizeSlug ?? DEFAULT_MEDIA_SIZE_SLUG }
                : {};

        setAttributes({
            ...mediaAttributes,
            ...additionalAttributes,
        });
        setTemporaryURL(undefined);
    }

    function onSelectURL(newURL: string): void {
        if (newURL !== url) {
            setAttributes({
                blob: undefined,
                url: newURL,
                id: undefined,
                sizeSlug: DEFAULT_MEDIA_SIZE_SLUG,
            });
            setTemporaryURL(undefined);
        }
    }

    function onFilesPreUpload(files: File[]): void {
        if (files.length === 1) {
            setTemporaryURL(createBlobURL(files[0]));
        }
    }

    const isExternal = isExternalImage(id, url);
    const src = isExternal ? url : undefined;

    const classes = clsx(className, {
        'is-transient': !!temporaryURL,
        'is-resized': !!width || !!height,
        [`size-${sizeSlug}`]: sizeSlug,
    });

    const blockProps = useBlockProps({
        ref: containerRef as unknown as React.Ref<HTMLDivElement>,
        className: classes,
    });

    if (!url) {
        return (
            <figure {...blockProps}>
                <MediaPlaceholder
                    icon={<BlockIcon icon={icon} />}
                    onSelect={onSelectImage}
                    onSelectURL={onSelectURL}
                    onFilesPreUpload={onFilesPreUpload}
                    onError={onUploadError}
                    accept="image/*"
                    allowedTypes={ALLOWED_MEDIA_TYPES as unknown as string[]}
                    value={{ id, src }}
                />
            </figure>
        );
    }

    return (
        <>
            <figure {...blockProps}>
                <Image
                    temporaryURL={temporaryURL}
                    attributes={attributes}
                    setAttributes={setAttributes}
                    isSingleSelected={isSingleSelected}
                    onSelectImage={onSelectImage}
                    onSelectURL={onSelectURL}
                    onUploadError={onUploadError}
                    hasNonContentControls={hasNonContentControls}
                />
            </figure>
            {isSingleSelected && maxWidthObserver}
        </>
    );
}

export default ImageEdit;
