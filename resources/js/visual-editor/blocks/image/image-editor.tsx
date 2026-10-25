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
 *   - Toolbar link control + inspector "Link to" destination, link CSS
 *     class and link rel (#1022), built on the public `LinkControl`
 *     instead of upstream's private `ImageURLInputUI`.
 *   - Inspector `ToolsPanel` with the alt-text + title controls.
 *
 * Anything that depended on the private APIs above (image-editor cropping,
 * the lightbox toggle UI, the focal-point picker, etc.) is documented as a divergence in
 * `upstream-state.json#knownDivergences`. The block's serialized output is
 * still produced by `save.tsx`, so this divergence is editor-only.
 */

import type { ChangeEvent, ReactElement, ReactNode } from 'react';
import clsx from 'clsx';
import {
    ResizableBox,
    SelectControl,
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
import { TEXT_DOMAIN } from '../../vendor/i18n';

import LinkToolbarControl, { type LinkToolbarValue } from '../_shared/link-toolbar-control';
import {
    ALLOWED_MEDIA_TYPES,
    LINK_DESTINATION_CUSTOM,
    LINK_DESTINATION_MEDIA,
    LINK_DESTINATION_NONE,
    MIN_SIZE,
    WIDTH_PRESETS,
    WIDTH_PRESET_SNAP_PCT,
    type WidthUnit,
} from './constants';
import WidthControl, { composeWidth } from './width-control';

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
    readonly linkDestination?: string;
    readonly sizeSlug?: string;
    readonly width?: string;
    readonly widthUnit?: WidthUnit;
    readonly keepAspectRatio?: boolean;
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

type LinkDestination =
    | typeof LINK_DESTINATION_NONE
    | typeof LINK_DESTINATION_MEDIA
    | typeof LINK_DESTINATION_CUSTOM;

/**
 * Resolve the effective link destination, inferring one for images saved
 * before `linkDestination` was editable: a link to the image's own URL is
 * a media-file link, any other link is custom.
 *
 * @since 1.18.0
 */
export function resolveLinkDestination(
    attributes: Pick<ImageEditorAttributes, 'href' | 'url' | 'linkDestination'>
): LinkDestination {
    const { href, url, linkDestination } = attributes;

    if (!href) {
        // "Custom URL" chosen in the inspector but not filled in yet.
        return linkDestination === LINK_DESTINATION_CUSTOM
            ? LINK_DESTINATION_CUSTOM
            : LINK_DESTINATION_NONE;
    }

    if (
        linkDestination === LINK_DESTINATION_MEDIA ||
        linkDestination === LINK_DESTINATION_CUSTOM
    ) {
        return linkDestination;
    }

    return href === url ? LINK_DESTINATION_MEDIA : LINK_DESTINATION_CUSTOM;
}

/**
 * Attributes to apply when the author picks a new link destination.
 *
 * Choosing "Custom URL" keeps an existing custom href so switching back
 * and forth does not wipe what the author typed; a media-file href is
 * dropped because it is not a custom value.
 *
 * @since 1.18.0
 */
export function getLinkDestinationAttributes(
    destination: string,
    attributes: Pick<ImageEditorAttributes, 'href' | 'url' | 'linkDestination'>
): Partial<ImageEditorAttributes> {
    if (destination === LINK_DESTINATION_MEDIA) {
        return { href: attributes.url, linkDestination: LINK_DESTINATION_MEDIA };
    }

    if (destination === LINK_DESTINATION_CUSTOM) {
        const keepHref =
            resolveLinkDestination(attributes) === LINK_DESTINATION_CUSTOM;
        return {
            href: keepHref ? attributes.href : undefined,
            linkDestination: LINK_DESTINATION_CUSTOM,
        };
    }

    return {
        href: undefined,
        linkTarget: undefined,
        rel: undefined,
        linkDestination: LINK_DESTINATION_NONE,
    };
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
        widthUnit,
        keepAspectRatio = true,
        height,
        aspectRatio,
        scale,
        href,
        rel,
        linkClass,
        linkTarget,
    } = attributes;

    const linkDestination = resolveLinkDestination(attributes);

    function onLinkChange(next: LinkToolbarValue): void {
        setAttributes({
            href: next.url || undefined,
            linkTarget: next.linkTarget,
            rel: next.rel,
            linkDestination:
                next.url && next.url === url
                    ? LINK_DESTINATION_MEDIA
                    : LINK_DESTINATION_CUSTOM,
        });
    }

    function onLinkRemove(): void {
        setAttributes(
            getLinkDestinationAttributes(LINK_DESTINATION_NONE, attributes)
        );
    }

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

    /**
     * Snaps a percentage value to the nearest preset when within tolerance.
     * Returns the numeric value (possibly snapped) so drag-to-resize lands
     * on a stable, exportable percentage.
     */
    function snapToPreset(pct: number): number {
        for (const preset of WIDTH_PRESETS) {
            if (Math.abs(pct - preset.percentage) <= WIDTH_PRESET_SNAP_PCT) {
                return preset.percentage;
            }
        }
        return pct;
    }

    /**
     * Called during drag. `elt` is the resizable wrapper — its clientWidth
     * is the on-screen width in pixels. We convert to the current unit and
     * write the same `width`/`widthUnit` attributes the inspector controls
     * write to, so all three surfaces feed one code path.
     */
    function onResizeStop(
        _event: unknown,
        _direction: unknown,
        elt: HTMLElement
    ): void {
        const pxWidth = elt.clientWidth;
        if (!Number.isFinite(pxWidth) || pxWidth < MIN_SIZE) {
            return;
        }

        // When `widthUnit` is unset, fall back to the unit encoded in `width`
        // so a stored responsive width like "50%" isn't silently replaced
        // with a pixel value on the first canvas drag.
        const activeUnit: WidthUnit =
            widthUnit ??
            (width?.trim().endsWith('%') ? '%' : 'px');

        // When the user is dragging both axes (keepAspectRatio off), persist
        // the resized height too — leaving it undefined lets the next render
        // discard the height change.
        const persistedHeight: Partial<ImageEditorAttributes> = keepAspectRatio
            ? { height: undefined }
            : { height: `${Math.round(elt.clientHeight)}px` };

        if (activeUnit === '%') {
            const parent = elt.parentElement;
            const parentWidth =
                parent?.getBoundingClientRect().width ?? pxWidth;
            if (parentWidth > 0) {
                const raw = Math.max(
                    1,
                    Math.round((pxWidth / parentWidth) * 100)
                );
                const snapped = snapToPreset(raw);
                setAttributes({
                    width: composeWidth(snapped, '%'),
                    widthUnit: '%',
                    ...persistedHeight,
                });
                return;
            }
        }

        setAttributes({
            width: composeWidth(Math.round(pxWidth), 'px'),
            widthUnit: 'px',
            ...persistedHeight,
        });
    }

    const linkedImage = <ImageWrapper href={attributes.href}>{image}</ImageWrapper>;
    const canResize = isSingleSelected && !!url;
    // Keep the ResizableBox OUTSIDE the (inline) anchor so its parent for the
    // percentage-width calculation in `onResizeStop` is the block wrapper,
    // not the link — the anchor's box can be smaller than the surrounding
    // container and would skew the ratio.
    const wrappedImage = canResize ? (
        <ResizableBox
            size={{
                width: width ?? 'auto',
                height: keepAspectRatio ? 'auto' : height ?? 'auto',
            }}
            minWidth={MIN_SIZE}
            enable={{
                top: false,
                right: true,
                bottom: !keepAspectRatio,
                left: true,
                topRight: false,
                bottomRight: true,
                bottomLeft: true,
                topLeft: false,
            }}
            lockAspectRatio={keepAspectRatio}
            onResizeStop={onResizeStop}
            __experimentalShowTooltip
        >
            {linkedImage}
        </ResizableBox>
    ) : (
        linkedImage
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
            {isSingleSelected && (
                <LinkToolbarControl
                    url={href}
                    linkTarget={linkTarget}
                    rel={rel}
                    onChange={onLinkChange}
                    onRemove={onLinkRemove}
                />
            )}
            <InspectorControls>
                <ToolsPanel
                    label={__('Settings', TEXT_DOMAIN)}
                    resetAll={() =>
                        setAttributes({
                            alt: '',
                            title: undefined,
                            width: undefined,
                            widthUnit: undefined,
                            height: undefined,
                            keepAspectRatio: true,
                        })
                    }
                >
                    <ToolsPanelItem
                        label={__('Alternative text', TEXT_DOMAIN)}
                        isShownByDefault
                        hasValue={() => !!alt}
                        onDeselect={() => setAttributes({ alt: '' })}
                    >
                        <TextareaControl
                            __nextHasNoMarginBottom
                            label={__('Alternative text', TEXT_DOMAIN)}
                            value={alt ?? ''}
                            onChange={(value: string) =>
                                setAttributes({ alt: value })
                            }
                            help={__(
                                'Describe the purpose of the image. Leave empty if decorative.',
                                TEXT_DOMAIN
                            )}
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        label={__('Image dimensions', TEXT_DOMAIN)}
                        isShownByDefault
                        hasValue={() => !!width}
                        onDeselect={() =>
                            setAttributes({
                                width: undefined,
                                widthUnit: undefined,
                                height: undefined,
                            })
                        }
                    >
                        <WidthControl
                            attributes={attributes}
                            setAttributes={setAttributes}
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        label={__('Link to', TEXT_DOMAIN)}
                        isShownByDefault
                        hasValue={() => linkDestination !== LINK_DESTINATION_NONE}
                        onDeselect={onLinkRemove}
                    >
                        <SelectControl
                            __nextHasNoMarginBottom
                            __next40pxDefaultSize
                            label={__('Link to', TEXT_DOMAIN)}
                            value={linkDestination}
                            options={[
                                { label: __('None', TEXT_DOMAIN), value: LINK_DESTINATION_NONE },
                                { label: __('Media file', TEXT_DOMAIN), value: LINK_DESTINATION_MEDIA },
                                { label: __('Custom URL', TEXT_DOMAIN), value: LINK_DESTINATION_CUSTOM },
                            ]}
                            onChange={(value: string) =>
                                setAttributes(
                                    getLinkDestinationAttributes(value, attributes)
                                )
                            }
                            help={
                                linkDestination === LINK_DESTINATION_CUSTOM
                                    ? __('Use the link button in the toolbar to set the URL.', TEXT_DOMAIN)
                                    : undefined
                            }
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        label={__('Title attribute', TEXT_DOMAIN)}
                        hasValue={() => !!title}
                        onDeselect={() => setAttributes({ title: undefined })}
                    >
                        <TextControl
                            __nextHasNoMarginBottom
                            __next40pxDefaultSize
                            label={__('Title attribute', TEXT_DOMAIN)}
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
            {href && (
                <InspectorControls group="advanced">
                    <TextControl
                        __nextHasNoMarginBottom
                        __next40pxDefaultSize
                        label={__('Link CSS class', TEXT_DOMAIN)}
                        value={linkClass ?? ''}
                        onChange={(value: string) =>
                            setAttributes({ linkClass: value || undefined })
                        }
                    />
                    <TextControl
                        __nextHasNoMarginBottom
                        __next40pxDefaultSize
                        label={__('Link relation', TEXT_DOMAIN)}
                        value={rel ?? ''}
                        onChange={(value: string) =>
                            setAttributes({ rel: value || undefined })
                        }
                    />
                </InspectorControls>
            )}
            {wrappedImage}
            {(isSingleSelected && hasNonContentControls) ||
            !RichText.isEmpty(caption ?? '') ? (
                <RichText
                    identifier="caption"
                    tagName="figcaption"
                    aria-label={__('Image caption text', TEXT_DOMAIN)}
                    placeholder={__('Add caption', TEXT_DOMAIN)}
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
