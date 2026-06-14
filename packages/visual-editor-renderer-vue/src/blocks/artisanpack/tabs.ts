/**
 * Vue renderers for the `artisanpack/tabs` family (#497). Mirrors the
 * Blade and React renderers so every rendering environment emits
 * identical markup.
 *
 * Tab triggers are derived from the inner-block tab-sections — each
 * section owns its own `label` and `tabId`. The tabs renderer walks
 * `innerBlocks` to build the tablist AND re-wraps each tab-section's
 * pre-rendered grandchildren with the SAME resolved tabId so the
 * trigger `aria-controls` always lines up with the panel `id`.
 */

import { defineComponent, h, type VNode } from 'vue';

import { attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

type TabsAlign = 'horizontal' | 'vertical';
type TabsSpacing = 'start' | 'end' | 'center' | 'equal';

const VALID_ALIGNS: ReadonlyArray<TabsAlign> = ['horizontal', 'vertical'];
const VALID_SPACINGS: ReadonlyArray<TabsSpacing> = ['start', 'end', 'center', 'equal'];

interface TabRecord {
    readonly label: string;
    readonly tabId: string;
    readonly content: VNode[];
}

function normalizeAlign(value: unknown): TabsAlign {
    const raw = attrString(value, 'horizontal');
    return (VALID_ALIGNS as ReadonlyArray<string>).includes(raw)
        ? (raw as TabsAlign)
        : 'horizontal';
}

function normalizeSpacing(value: unknown): TabsSpacing {
    const raw = attrString(value, 'start');
    return (VALID_SPACINGS as ReadonlyArray<string>).includes(raw)
        ? (raw as TabsSpacing)
        : 'start';
}

function sanitizeTabId(raw: string, sectionIndex: number): string {
    const slug = raw
        .toLowerCase()
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/^-+|-+$/g, '');
    return slug === '' ? `tab-${sectionIndex}` : slug;
}

/**
 * Ensure a tab id is unique within the current tabs block — if the
 * author duplicated a tab-section (copy/paste, template clone) two
 * sections can land on the same slug, which would collapse the
 * trigger / panel ARIA wiring. Append a numeric suffix in that case.
 */
function uniqueTabId(base: string, used: Set<string>): string {
    if (!used.has(base)) {
        used.add(base);
        return base;
    }
    let suffix = 2;
    let candidate = `${base}-${suffix}`;
    while (used.has(candidate)) {
        suffix += 1;
        candidate = `${base}-${suffix}`;
    }
    used.add(candidate);
    return candidate;
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

export const TabsBlock = defineComponent({
    name: 'TabsBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const tabsAlign = normalizeAlign(props.attributes.tabsAlign);
            const tabsSpacing = normalizeSpacing(props.attributes.tabsSpacing);
            const className = attrString(props.attributes.className);
            const classes = classList([
                'ap-tabs',
                `align-tabs-${tabsAlign}`,
                `space-tabs-${tabsSpacing}`,
                className,
            ]);

            const slotVnodes = (slots.default ? slots.default() : []) as VNode[];

            const tabs: TabRecord[] = [];
            const usedIds = new Set<string>();
            let sectionIndex = 0;
            props.innerBlocks.forEach((child, index) => {
                if (child.name !== 'artisanpack/tab-section') {
                    return;
                }
                sectionIndex += 1;
                const childAttrs = (child.attributes ?? {}) as Record<string, unknown>;
                const labelRaw = attrString(childAttrs.label);
                const baseId = sanitizeTabId(attrString(childAttrs.tabId), sectionIndex);
                const tabId = uniqueTabId(baseId, usedIds);

                tabs.push({
                    label: labelRaw === '' ? `Tab ${sectionIndex}` : labelRaw,
                    tabId,
                    content: extractSlotContent(slotVnodes[index]),
                });
            });

            const triggerVnodes: VNode[] = tabs.map((tab, index) => {
                const isFirst = index === 0;
                return h('li', { key: `${index}-${tab.tabId}` }, [
                    h(
                        'a',
                        {
                            href: `#tabs-panel-${tab.tabId}`,
                            id: `tabs-tab-${tab.tabId}`,
                            role: 'tab',
                            'aria-controls': `tabs-panel-${tab.tabId}`,
                            'aria-selected': isFirst ? 'true' : 'false',
                            tabindex: isFirst ? '0' : '-1',
                        },
                        tab.label
                    ),
                ]);
            });

            const panelVnodes: VNode[] = tabs.map((tab, index) =>
                h(
                    'div',
                    {
                        key: `panel-${index}-${tab.tabId}`,
                        class: 'ap-tab-section',
                        role: 'tabpanel',
                        id: `tabs-panel-${tab.tabId}`,
                        'aria-labelledby': `tabs-tab-${tab.tabId}`,
                    },
                    tab.content
                )
            );

            return h('div', { class: classes, 'data-ap-tabs': '' }, [
                h('div', { class: 'ap-tabs__list' }, [
                    h('ul', { role: 'tablist' }, triggerVnodes),
                ]),
                h('div', { class: 'ap-tabs__container' }, panelVnodes),
            ]);
        };
    },
});

// Standalone fallback when rendered outside a tabs parent. The tabs
// wrapper reaches into the slot vnodes and re-wraps them, so this only
// surfaces when something else renders the section in isolation.
export const TabSectionBlock = defineComponent({
    name: 'TabSectionBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const tabId = attrString(props.attributes.tabId);
            const className = attrString(props.attributes.className);
            const classes = classList(['ap-tab-section', className]);

            const attrs: Record<string, unknown> = {
                class: classes,
                role: 'tabpanel',
            };

            if (tabId !== '') {
                const safeId = sanitizeTabId(tabId, 1);
                attrs.id = `tabs-panel-${safeId}`;
                attrs['aria-labelledby'] = `tabs-tab-${safeId}`;
            }

            return h('div', attrs, slots.default ? slots.default() : []);
        };
    },
});
