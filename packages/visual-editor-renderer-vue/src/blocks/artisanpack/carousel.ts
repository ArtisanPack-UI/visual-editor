/**
 * Vue renderers for the `artisanpack/carousel` family (#1127). Mirrors
 * the Blade partials and React components so every renderer emits
 * identical markup.
 *
 * The carousel walks `innerBlocks` and re-wraps each slide's slot content
 * itself, so it can label every slide "n of N" (WAI-ARIA APG carousel
 * pattern). The Blade package's `frontend/interactivity.js` builds the
 * dots, reveals the controls and drives the track; without it the track
 * stays a scroll-snap strip.
 */

import { defineComponent, h, type VNode } from 'vue';

import { attrString, classList } from '../../support/attributes';
import { compileBlockSupports } from '../../support/blockSupports';
import { CAROUSEL_ARROW_PATHS, fillLabel, getCarouselLabels, resolveCarousel } from '../../support/carousel';
import { resolveAnchor } from '../../support/headingAnchors';
import { blockRendererProps, withStyle } from '../shared';

// `BlockSupports::applyAlign()`: an allowlisted `align{value}` class, plus
// `has-text-align-{value}` for left / center / right.
const ALIGN_VALUES: ReadonlyArray<string> = ['left', 'center', 'right', 'wide', 'full'];

function alignClasses(align: string): string[] {
    if (!ALIGN_VALUES.includes(align)) {
        return [];
    }

    return ['left', 'center', 'right'].includes(align) ? [`align${align}`, `has-text-align-${align}`] : [`align${align}`];
}

function extractSlotContent(vnode: VNode | null | undefined): VNode[] {
    if (vnode === null || vnode === undefined) {
        return [];
    }
    const children = vnode.children;
    if (children !== null && typeof children === 'object' && 'default' in children) {
        const slot = (children as { default?: () => unknown }).default;
        if (typeof slot === 'function') {
            const result = slot();
            return Array.isArray(result) ? (result as VNode[]) : [];
        }
    }
    return [];
}

function arrow(direction: 'previous' | 'next', label: string): VNode {
    return h(
        'button',
        {
            type: 'button',
            class: `wp-block-artisanpack-carousel__arrow wp-block-artisanpack-carousel__arrow--${direction}`,
            'aria-label': label,
        },
        [
            h(
                'svg',
                {
                    xmlns: 'http://www.w3.org/2000/svg',
                    viewBox: '0 0 24 24',
                    width: '24',
                    height: '24',
                    'aria-hidden': 'true',
                    focusable: 'false',
                },
                [
                    h('path', {
                        d: CAROUSEL_ARROW_PATHS[direction],
                        fill: 'none',
                        stroke: 'currentColor',
                        'stroke-width': '2',
                        'stroke-linecap': 'round',
                        'stroke-linejoin': 'round',
                    }),
                ]
            ),
        ]
    );
}

/**
 * A slide's wrapper props, from its own supports. The carousel rebuilds
 * its slides from `innerBlocks`, so it adds the screen-size visibility
 * scope itself (`withScope`); a slide rendered on its own already has it
 * in `className`, merged by `BlockTree`.
 */
function slideProps(attributes: Record<string, unknown>, withScope: boolean): Record<string, unknown> {
    const supports = compileBlockSupports(attributes);

    return withStyle(
        {
            class: classList([
                'wp-block-artisanpack-carousel-slide',
                'ap-carousel-slide',
                ...supports.classNames,
                attrString(attributes.className),
                withScope ? attrString(attributes._veVisScope) : null,
            ]),
        },
        supports.style
    );
}

