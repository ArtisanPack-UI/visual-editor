/**
 * Cover — inspector (sidebar) controls.
 *
 * Ported from
 * `@wordpress/block-library/src/cover/edit/inspector-controls.js`
 * (v9.43.0). The following upstream block-library internals are replaced
 * with inline equivalents (documented in `upstream-state.json` under
 * `knownDivergences`):
 *
 *   - `cleanEmptyObject` from `unlock(blockEditorPrivateApis)` → inline.
 *   - `ResolutionTool` from `unlock(blockEditorPrivateApis)` → a plain
 *     `SelectControl` inside a `ToolsPanelItem`.
 *   - `HTMLElementControl` from `unlock(blockEditorPrivateApis)` → a plain
 *     `SelectControl` rendered inside `InspectorControls group="advanced"`.
 *   - `PosterImage` from `../utils/poster-image` → a `MediaUploadCheck` +
 *     `MediaUpload` pair inline.
 *   - `useToolsPanelDropdownMenuProps` from `../utils/hooks` → omitted so
 *     the panel renders without the optional dropdown menu.
 */

import type { ReactElement } from 'react';
import { useMemo } from '@wordpress/element';
import {
    ExternalLink,
    FocalPointPicker,
    RangeControl,
    SelectControl,
    TextareaControl,
    ToggleControl,
    Button,
    // eslint-disable-next-line camelcase
    __experimentalUseCustomUnits as useCustomUnits,
    // eslint-disable-next-line camelcase
    __experimentalToolsPanel as ToolsPanel,
    // eslint-disable-next-line camelcase
    __experimentalToolsPanelItem as ToolsPanelItem,
    // eslint-disable-next-line camelcase
    __experimentalUnitControl as UnitControl,
    // eslint-disable-next-line camelcase
    __experimentalParseQuantityAndUnitFromRawValue as parseQuantityAndUnitFromRawValue,
} from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import {
    InspectorControls,
    MediaUpload,
    MediaUploadCheck,
    useSettings,
    store as blockEditorStore,
    // eslint-disable-next-line camelcase
    __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
    // eslint-disable-next-line camelcase
    __experimentalUseGradient as useGradient,
    // eslint-disable-next-line camelcase
    __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

import { COVER_MIN_HEIGHT, mediaPosition } from '../shared';
import { DEFAULT_MEDIA_SIZE_SLUG } from '../constants';

interface FocalPoint {
    x: number;
    y: number;
}

interface CoverAttributes {
    useFeaturedImage?: boolean;
    id?: number;
    dimRatio?: number;
    focalPoint?: FocalPoint;
    hasParallax?: boolean;
    isRepeated?: boolean;
    minHeight?: number;
    minHeightUnit?: string;
    alt?: string;
    tagName?: string;
    poster?: string;
    sizeSlug?: string;
    style?: {
        dimensions?: {
            aspectRatio?: string;
        };
    } & Record<string, unknown>;
}

interface CurrentSettings {
    isVideoBackground?: boolean;
    isImageBackground?: boolean;
    mediaElement?: { current: HTMLElement | null };
    url?: string;
    overlayColor?: { color?: string };
}

interface CoverInspectorControlsProps {
    attributes: CoverAttributes;
    setAttributes: (next: Partial<CoverAttributes>) => void;
    clientId: string;
    setOverlayColor: (color: string | undefined) => void;
    coverRef: { current: HTMLElement | null };
    currentSettings: CurrentSettings;
    updateDimRatio: (ratio: number) => void;
    toggleUseFeaturedImage: () => void;
    onClearMedia: () => void;
    featuredImage?: {
        media_details?: { sizes?: Record<string, { source_url: string }> };
    } | null;
}

function cleanEmptyObject<T>(object: T): T | undefined {
    if (
        object === null ||
        object === undefined ||
        typeof object !== 'object' ||
        Array.isArray(object)
    ) {
        return object;
    }

    const cleaned = Object.entries(object as Record<string, unknown>)
        .map(([key, value]) => [key, cleanEmptyObject(value)] as const)
        .filter(([, value]) => value !== undefined);

    if (cleaned.length === 0) {
        return undefined;
    }

    return Object.fromEntries(cleaned) as unknown as T;
}

interface CoverHeightInputProps {
    onChange: (value: number | undefined) => void;
    onUnitChange?: (unit: string) => void;
    unit?: string;
    value?: number | string;
}

function CoverHeightInput({
    onChange,
    onUnitChange,
    unit = 'px',
    value = '',
}: CoverHeightInputProps): ReactElement {
    const instanceId = useInstanceId(UnitControl);
    const inputId = `block-cover-height-input-${instanceId}`;
    const isPx = unit === 'px';

    const [availableUnits] = useSettings('spacing.units');
    const units = useCustomUnits({
        availableUnits: availableUnits || ['px', 'em', 'rem', 'vw', 'vh'],
        defaultValues: { px: 430, '%': 20, em: 20, rem: 20, vw: 20, vh: 50 },
    });

    const handleOnChange = (unprocessedValue: string): void => {
        const inputValue =
            unprocessedValue !== ''
                ? parseFloat(unprocessedValue)
                : undefined;

        if (inputValue !== undefined && Number.isNaN(inputValue)) {
            return;
        }
        onChange(inputValue);
    };

    const computedValue = useMemo(() => {
        const [parsedQuantity] = parseQuantityAndUnitFromRawValue(value);
        return [parsedQuantity, unit].join('');
    }, [unit, value]);

    const min = isPx ? COVER_MIN_HEIGHT : 0;

    return (
        <UnitControl
            __next40pxDefaultSize
            label={__('Minimum height')}
            id={inputId}
            isResetValueOnUnitChange
            min={min}
            onChange={handleOnChange}
            onUnitChange={onUnitChange}
            units={units}
            value={computedValue}
        />
    );
}

export default function CoverInspectorControls({
    attributes,
    setAttributes,
    clientId,
    setOverlayColor,
    coverRef,
    currentSettings,
    updateDimRatio,
    featuredImage,
}: CoverInspectorControlsProps): ReactElement {
    const {
        useFeaturedImage,
        id,
        dimRatio,
        focalPoint,
        hasParallax,
        isRepeated,
        minHeight,
        minHeightUnit,
        alt,
        tagName,
        poster,
    } = attributes;
    const {
        isVideoBackground,
        isImageBackground,
        mediaElement,
        url,
        overlayColor,
    } = currentSettings;

    const sizeSlug = attributes.sizeSlug || DEFAULT_MEDIA_SIZE_SLUG;

    const { gradientValue, setGradient } = useGradient();
    const { getSettings } = useSelect(blockEditorStore, []);

    const imageSizes = (
        getSettings() as { imageSizes?: { name: string; slug: string }[] }
    )?.imageSizes;

    const image = useSelect(
        (select) =>
            id && isImageBackground
                ? (
                      select(coreStore) as unknown as {
                          getEntityRecord: (
                              kind: string,
                              name: string,
                              key: number,
                              query?: { context?: string }
                          ) => unknown;
                      }
                  ).getEntityRecord('postType', 'attachment', id, {
                      context: 'view',
                  })
                : null,
        [id, isImageBackground]
    ) as
        | {
              media_details?: {
                  sizes?: Record<string, { source_url: string }>;
              };
          }
        | null;

    const currentBackgroundImage = useFeaturedImage ? featuredImage : image;

    function updateImage(newSizeSlug: string): null | undefined {
        const newUrl =
            currentBackgroundImage?.media_details?.sizes?.[newSizeSlug]
                ?.source_url;
        if (!newUrl) {
            return null;
        }

        setAttributes({
            url: newUrl,
            sizeSlug: newSizeSlug,
        });
        return undefined;
    }

    const imageSizeOptions = imageSizes
        ?.filter(
            ({ slug }) =>
                currentBackgroundImage?.media_details?.sizes?.[slug]
                    ?.source_url
        )
        ?.map(({ name, slug }) => ({ value: slug, label: name }));

    const toggleParallax = (): void => {
        setAttributes({
            hasParallax: !hasParallax,
            ...(!hasParallax ? { focalPoint: undefined } : {}),
        });
    };

    const toggleIsRepeated = (): void => {
        setAttributes({
            isRepeated: !isRepeated,
        });
    };

    const showFocalPointPicker = isVideoBackground || isImageBackground;

    const imperativeFocalPointPreview = (value: FocalPoint): void => {
        const target = mediaElement?.current ?? coverRef.current;
        if (!target) {
            return;
        }
        const property = mediaElement?.current
            ? 'objectPosition'
            : 'backgroundPosition';
        (target.style as unknown as Record<string, string>)[property] =
            mediaPosition(value);
    };

    const colorGradientSettings = useMultipleOriginColorsAndGradients();

    const tagNameOptions = [
        { label: __('Default (<div>)'), value: 'div' },
        { label: '<header>', value: 'header' },
        { label: '<main>', value: 'main' },
        { label: '<section>', value: 'section' },
        { label: '<article>', value: 'article' },
        { label: '<aside>', value: 'aside' },
        { label: '<footer>', value: 'footer' },
    ];

    return (
        <>
            <InspectorControls>
                {(!!url || useFeaturedImage) && (
                    <ToolsPanel
                        label={__('Settings')}
                        resetAll={() => {
                            setAttributes({
                                hasParallax: false,
                                focalPoint: undefined,
                                isRepeated: false,
                                alt: '',
                                poster: undefined,
                            });
                            updateImage(DEFAULT_MEDIA_SIZE_SLUG);
                        }}
                    >
                        {isImageBackground && (
                            <>
                                <ToolsPanelItem
                                    label={__('Fixed background')}
                                    isShownByDefault
                                    hasValue={() => !!hasParallax}
                                    onDeselect={() =>
                                        setAttributes({
                                            hasParallax: false,
                                            focalPoint: undefined,
                                        })
                                    }
                                >
                                    <ToggleControl
                                        label={__('Fixed background')}
                                        checked={!!hasParallax}
                                        onChange={toggleParallax}
                                    />
                                </ToolsPanelItem>

                                <ToolsPanelItem
                                    label={__('Repeated background')}
                                    isShownByDefault
                                    hasValue={() => !!isRepeated}
                                    onDeselect={() =>
                                        setAttributes({
                                            isRepeated: false,
                                        })
                                    }
                                >
                                    <ToggleControl
                                        label={__('Repeated background')}
                                        checked={!!isRepeated}
                                        onChange={toggleIsRepeated}
                                    />
                                </ToolsPanelItem>
                            </>
                        )}
                        {showFocalPointPicker && url && (
                            <ToolsPanelItem
                                label={__('Focal point')}
                                isShownByDefault
                                hasValue={() => !!focalPoint}
                                onDeselect={() =>
                                    setAttributes({
                                        focalPoint: undefined,
                                    })
                                }
                            >
                                <FocalPointPicker
                                    label={__('Focal point')}
                                    url={url}
                                    value={focalPoint}
                                    onDragStart={imperativeFocalPointPreview}
                                    onDrag={imperativeFocalPointPreview}
                                    onChange={(newFocalPoint: FocalPoint) =>
                                        setAttributes({
                                            focalPoint: newFocalPoint,
                                        })
                                    }
                                />
                            </ToolsPanelItem>
                        )}
                        {isVideoBackground && (
                            <ToolsPanelItem
                                label={__('Poster image')}
                                isShownByDefault
                                hasValue={() => !!poster}
                                onDeselect={() =>
                                    setAttributes({ poster: undefined })
                                }
                            >
                                <MediaUploadCheck>
                                    <MediaUpload
                                        title={__('Poster image')}
                                        onSelect={(media: {
                                            url?: string;
                                        }) =>
                                            setAttributes({
                                                poster: media?.url,
                                            })
                                        }
                                        allowedTypes={['image']}
                                        render={({
                                            open,
                                        }: {
                                            open: () => void;
                                        }) => (
                                            <Button
                                                variant="secondary"
                                                onClick={open}
                                            >
                                                {poster
                                                    ? __('Replace')
                                                    : __('Select')}
                                            </Button>
                                        )}
                                    />
                                </MediaUploadCheck>
                            </ToolsPanelItem>
                        )}
                        {!useFeaturedImage && url && !isVideoBackground && (
                            <ToolsPanelItem
                                label={__('Alternative text')}
                                isShownByDefault
                                hasValue={() => !!alt}
                                onDeselect={() => setAttributes({ alt: '' })}
                            >
                                <TextareaControl
                                    label={__('Alternative text')}
                                    value={alt ?? ''}
                                    onChange={(newAlt: string) =>
                                        setAttributes({ alt: newAlt })
                                    }
                                    help={
                                        <>
                                            <ExternalLink
                                                href="https://www.w3.org/WAI/tutorials/images/decision-tree/"
                                            >
                                                {__(
                                                    'Describe the purpose of the image.'
                                                )}
                                            </ExternalLink>
                                            <br />
                                            {__('Leave empty if decorative.')}
                                        </>
                                    }
                                />
                            </ToolsPanelItem>
                        )}
                        {!!imageSizeOptions?.length && (
                            <ToolsPanelItem
                                label={__('Resolution')}
                                isShownByDefault
                                hasValue={() =>
                                    sizeSlug !== DEFAULT_MEDIA_SIZE_SLUG
                                }
                                onDeselect={() =>
                                    updateImage(DEFAULT_MEDIA_SIZE_SLUG)
                                }
                            >
                                <SelectControl
                                    __next40pxDefaultSize
                                    label={__('Resolution')}
                                    value={sizeSlug}
                                    options={imageSizeOptions}
                                    onChange={(next: string) =>
                                        updateImage(next)
                                    }
                                />
                            </ToolsPanelItem>
                        )}
                    </ToolsPanel>
                )}
            </InspectorControls>
            {(colorGradientSettings as { hasColorsOrGradients?: boolean })
                .hasColorsOrGradients && (
                <InspectorControls group="color">
                    <ColorGradientSettingsDropdown
                        __experimentalIsRenderedInSidebar
                        settings={[
                            {
                                colorValue: overlayColor?.color,
                                gradientValue,
                                label: __('Overlay'),
                                onColorChange: setOverlayColor,
                                onGradientChange: setGradient,
                                isShownByDefault: true,
                                resetAllFilter: () => ({
                                    overlayColor: undefined,
                                    customOverlayColor: undefined,
                                    gradient: undefined,
                                    customGradient: undefined,
                                }),
                                clearable: true,
                            },
                        ]}
                        panelId={clientId}
                        {...(colorGradientSettings as Record<string, unknown>)}
                    />
                    <ToolsPanelItem
                        hasValue={() => {
                            return dimRatio === undefined
                                ? false
                                : dimRatio !== (url ? 50 : 100);
                        }}
                        label={__('Overlay opacity')}
                        onDeselect={() => updateDimRatio(url ? 50 : 100)}
                        resetAllFilter={() => ({
                            dimRatio: url ? 50 : 100,
                        })}
                        isShownByDefault
                        panelId={clientId}
                    >
                        <RangeControl
                            label={__('Overlay opacity')}
                            value={dimRatio}
                            onChange={(newDimRatio: number) =>
                                updateDimRatio(newDimRatio)
                            }
                            min={0}
                            max={100}
                            step={10}
                            required
                            __next40pxDefaultSize
                        />
                    </ToolsPanelItem>
                </InspectorControls>
            )}
            <InspectorControls group="dimensions">
                <ToolsPanelItem
                    className="single-column"
                    hasValue={() => !!minHeight}
                    label={__('Minimum height')}
                    onDeselect={() =>
                        setAttributes({
                            minHeight: undefined,
                            minHeightUnit: undefined,
                        })
                    }
                    resetAllFilter={() => ({
                        minHeight: undefined,
                        minHeightUnit: undefined,
                    })}
                    isShownByDefault
                    panelId={clientId}
                >
                    <CoverHeightInput
                        value={
                            attributes?.style?.dimensions?.aspectRatio
                                ? ''
                                : minHeight
                        }
                        unit={minHeightUnit}
                        onChange={(newMinHeight) =>
                            setAttributes({
                                minHeight: newMinHeight,
                                style: cleanEmptyObject({
                                    ...attributes?.style,
                                    dimensions: {
                                        ...attributes?.style?.dimensions,
                                        aspectRatio: undefined,
                                    },
                                }),
                            })
                        }
                        onUnitChange={(nextUnit: string) =>
                            setAttributes({
                                minHeightUnit: nextUnit,
                            })
                        }
                    />
                </ToolsPanelItem>
            </InspectorControls>
            <InspectorControls group="advanced">
                <SelectControl
                    __next40pxDefaultSize
                    label={__('HTML element')}
                    value={tagName ?? 'div'}
                    options={tagNameOptions}
                    onChange={(value: string) =>
                        setAttributes({ tagName: value })
                    }
                />
            </InspectorControls>
        </>
    );
}
