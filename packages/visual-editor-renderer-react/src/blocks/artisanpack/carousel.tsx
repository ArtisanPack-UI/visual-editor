/**
 * React renderers for the `artisanpack/carousel` family (#1127). Mirrors
 * the Blade partials and Vue components so every renderer emits identical
 * markup.
 *
 * The carousel walks `innerBlocks` and re-wraps each slide's pre-rendered
 * children itself, so it can label every slide "n of N" (WAI-ARIA APG
 * carousel pattern). The Blade package's `frontend/interactivity.js`
 * builds the dots, reveals the controls and drives the track; without it
 * the track stays a scroll-snap strip.
 */

import { Children, Fragment, isValidElement, type JSX, type ReactNode } from 'react';

import { attrString, classList } from '../../support/attributes';
import { compileBlockSupports } from '../../support/blockSupports';
import { CAROUSEL_ARROW_PATHS, fillLabel, getCarouselLabels, resolveCarousel } from '../../support/carousel';
import { resolveAnchor } from '../../support/headingAnchors';
import { styleProp } from '../../support/styleProp';
import type { Block, BlockRendererProps } from '../../types';

// `BlockSupports::applyAlign()`: an allowlisted `align{value}` class, plus
// `has-text-align-{value}` for left / center / right.
const ALIGN_VALUES: ReadonlyArray<string> = ['left', 'center', 'right', 'wide', 'full'];

function alignClasses(align: string): string[] {
    if (!ALIGN_VALUES.includes(align)) {
        return [];
    }

    return ['left', 'center', 'right'].includes(align) ? [`align${align}`, `has-text-align-${align}`] : [`align${align}`];
}

function Arrow({ direction, label }: { direction: 'previous' | 'next'; label: string }): JSX.Element {
    return (
        <button
            type="button"
            className={`wp-block-artisanpack-carousel__arrow wp-block-artisanpack-carousel__arrow--${direction}`}
            aria-label={label}
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                width="24"
                height="24"
                aria-hidden="true"
                focusable="false"
            >
                <path
                    d={CAROUSEL_ARROW_PATHS[direction]}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
        </button>
    );
}

/** A slide's wrapper class and style, from its own supports. */
function slideWrapper(attributes: Record<string, unknown>): { className: string; style: Record<string, string> } {
    const supports = compileBlockSupports(attributes);

    return {
        className: classList([
            'wp-block-artisanpack-carousel-slide',
            'ap-carousel-slide',
            ...supports.classNames,
            attrString(attributes.className),
        ]),
        style: supports.style,
    };
}

export function CarouselBlock({ attributes, innerBlocks, children }: BlockRendererProps): JSX.Element {
    const settings = resolveCarousel(attributes);
    const labels = getCarouselLabels();
    const supports = compileBlockSupports(attributes);

    const classes = classList([
        'wp-block-artisanpack-carousel',
        'ap-carousel',
        `is-controls-${settings.placement}`,
        settings.dimInactive ? 'has-dimmed-inactive' : null,
        ...alignClasses(attrString(attributes.align)),
        ...supports.classNames,
        attrString(attributes.className),
    ]);

    // `BlockTree` hands the rendered children over wrapped in a single
    // `<Fragment>`; unwrap it to index each slide by its inner-block position.
    let childArray = Children.toArray(children);

    if (childArray.length === 1 && isValidElement(childArray[0]) && childArray[0].type === Fragment) {
        childArray = Children.toArray((childArray[0].props as { children?: ReactNode }).children);
    }

    const slides: Array<{ attributes: Record<string, unknown>; content: ReactNode }> = [];

    innerBlocks.forEach((child: Block, index: number) => {
        if (child.name !== 'artisanpack/carousel-slide') {
            return;
        }

        const childEl = childArray[index];

        slides.push({
            attributes: (child.attributes ?? {}) as Record<string, unknown>,
            content: isValidElement(childEl) ? ((childEl.props as { children?: ReactNode }).children ?? null) : null,
        });
    });

    const ariaLabel = attrString(attributes.ariaLabel).trim();
    const hasControls = settings.showArrows || settings.showDots || settings.autoplay !== null;

    return (
        <div
            className={classes}
            style={styleProp({ ...supports.style, ...settings.style })}
            id={resolveAnchor(attributes.anchor)}
            role="region"
            aria-roledescription={labels.carousel}
            aria-label={ariaLabel !== '' ? ariaLabel : labels.region}
            data-ap-carousel=""
            data-loop={settings.loop ? 'true' : undefined}
            data-autoplay={settings.autoplay !== null ? String(settings.autoplay) : undefined}
        >
            <div className="wp-block-artisanpack-carousel__viewport">
                <div className="wp-block-artisanpack-carousel__track" aria-live="polite">
                    {slides.map((slide, index) => {
                        const wrapper = slideWrapper(slide.attributes);
                        const slideLabel = attrString(slide.attributes.ariaLabel).trim();

                        return (
                            <div
                                key={index}
                                className={wrapper.className}
                                style={styleProp(wrapper.style)}
                                role="group"
                                aria-roledescription={labels.slide}
                                aria-label={
                                    slideLabel !== ''
                                        ? slideLabel
                                        : fillLabel(labels.slidePosition, { current: index + 1, total: slides.length })
                                }
                            >
                                {slide.content}
                            </div>
                        );
                    })}
                </div>
            </div>
            {hasControls && (
                <div className="wp-block-artisanpack-carousel__controls" hidden>
                    {settings.autoplay !== null && (
                        <button
                            type="button"
                            className="wp-block-artisanpack-carousel__autoplay"
                            data-label-pause={labels.pause}
                            data-label-play={labels.play}
                        >
                            {labels.pause}
                        </button>
                    )}
                    {settings.showArrows && <Arrow direction="previous" label={labels.previous} />}
                    {settings.showDots && (
                        <div
                            className="wp-block-artisanpack-carousel__dots"
                            role="group"
                            aria-label={labels.dots}
                            data-dot-label={labels.dot}
                        />
                    )}
                    {settings.showArrows && <Arrow direction="next" label={labels.next} />}
                </div>
            )}
        </div>
    );
}

// Standalone fallback when a slide renders outside its carousel. The
// carousel re-wraps its slides itself, so this only surfaces when
// something else renders the slide in isolation.
export function CarouselSlideBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const wrapper = slideWrapper(attributes);
    const ariaLabel = attrString(attributes.ariaLabel).trim();

    return (
        <div
            className={wrapper.className}
            style={styleProp(wrapper.style)}
            role="group"
            aria-roledescription={getCarouselLabels().slide}
            aria-label={ariaLabel !== '' ? ariaLabel : undefined}
        >
            {children}
        </div>
    );
}