export const CarouselBlock = defineComponent({
    name: 'CarouselBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const settings = resolveCarousel(props.attributes);
            const labels = getCarouselLabels();
            const supports = compileBlockSupports(props.attributes);

            const classes = classList([
                'wp-block-artisanpack-carousel',
                'ap-carousel',
                `is-controls-${settings.placement}`,
                settings.dimInactive ? 'has-dimmed-inactive' : null,
                ...alignClasses(attrString(props.attributes.align)),
                ...supports.classNames,
                attrString(props.attributes.className),
            ]);

            const slotVnodes = (slots.default ? slots.default() : []) as VNode[];
            const slides: Array<{ attributes: Record<string, unknown>; content: VNode[] }> = [];

            props.innerBlocks.forEach((child, index) => {
                if (child.name !== 'artisanpack/carousel-slide') {
                    return;
                }

                slides.push({
                    attributes: (child.attributes ?? {}) as Record<string, unknown>,
                    content: extractSlotContent(slotVnodes[index]),
                });
            });

            const slideVnodes = slides.map((slide, index) => {
                const slideLabel = attrString(slide.attributes.ariaLabel).trim();

                return h(
                    'div',
                    {
                        key: index,
                        ...slideProps(slide.attributes, true),
                        role: 'group',
                        'aria-roledescription': labels.slide,
                        'aria-label':
                            slideLabel !== ''
                                ? slideLabel
                                : fillLabel(labels.slidePosition, { current: index + 1, total: slides.length }),
                    },
                    slide.content
                );
            });

            const controls: VNode[] = [];

            if (settings.autoplay !== null) {
                controls.push(
                    h(
                        'button',
                        {
                            type: 'button',
                            class: 'wp-block-artisanpack-carousel__autoplay',
                            'data-label-pause': labels.pause,
                            'data-label-play': labels.play,
                        },
                        labels.pause
                    )
                );
            }

            if (settings.showArrows) {
                controls.push(arrow('previous', labels.previous));
            }

            if (settings.showDots) {
                controls.push(
                    h('div', {
                        class: 'wp-block-artisanpack-carousel__dots',
                        role: 'group',
                        'aria-label': labels.dots,
                        'data-dot-label': labels.dot,
                    })
                );
            }

            if (settings.showArrows) {
                controls.push(arrow('next', labels.next));
            }

            const ariaLabel = attrString(props.attributes.ariaLabel).trim();
            const anchor = resolveAnchor(props.attributes.anchor);
            const rootProps: Record<string, unknown> = withStyle(
                { class: classes },
                { ...supports.style, ...settings.style }
            );

            if (anchor !== undefined) {
                rootProps.id = anchor;
            }

            rootProps.role = 'region';
            rootProps['aria-roledescription'] = labels.carousel;
            rootProps['aria-label'] = ariaLabel !== '' ? ariaLabel : labels.region;
            rootProps['data-ap-carousel'] = '';

            if (settings.loop) {
                rootProps['data-loop'] = 'true';
            }

            if (settings.autoplay !== null) {
                rootProps['data-autoplay'] = String(settings.autoplay);
            }

            return h('div', rootProps, [
                h('div', { class: 'wp-block-artisanpack-carousel__viewport' }, [
                    h('div', { class: 'wp-block-artisanpack-carousel__track', 'aria-live': 'polite' }, slideVnodes),
                ]),
                ...(controls.length > 0
                    ? [h('div', { class: 'wp-block-artisanpack-carousel__controls', hidden: '' }, controls)]
                    : []),
            ]);
        };
    },
});

// Standalone fallback when a slide renders outside its carousel. The
// carousel re-wraps its slides itself, so this only surfaces when
// something else renders the slide in isolation.
export const CarouselSlideBlock = defineComponent({
    name: 'CarouselSlideBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const nodeProps: Record<string, unknown> = {
                ...slideProps(props.attributes, false),
                role: 'group',
                'aria-roledescription': getCarouselLabels().slide,
            };
            const ariaLabel = attrString(props.attributes.ariaLabel).trim();

            if (ariaLabel !== '') {
                nodeProps['aria-label'] = ariaLabel;
            }

            return h('div', nodeProps, slots.default ? slots.default() : []);
        };
    },
});
