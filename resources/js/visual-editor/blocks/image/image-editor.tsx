/**
 * Image — vendored editor component.
 *
 * A simplified TypeScript port of `@wordpress/block-library/src/image/image.js`
 * (v9.43.0). Upstream's `image.js` is ~1300 LOC of toolbar/inspector/resizer
 * code that depends heavily on block-library internals (`unlock`, the shared
 * `Caption` component, `useToolsPanelDropdownMenuProps`, the `embed/util`
 * `createUpgradedEmbedBlock` helper, the private `DimensionsTool`/
 * `ResolutionTool`/`mediaEditKey` APIs, etc.) — none of which are reachable
 * from outside `@wordpress/block-library` because of its `exports` field.
 *
 * The fork keeps a behaviour-equivalent render path:
 *
 *   - figure → optional anchor → img → optional figcaption.
 *   - `MediaReplaceFlow` toolbar button to swap the media or paste a URL.
 *   - Inspector `ToolsPanel` with the alt-text + title controls.
 *
 * Anything that depended on the private APIs above (image-editor cropping,
 * the lightbox toggle UI, the link-destination dropdown UI, the resizable
 * box, the focal-point picker, etc.) is documented as a divergence in
 * `upstream-state.json#knownDivergences`. The block's serialized output is
 * still produced by `save.tsx`, so this divergence is editor-only.
 */

import type { ChangeEvent, ReactElement, ReactNode } from 'react';
import clsx from 'clsx';
import {
    TextareaControl,
    TextControl,
    __experimentalToolsPanel as ToolsPanel,
    __experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import {
    BlockControls,
    InspectorControls,
    MediaReplaceFlow,
    RichText,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import { ALLOWED_MEDIA_TYPES } from './constants';

interface ImageEditorAttributes {
    readonly id?: number;
    readonly url?: string;
    readonly alt?: string;
    readonly title?: string;
    readonly caption?: string;
    readonly href?: string;
    readonly rel?: string;
    readonly linkClass?: string;
    readonly linkTarget?: string;
    readonly sizeSlug?: string;
    readonly width?: string;
    readonly height?: string;
    readonly aspectRatio?: string;
    readonly scale?: string;
    readonly focalPoint?: { x?: number; y?: number };
    readonly align?: string;
    readonly blob?: string;
    readonly [key: string]: unknown;
}

interface MediaItem {
    readonly id?: number;
    readonly url?: string;
    readonly alt?: string;
    readonly caption?: string;
    readonly title?: string;
}

interface ImageEditorProps {
    readonly temporaryURL?: string;
    readonly isSideloading?: boolean;
    readonly attributes: ImageEditorAttributes;
    readonly setAttributes: (next: Partial<ImageEditorAttributes>) => void;
    readonly isSingleSelected: boolean;
    readonly onSelectImage: (media: MediaItem | MediaItem[] | undefined) => void;
    readonly onSelectURL: (newURL: string) => void;
    readonly onUploadError: (message: string) => void;
    readonly hasNonContentControls?: boolean;
}

function ImageWrapper({
    href,
    children,
}: {
    href?: string;
    children: ReactNode;
}): ReactElement {
    if (!href) {
        return <>{children}</>;
    }
    return (
        <a
            href={href}
            onClick={(event) => event.preventDefault()}
            aria-disabled
            style={{
                pointerEvents: 'none',
                cursor: 'default',
                display: 'inline',
            }}
        >
            {children}
        </a>
    );
}

export default function Image(props: ImageEditorProps): ReactElement {
    const {
        attributes,
        setAttributes,
        isSingleSelected,
        onSelectImage,
        onSelectURL,
        onUploadError,
        hasNonContentControls = true,
    } = props;

    const {
        url,
        alt,
        caption,
        id,
        title,
        width,
        height,
        aspectRatio,
        scale,
    } = attributes;

    const imageClasses = clsx({
        [`wp-image-${id}`]: !!id,
    });

    const image = (
        <img
            src={url}
            alt={alt}
            className={imageClasses || undefined}
            style={{
                aspectRatio,
                objectFit: scale,
                width,
                height,
            }}
            title={title}
        />
    );

    return (
        <>
            {isSingleSelected && (
                <BlockControls group="other">
                    <MediaReplaceFlow
                        mediaId={id}
                        mediaURL={url}
                        allowedTypes={ALLOWED_MEDIA_TYPES as unknown as string[]}
                        accept="image/*"
                        onSelect={onSelectImage}
                        onSelectURL={onSelectURL}
                        onError={onUploadError}
                        onReset={() => onSelectImage(undefined)}
                        variant="toolbar"
                    />
                </BlockControls>
            )}
            <InspectorControls>
                <ToolsPanel
                    label={__('Settings')}
                    resetAll={() =>
                        setAttributes({
                            alt: '',
                            title: undefined,
                        })
                    }
                >
                    <ToolsPanelItem
                        label={__('Alternative text')}
                        isShownByDefault
                        hasValue={() => !!alt}
                        onDeselect={() => setAttributes({ alt: '' })}
                    >
                        <TextareaControl
                            __nextHasNoMarginBottom
                            label={__('Alternative text')}
                            value={alt ?? ''}
                            onChange={(value: string) =>
                                setAttributes({ alt: value })
                            }
                            help={__(
                                'Describe the purpose of the image. Leave empty if decorative.'
                            )}
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        label={__('Title attribute')}
                        hasValue={() => !!title}
                        onDeselect={() => setAttributes({ title: undefined })}
                    >
                        <TextControl
                            __nextHasNoMarginBottom
                            __next40pxDefaultSize
                            label={__('Title attribute')}
                            value={title ?? ''}
                            onChange={(value: string) =>
                                setAttributes({
                                    title: value || undefined,
                                })
                            }
                        />
                    </ToolsPanelItem>
                </ToolsPanel>
            </InspectorControls>
            <ImageWrapper href={attributes.href}>{image}</ImageWrapper>
            {(isSingleSelected && hasNonContentControls) ||
            !RichText.isEmpty(caption ?? '') ? (
                <RichText
                    identifier="caption"
                    tagName="figcaption"
                    aria-label={__('Image caption text')}
                    placeholder={__('Add caption')}
                    value={caption ?? ''}
                    onChange={(value: string) =>
                        setAttributes({ caption: value } as Partial<ImageEditorAttributes> & {
                            caption: string;
                        })
                    }
                    inlineToolbar
                />
            ) : null}
        </>
    );
}

// Suppress unused warnings for declared but currently unused destructures (kept
// for future feature parity with upstream image.js).
export type { ChangeEvent, ImageEditorProps, ImageEditorAttributes, MediaItem };
