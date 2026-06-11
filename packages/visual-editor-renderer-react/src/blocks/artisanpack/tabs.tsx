/**
 * React renderers for the `artisanpack/tabs` family (#497).
 *
 * Tab triggers are derived from the inner-block tab-sections — each
 * section owns its own `label` and `tabId`. The tabs renderer walks
 * `innerBlocks` to build the tablist AND re-wraps each tab-section's
 * pre-rendered grandchildren with the SAME resolved tabId, so the
 * trigger `aria-controls` always lines up with the panel `id` even
 * when the persisted tabId is missing and a positional fallback kicks
 * in.
 */

import { Children, isValidElement, type JSX, type ReactNode } from 'react';

import { attrString, classList } from '../../support/attributes';
import type { Block, BlockRendererProps } from '../../types';

type TabsAlign = 'horizontal' | 'vertical';
type TabsSpacing = 'start' | 'end' | 'center' | 'equal';

const VALID_ALIGNS: ReadonlyArray<TabsAlign> = ['horizontal', 'vertical'];
const VALID_SPACINGS: ReadonlyArray<TabsSpacing> = ['start', 'end', 'center', 'equal'];

interface TabRecord {
    readonly label: string;
    readonly tabId: string;
    readonly content: ReactNode;
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

/**
 * Normalize a tab id so it's safe to interpolate into HTML id /
 * aria-controls / fragment href values. Mirrors the editor-side
 * slugify, with a positional fallback when the result is empty.
 */
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

export function TabsBlock({
    attributes,
    innerBlocks,
    children,
}: BlockRendererProps): JSX.Element {
    const tabsAlign = normalizeAlign(attributes.tabsAlign);
    const tabsSpacing = normalizeSpacing(attributes.tabsSpacing);
    const className = attrString(attributes.className);

    const classes = classList([
        'ap-tabs',
        `align-tabs-${tabsAlign}`,
        `space-tabs-${tabsSpacing}`,
        className,
    ]);

    const childArray = Children.toArray(children);

    const tabs: TabRecord[] = [];
    const usedIds = new Set<string>();
    let sectionIndex = 0;
    innerBlocks.forEach((child: Block, index: number) => {
        if (child.name !== 'artisanpack/tab-section') {
            return;
        }
        sectionIndex += 1;
        const childAttrs = (child.attributes ?? {}) as Record<string, unknown>;
        const labelRaw = attrString(childAttrs.label);
        const baseId = sanitizeTabId(attrString(childAttrs.tabId), sectionIndex);
        const tabId = uniqueTabId(baseId, usedIds);

        // Reach through the pre-rendered child element to grab its
        // grandchildren (the paragraph, group, … the tab-section
        // wraps). Re-wrapping them here lets us stamp the resolved
        // tabId onto the panel so the trigger anchor always lines up.
        // `childArray[index]` uses the raw innerBlocks index so
        // non-tab-section siblings stay paired with their own VNode.
        const childEl = childArray[index];
        const content = isValidElement(childEl)
            ? (childEl.props as { children?: ReactNode }).children ?? null
            : null;

        tabs.push({
            label: labelRaw === '' ? `Tab ${sectionIndex}` : labelRaw,
            tabId,
            content,
        });
    });

    return (
        <div className={classes} data-ap-tabs>
            <div className="ap-tabs__list">
                <ul role="tablist">
                    {tabs.map((tab, index) => {
                        const isFirst = index === 0;
                        return (
                            <li key={`${index}-${tab.tabId}`}>
                                <a
                                    href={`#tabs-panel-${tab.tabId}`}
                                    id={`tabs-tab-${tab.tabId}`}
                                    role="tab"
                                    aria-controls={`tabs-panel-${tab.tabId}`}
                                    aria-selected={isFirst ? 'true' : 'false'}
                                    tabIndex={isFirst ? 0 : -1}
                                >
                                    {tab.label}
                                </a>
                            </li>
                        );
                    })}
                </ul>
            </div>
            <div className="ap-tabs__container">
                {tabs.map((tab, index) => (
                    <div
                        key={`panel-${index}-${tab.tabId}`}
                        className="ap-tab-section"
                        role="tabpanel"
                        id={`tabs-panel-${tab.tabId}`}
                        aria-labelledby={`tabs-tab-${tab.tabId}`}
                    >
                        {tab.content}
                    </div>
                ))}
            </div>
        </div>
    );
}

// Standalone fallback when rendered outside a tabs parent. The tabs
// wrapper reaches through `children` and rewraps them, so this only
// surfaces when something else renders the section in isolation.
export function TabSectionBlock({
    attributes,
    children,
}: BlockRendererProps): JSX.Element {
    const tabId = attrString(attributes.tabId);
    const className = attrString(attributes.className);
    const classes = classList(['ap-tab-section', className]);

    const wrapperProps: Record<string, unknown> = {
        className: classes,
        role: 'tabpanel',
    };

    if (tabId !== '') {
        const safeId = sanitizeTabId(tabId, 1);
        wrapperProps.id = `tabs-panel-${safeId}`;
        wrapperProps['aria-labelledby'] = `tabs-tab-${safeId}`;
    }

    return <div {...wrapperProps}>{children}</div>;
}
