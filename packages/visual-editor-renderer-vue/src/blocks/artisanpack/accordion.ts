/**
 * Vue renderers for the `artisanpack/accordions` family (#497).
 * Mirrors the Blade partials and React renderers so every environment
 * emits identical markup.
 *
 * The accordion is the single source of truth for the panel id and
 * toggle icon. Its renderer reaches through the slot's title/body
 * VNodes to grab the grandchildren (the heading and paragraph) so it
 * can re-wrap them with the matching `id` / `aria-controls` /
 * `aria-labelledby` wiring.
 */

import { defineComponent, h, type VNode } from 'vue';

import { attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

type AccordionPanelIcon = 'plus-minus' | 'arrows';

const VALID_PANEL_ICONS: ReadonlyArray<AccordionPanelIcon> = ['plus-minus', 'arrows'];

function normalizePanelIcon(value: unknown): AccordionPanelIcon {
    const raw = attrString(value, 'plus-minus');
    return (VALID_PANEL_ICONS as ReadonlyArray<string>).includes(raw)
        ? (raw as AccordionPanelIcon)
        : 'plus-minus';
}

function extractSlotContent(vnode: VNode | null): unknown {
    if (vnode === null || vnode === undefined) {
        return null;
    }

    const children = vnode.children;
    if (children !== null && typeof children === 'object' && 'default' in children) {
        const slot = (children as { default?: () => unknown }).default;
        return typeof slot === 'function' ? slot() : null;
    }

    return null;
}

function findChildBlockVnode(
    slotVnodes: VNode[],
    innerBlocks: ReadonlyArray<{ name?: string }>,
    name: string
): VNode | null {
    const index = innerBlocks.findIndex((block) => block?.name === name);
    if (index === -1) {
        return null;
    }
    return slotVnodes[index] ?? null;
}

/**
 * The stored `className` of the first inner block with this name. The
 * accordion re-wraps its title and body, so their classes (and with
 * them the `ve-r-*` / `ve-fx-*` scopes, M-7) go on the re-wrapped
 * elements, as `accordion.blade.php` does.
 */
function childClassName(
    innerBlocks: ReadonlyArray<{ name?: string; attributes?: unknown }>,
    name: string
): string {
    const child = innerBlocks.find((block) => block?.name === name);
    const attributes = child?.attributes;

    return attributes !== null && typeof attributes === 'object' && !Array.isArray(attributes)
        ? attrString((attributes as Record<string, unknown>).className)
        : '';
}

export const AccordionsBlock = defineComponent({
    name: 'AccordionsBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const className = attrString(props.attributes.className);
            const classes = classList(['wp-block-artisanpack-accordions', 'ap-accordions', className]);

            return h(
                'div',
                { class: classes },
                slots.default ? slots.default() : []
            );
        };
    },
});

export const AccordionBlock = defineComponent({
    name: 'AccordionBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const panelId = attrString(props.attributes.panelId);
            const panelIcon = normalizePanelIcon(props.attributes.panelIcon);
            const defaultOpen = props.attributes.defaultOpen === true;
            const className = attrString(props.attributes.className);
            const classes = classList(['wp-block-artisanpack-accordion', 'ap-accordion', className]);

            const slotVnodes = (slots.default ? slots.default() : []) as VNode[];
            const titleVnode = findChildBlockVnode(
                slotVnodes,
                props.innerBlocks,
                'artisanpack/accordion-title'
            );
            const bodyVnode = findChildBlockVnode(
                slotVnodes,
                props.innerBlocks,
                'artisanpack/accordion-body'
            );

            const wrapperAttrs: Record<string, unknown> = {
                class: classes,
                'data-panel-icon': panelIcon,
            };
            if (panelId !== '') {
                wrapperAttrs['data-panel-id'] = panelId;
            }

            const triggerAttrs: Record<string, unknown> = {
                class: 'ap-accordion__title-content',
                role: 'button',
                tabindex: '0',
                'aria-expanded': defaultOpen ? 'true' : 'false',
            };
            if (panelId !== '') {
                triggerAttrs.id = `${panelId}-control`;
                triggerAttrs['aria-controls'] = panelId;
            }

            const bodyAttrs: Record<string, unknown> = {
                class: classList(['wp-block-artisanpack-accordion-body', 'ap-accordion__body', childClassName(props.innerBlocks, 'artisanpack/accordion-body')]),
                role: 'region',
            };
            if (!defaultOpen) {
                bodyAttrs.hidden = '';
            }
            if (panelId !== '') {
                bodyAttrs.id = panelId;
                bodyAttrs['aria-labelledby'] = `${panelId}-control`;
            }

            const titleContent = extractSlotContent(titleVnode) as VNode[] | null;
            const bodyContent = extractSlotContent(bodyVnode) as VNode[] | null;

            return h('div', wrapperAttrs, [
                h('div', { class: classList(['wp-block-artisanpack-accordion-title', 'ap-accordion__title', childClassName(props.innerBlocks, 'artisanpack/accordion-title')]) }, [
                    h('div', triggerAttrs, titleContent ?? []),
                    h('span', {
                        class: `ap-accordion__icon ap-accordion__icon--${panelIcon}`,
                        'aria-hidden': 'true',
                    }),
                ]),
                h('div', bodyAttrs, bodyContent ?? []),
            ]);
        };
    },
});

// Standalone fallback when rendered outside an accordion. The accordion
// wrapper reaches into these via the slot mechanism, so these only
// surface when something else renders the title/body in isolation.

export const AccordionTitleBlock = defineComponent({
    name: 'AccordionTitleBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () =>
            h(
                'div',
                { class: classList(['wp-block-artisanpack-accordion-title', 'ap-accordion__title', attrString(props.attributes.className)]) },
                slots.default ? slots.default() : []
            );
    },
});

export const AccordionBodyBlock = defineComponent({
    name: 'AccordionBodyBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () =>
            h(
                'div',
                { class: classList(['wp-block-artisanpack-accordion-body', 'ap-accordion__body', attrString(props.attributes.className)]) },
                slots.default ? slots.default() : []
            );
    },
});
