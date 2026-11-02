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

import { Children, Fragment, isValidElement, type JSX, type ReactNode } from 'react';

import { attrString, classList } from '../../support/attributes';
import { TAB_ID_ATTRIBUTE, sanitizeTabId, uniqueTabId } from '../../support/tabIds';
import type { Block, BlockRendererProps } from '../../types';

type TabsAlign = 'horizontal' | 'vertical';
type TabsSpacing = 'start' | 'end' | 'center' | 'equal';

const VALID_ALIGNS: ReadonlyArray<TabsAlign> = ['horizontal', 'vertical'];
const VALID_SPACINGS: ReadonlyArray<TabsSpacing> = ['start', 'end', 'center', 'equal'];

interface TabRecord {
    readonly label: string;
    readonly tabId: string;
    readonly content: ReactNode;
    /** The section's own class (custom class, block style), #922. */
    readonly className: string;
    /** The section's aria-label; replaces aria-labelledby when set (#922). */
    readonly ariaLabel: string;
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
 * The section's id. `BlockTree` stamps a tree-unique one (#1057); a Tabs
 * block rendered on its own falls back to ids unique within the block.
 */
function resolveTabId(childAttrs: Record<string, unknown>, sectionIndex: number, used: Set<string>): string {
    const stamped = attrString(childAttrs[TAB_ID_ATTRIBUTE]);

    return stamped !== ''
        ? stamped
        : uniqueTabId(sanitizeTabId(attrString(childAttrs.tabId), sectionIndex), used);
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
        'wp-block-artisanpack-tabs',
        'ap-tabs',
        `align-tabs-${tabsAlign}`,
        `space-tabs-${tabsSpacing}`,
        className,
    ]);

    // `BlockTree` hands the rendered children over wrapped in a single
    // `<Fragment>`; `Children.toArray()` doesn't flatten fragments, so
    // unwrap it to index each section by its inner-block position.
    let childArray = Children.toArray(children);

    if (childArray.length === 1 && isValidElement(childArray[0]) && childArray[0].type === Fragment) {
        childArray = Children.toArray((childArray[0].props as { children?: ReactNode }).children);
    }

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
        const tabId = resolveTabId(childAttrs, sectionIndex, usedIds);

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
            className: attrString(childAttrs.className),
            ariaLabel: attrString(childAttrs.ariaLabel).trim(),
        });
    });

    return (
        <div className={classes} data-ap-tabs="">
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
                        className={classList(['wp-block-artisanpack-tab-section', 'ap-tab-section', tab.className])}
                        role="tabpanel"
                        id={`tabs-panel-${tab.tabId}`}
                        aria-label={tab.ariaLabel === '' ? undefined : tab.ariaLabel}
                        aria-labelledby={tab.ariaLabel === '' ? `tabs-tab-${tab.tabId}` : undefined}
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
    const classes = classList(['wp-block-artisanpack-tab-section', 'ap-tab-section', className]);

    const wrapperProps: Record<string, unknown> = {
        className: classes,
        role: 'tabpanel',
    };

    if (tabId !== '') {
        const safeId = sanitizeTabId(tabId, 1);
        wrapperProps.id = `tabs-panel-${safeId}`;
        wrapperProps['aria-labelledby'] = `tabs-tab-${safeId}`;
    }

    const ariaLabel = attrString(attributes.ariaLabel).trim();

    if (ariaLabel !== '') {
        delete wrapperProps['aria-labelledby'];
        wrapperProps['aria-label'] = ariaLabel;
    }

    return <div {...wrapperProps}>{children}</div>;
}
