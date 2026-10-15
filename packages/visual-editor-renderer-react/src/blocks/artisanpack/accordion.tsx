/**
 * React renderers for the `artisanpack/accordions` family (#497).
 * Mirrors the Blade partials and the Vue components so every renderer
 * emits identical markup.
 *
 * The accordion is the single source of truth for the panel id and
 * toggle icon. Its renderer walks the pre-rendered children, extracts
 * the title and body grandchildren by name, and re-wraps them with the
 * matching `id` / `aria-controls` / `aria-labelledby` wiring. The
 * standalone title and body renderers just emit plain wrappers so they
 * still produce reasonable markup if rendered outside an accordion.
 */

import { Children, Fragment, isValidElement, type JSX, type ReactNode } from 'react';

import { attrString, classList } from '../../support/attributes';
import type { Block, BlockRendererProps } from '../../types';

type AccordionPanelIcon = 'plus-minus' | 'arrows';

const VALID_PANEL_ICONS: ReadonlyArray<AccordionPanelIcon> = ['plus-minus', 'arrows'];

function normalizePanelIcon(value: unknown): AccordionPanelIcon {
    const raw = attrString(value, 'plus-minus');
    return (VALID_PANEL_ICONS as ReadonlyArray<string>).includes(raw)
        ? (raw as AccordionPanelIcon)
        : 'plus-minus';
}

function findChildByName(
    children: ReactNode,
    innerBlocks: Block[],
    name: string
): ReactNode {
    const index = innerBlocks.findIndex((block) => block.name === name);
    if (index === -1) {
        return null;
    }

    // `BlockTree` hands the rendered children over wrapped in a single
    // `<Fragment>`; `Children.toArray()` doesn't flatten fragments, so
    // unwrap it to index the title / body by their inner-block position.
    let childArray = Children.toArray(children);

    if (childArray.length === 1 && isValidElement(childArray[0]) && childArray[0].type === Fragment) {
        childArray = Children.toArray((childArray[0].props as { children?: ReactNode }).children);
    }

    const child = childArray[index];

    if (!isValidElement(child)) {
        return null;
    }

    // Each child renderer wraps its own grandchildren in a plain block
    // wrapper; we want the grandchildren (heading / paragraph etc),
    // not the title/body's own wrapper. Reach through `props.children`
    // to grab the pre-rendered grandchildren the BlockTree walker
    // already produced.
    const props = child.props as { children?: ReactNode };
    return props.children ?? null;
}

/**
 * The stored `className` of the first inner block with this name. The
 * accordion re-wraps its title and body, so their classes (and with
 * them the `ve-r-*` / `ve-fx-*` scopes, M-7) go on the re-wrapped
 * elements, as `accordion.blade.php` does.
 */
function childClassName(innerBlocks: Block[], name: string): string {
    const child = innerBlocks.find((block) => block.name === name);
    const attributes = child?.attributes;

    return attributes !== null && typeof attributes === 'object' && !Array.isArray(attributes)
        ? attrString((attributes as Record<string, unknown>).className)
        : '';
}

export function AccordionsBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const classes = classList(['ap-accordions', className]);

    return <div className={classes}>{children}</div>;
}

export function AccordionBlock({
    attributes,
    innerBlocks,
    children,
}: BlockRendererProps): JSX.Element {
    const panelId = attrString(attributes.panelId);
    const panelIcon = normalizePanelIcon(attributes.panelIcon);
    const className = attrString(attributes.className);
    const classes = classList(['ap-accordion', className]);

    const titleContent = findChildByName(children, innerBlocks, 'artisanpack/accordion-title');
    const bodyContent = findChildByName(children, innerBlocks, 'artisanpack/accordion-body');

    const wrapperAttrs: Record<string, unknown> = {
        className: classes,
        'data-panel-icon': panelIcon,
    };
    if (panelId !== '') {
        wrapperAttrs['data-panel-id'] = panelId;
    }

    const triggerAttrs: Record<string, unknown> = {
        className: 'ap-accordion__title-content',
        role: 'button',
        tabIndex: 0,
        'aria-expanded': 'false',
    };
    if (panelId !== '') {
        triggerAttrs.id = `${panelId}-control`;
        triggerAttrs['aria-controls'] = panelId;
    }

    const bodyAttrs: Record<string, unknown> = {
        className: classList(['ap-accordion__body', childClassName(innerBlocks, 'artisanpack/accordion-body')]),
        role: 'region',
        hidden: true,
    };
    if (panelId !== '') {
        bodyAttrs.id = panelId;
        bodyAttrs['aria-labelledby'] = `${panelId}-control`;
    }

    return (
        <div {...wrapperAttrs}>
            <div className={classList(['ap-accordion__title', childClassName(innerBlocks, 'artisanpack/accordion-title')])}>
                <div {...triggerAttrs}>{titleContent}</div>
                <span
                    className={`ap-accordion__icon ap-accordion__icon--${panelIcon}`}
                    aria-hidden="true"
                />
            </div>
            <div {...bodyAttrs}>{bodyContent}</div>
        </div>
    );
}

// Standalone fallback when rendered outside an accordion. The accordion
// wrapper reaches through `children` and rewraps them, so these only
// surface when something else renders the title/body in isolation.

export function AccordionTitleBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    return <div className={classList(['ap-accordion__title', attrString(attributes.className)])}>{children}</div>;
}

export function AccordionBodyBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    return <div className={classList(['ap-accordion__body', attrString(attributes.className)])}>{children}</div>;
}
