/**
 * Carousel — editor-side component (#1127).
 *
 * The canvas lays the slides out side by side at the slides per view in
 * force for the editor's active breakpoint, so the preview matches the
 * front end. The track scrolls sideways, and the previous / next arrows
 * scroll it by one slide. The toolbar's "Show all slides" switch wraps
 * the slides onto rows instead, so off-screen slides can be edited
 * without scrolling. Dots and the autoplay button are a static preview;
 * the front-end script drives the real ones.
 */

import { useRef, useState, useSyncExternalStore, type CSSProperties, type ReactElement } from 'react';
import {
    BlockControls,
    InspectorControls,
    PanelColorSettings,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import {
    Button,
    PanelBody,
    RangeControl,
    SelectControl,
    ToggleControl,
    ToolbarButton,
    ToolbarGroup,
} from '@wordpress/components';
import { createBlock } from '@wordpress/blocks';
import { __, sprintf } from '@wordpress/i18n';
import { plus as plusIcon, seen as seenIcon } from '@wordpress/icons';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { getActiveBreakpoint, subscribeActiveBreakpoint } from '../../responsive/active-breakpoint';

import {
    MAX_AUTOPLAY_INTERVAL,
    MAX_SLIDES_PER_VIEW,
    MIN_AUTOPLAY_INTERVAL,
    MIN_SLIDES_PER_VIEW,
    blockGapValue,
    carouselStyleVariables,
    normalizeAutoplayInterval,
    normalizeInactiveOpacity,
    normalizePlacement,
    positionCount,
    resolveSlidesPerView,
    slidesPerViewAt,
    type CarouselAttributes,
    type ControlsPlacement,
} from './settings';
import { ArrowIcon } from './arrow-icon';

interface CarouselEditProps {
    readonly attributes: CarouselAttributes;
    readonly setAttributes: (next: Partial<CarouselAttributes>) => void;
    readonly clientId: string;
}

const SLIDE_BLOCK = 'artisanpack/carousel-slide';
const ALLOWED_BLOCKS: string[] = [SLIDE_BLOCK];
const TEMPLATE: [string, Record<string, unknown>][] = [
    [SLIDE_BLOCK, {}],
    [SLIDE_BLOCK, {}],
    [SLIDE_BLOCK, {}],
];

function placementOptions(): ReadonlyArray<{ value: ControlsPlacement; label: string }> {
    return [
        { value: 'below', label: __('Below the slides', TEXT_DOMAIN) },
        { value: 'split', label: __('Split: dots left, arrows right', TEXT_DOMAIN) },
        { value: 'overlay', label: __('Arrows on the sides', TEXT_DOMAIN) },
    ];
}

function useActiveBreakpoint(): string {
    return useSyncExternalStore(subscribeActiveBreakpoint, getActiveBreakpoint, getActiveBreakpoint);
}

function slidesPerViewHelp(breakpoint: string): string {
    if (breakpoint === 'tablet') {
        return __('Applies at tablet widths. Defaults to at most 2.', TEXT_DOMAIN);
    }

    if (breakpoint === 'mobile') {
        return __('Applies at mobile widths. Defaults to 1.', TEXT_DOMAIN);
    }

    return __(
        'Switch the editor to Tablet or Mobile to set those sizes. They default to at most 2 and to 1.',
        TEXT_DOMAIN
    );
}

export default function CarouselEdit({ attributes, setAttributes, clientId }: CarouselEditProps): ReactElement {
    const breakpoint = useActiveBreakpoint();
    const [showAll, setShowAll] = useState(false);
    const rootRef = useRef<HTMLDivElement | null>(null);

    // The responsive HOC hands this component the active breakpoint's
    // value already merged in; the stored attributes still hold the base
    // value and the raw overrides, which the per-size fallbacks need.
    const { storedAttributes, slideCount } = useSelect(
        (select) => {
            const store = select('core/block-editor') as unknown as {
                getBlockAttributes: (id: string) => Record<string, unknown> | null;
                getBlockOrder: (id: string) => string[];
            };

            return {
                storedAttributes: store.getBlockAttributes(clientId) ?? {},
                slideCount: store.getBlockOrder(clientId).length,
            };
        },
        [clientId]
    );

    const { insertBlock } = useDispatch('core/block-editor') as unknown as {
        insertBlock: (block: unknown, index?: number, rootClientId?: string) => void;
    };

    const perView = resolveSlidesPerView(storedAttributes.slidesPerView, storedAttributes.responsive);
    const activePerView = slidesPerViewAt(perView, breakpoint);
    const placement = normalizePlacement(attributes.controlsPlacement);
    const autoplayInterval = normalizeAutoplayInterval(attributes.autoplayInterval);
    const inactiveOpacity = normalizeInactiveOpacity(attributes.inactiveOpacity);
    const gap = blockGapValue(attributes.style);

    const style: Record<string, string> = carouselStyleVariables(attributes, perView);

    // The editor previews one breakpoint at a time at any canvas width,
    // so the active size's count is pinned instead of left to the
    // stylesheet's media queries.
    style['--ap-carousel-columns'] = String(activePerView);

    if (gap !== null) {
        style['--ap-carousel-gap'] = gap;
    }

    const blockProps = useBlockProps({
        ref: rootRef,
        className: [
            'ap-carousel',
            `is-controls-${placement}`,
            attributes.dimInactive ? 'has-dimmed-inactive' : null,
            showAll ? 'is-showing-all-slides' : null,
        ]
            .filter(Boolean)
            .join(' '),
        style: style as CSSProperties,
    });

    const innerBlocksProps = useInnerBlocksProps(
        { className: 'wp-block-artisanpack-carousel__track' },
        {
            allowedBlocks: ALLOWED_BLOCKS,
            template: TEMPLATE,
            orientation: 'horizontal',
            renderAppender: false,
        }
    );

    const handleAddSlide = (): void => {
        insertBlock(createBlock(SLIDE_BLOCK, {}), slideCount, clientId);
    };

    const scrollBySlide = (direction: 1 | -1): void => {
        const track = rootRef.current?.querySelector<HTMLElement>('.wp-block-artisanpack-carousel__track');
        const slide = track?.querySelector<HTMLElement>(':scope > *');

        if (!track || !slide) {
            return;
        }

        // In a right-to-left track the next slide sits to the left.
        const sign = window.getComputedStyle(track).direction === 'rtl' ? -1 : 1;

        track.scrollBy({ left: sign * direction * slide.getBoundingClientRect().width, behavior: 'smooth' });
    };

    const dots = positionCount(slideCount, activePerView);

    return (
        <>
            <BlockControls group="block">
                <ToolbarGroup>
                    <ToolbarButton
                        icon={seenIcon}
                        label={showAll ? __('Show slides in a row', TEXT_DOMAIN) : __('Show all slides', TEXT_DOMAIN)}
                        isPressed={showAll}
                        onClick={() => setShowAll(!showAll)}
                    />
                    <ToolbarButton icon={plusIcon} label={__('Add slide', TEXT_DOMAIN)} onClick={handleAddSlide} />
                </ToolbarGroup>
            </BlockControls>
            <InspectorControls>
                <PanelBody title={__('Slides', TEXT_DOMAIN)} initialOpen>
                    <RangeControl
                        label={__('Slides per view', TEXT_DOMAIN)}
                        help={slidesPerViewHelp(breakpoint)}
                        value={activePerView}
                        min={MIN_SLIDES_PER_VIEW}
                        max={MAX_SLIDES_PER_VIEW}
                        onChange={(value) => {
                            if (typeof value === 'number') {
                                setAttributes({ slidesPerView: value });
                            }
                        }}
                        __nextHasNoMarginBottom
                    />
                    <Button variant="secondary" onClick={handleAddSlide}>
                        {__('Add slide', TEXT_DOMAIN)}
                    </Button>
                </PanelBody>
                <PanelBody title={__('Controls', TEXT_DOMAIN)} initialOpen>
                    <ToggleControl
                        label={__('Show previous and next arrows', TEXT_DOMAIN)}
                        checked={attributes.showArrows}
                        onChange={(value) => setAttributes({ showArrows: value })}
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Show dots', TEXT_DOMAIN)}
                        checked={attributes.showDots}
                        onChange={(value) => setAttributes({ showDots: value })}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Control placement', TEXT_DOMAIN)}
                        value={placement}
                        options={placementOptions() as Array<{ value: ControlsPlacement; label: string }>}
                        onChange={(value) => setAttributes({ controlsPlacement: normalizePlacement(value) })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                <PanelBody title={__('Behavior', TEXT_DOMAIN)} initialOpen={false}>
                    <ToggleControl
                        label={__('Loop', TEXT_DOMAIN)}
                        help={__('Go back to the first slide after the last.', TEXT_DOMAIN)}
                        checked={attributes.loop}
                        onChange={(value) => setAttributes({ loop: value })}
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Autoplay', TEXT_DOMAIN)}
                        help={__(
                            'Adds a pause button. Never plays for visitors who prefer reduced motion.',
                            TEXT_DOMAIN
                        )}
                        checked={attributes.autoplay}
                        onChange={(value) => setAttributes({ autoplay: value })}
                        __nextHasNoMarginBottom
                    />
                    {attributes.autoplay && (
                        <RangeControl
                            label={__('Seconds per slide', TEXT_DOMAIN)}
                            value={autoplayInterval}
                            min={MIN_AUTOPLAY_INTERVAL}
                            max={MAX_AUTOPLAY_INTERVAL}
                            onChange={(value) => {
                                if (typeof value === 'number') {
                                    setAttributes({ autoplayInterval: value });
                                }
                            }}
                            __nextHasNoMarginBottom
                        />
                    )}
                </PanelBody>
                <PanelBody title={__('Inactive slides', TEXT_DOMAIN)} initialOpen={false}>
                    <ToggleControl
                        label={__('Dim slides outside the view', TEXT_DOMAIN)}
                        help={__(
                            'Lets the neighboring slides peek in, faded, within the carousel’s padding.',
                            TEXT_DOMAIN
                        )}
                        checked={attributes.dimInactive}
                        onChange={(value) => setAttributes({ dimInactive: value })}
                        __nextHasNoMarginBottom
                    />
                    {attributes.dimInactive && (
                        <RangeControl
                            label={__('Inactive slide opacity (%)', TEXT_DOMAIN)}
                            value={inactiveOpacity}
                            min={0}
                            max={100}
                            onChange={(value) => {
                                if (typeof value === 'number') {
                                    setAttributes({ inactiveOpacity: value });
                                }
                            }}
                            __nextHasNoMarginBottom
                        />
                    )}
                </PanelBody>
                <PanelColorSettings
                    title={__('Control colors', TEXT_DOMAIN)}
                    initialOpen={false}
                    colorSettings={[
                        {
                            value: attributes.arrowColor,
                            onChange: (value: string | undefined) => setAttributes({ arrowColor: value ?? '' }),
                            label: __('Arrow icon', TEXT_DOMAIN),
                        },
                        {
                            value: attributes.arrowBackgroundColor,
                            onChange: (value: string | undefined) =>
                                setAttributes({ arrowBackgroundColor: value ?? '' }),
                            label: __('Arrow background', TEXT_DOMAIN),
                        },
                        {
                            value: attributes.dotColor,
                            onChange: (value: string | undefined) => setAttributes({ dotColor: value ?? '' }),
                            label: __('Dot', TEXT_DOMAIN),
                        },
                        {
                            value: attributes.dotActiveColor,
                            onChange: (value: string | undefined) => setAttributes({ dotActiveColor: value ?? '' }),
                            label: __('Active dot', TEXT_DOMAIN),
                        },
                    ]}
                />
            </InspectorControls>
            <div {...blockProps}>
                <div className="wp-block-artisanpack-carousel__viewport">
                    <div {...innerBlocksProps} />
                </div>
                {(attributes.showArrows || attributes.showDots || attributes.autoplay) && (
                    <div className="wp-block-artisanpack-carousel__controls">
                        {attributes.autoplay && (
                            <button type="button" className="wp-block-artisanpack-carousel__autoplay" tabIndex={-1}>
                                {__('Pause', TEXT_DOMAIN)}
                            </button>
                        )}
                        {attributes.showArrows && (
                            <button
                                type="button"
                                className="wp-block-artisanpack-carousel__arrow wp-block-artisanpack-carousel__arrow--previous"
                                aria-label={__('Previous slide', TEXT_DOMAIN)}
                                onClick={() => scrollBySlide(-1)}
                            >
                                <ArrowIcon direction="previous" />
                            </button>
                        )}
                        {attributes.showDots && (
                            <div className="wp-block-artisanpack-carousel__dots" aria-hidden="true">
                                {Array.from({ length: dots }, (_unused, index) => (
                                    <span
                                        key={index}
                                        className={`wp-block-artisanpack-carousel__dot${index === 0 ? ' is-active' : ''}`}
                                        title={sprintf(
                                            /* translators: %d: slide position. */
                                            __('Slide %d', TEXT_DOMAIN),
                                            index + 1
                                        )}
                                    />
                                ))}
                            </div>
                        )}
                        {attributes.showArrows && (
                            <button
                                type="button"
                                className="wp-block-artisanpack-carousel__arrow wp-block-artisanpack-carousel__arrow--next"
                                aria-label={__('Next slide', TEXT_DOMAIN)}
                                onClick={() => scrollBySlide(1)}
                            >
                                <ArrowIcon direction="next" />
                            </button>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
