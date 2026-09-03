/**
 * Vue renderer for the `artisanpack/toc` block (#760).
 *
 * Mirrors the React renderer: when `_resolvedItems` is present in the
 * incoming tree (a fully-resolved payload passed in from a Blade-side
 * render), the same nested list is emitted; otherwise the block
 * degrades to a landmark with the optional heading and a placeholder.
 */

import { defineComponent, h } from 'vue';

import { attrBoolean, attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

interface TocItem {
    level: number;
    text: string;
    anchor: string;
    children: TocItem[];
}

function clampLabelLevel(value: unknown): 2 | 3 | 4 | 5 | 6 {
    const numeric = typeof value === 'number' ? value : Number(value);

    if (!Number.isFinite(numeric)) {
        return 2;
    }

    const rounded = Math.round(numeric);

    if (rounded <= 2) {
        return 2;
    }

    if (rounded >= 6) {
        return 6;
    }

    return rounded as 2 | 3 | 4 | 5 | 6;
}

function normalizeItems(value: unknown): TocItem[] {
    if (!Array.isArray(value)) {
        return [];
    }

    const items: TocItem[] = [];

    for (const entry of value) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const raw = entry as Record<string, unknown>;
        const anchor = attrString(raw.anchor);

        if (anchor.trim() === '') {
            continue;
        }

        const levelRaw = raw.level;
        const level = typeof levelRaw === 'number' ? Math.round(levelRaw) : Number(levelRaw) || 0;

        items.push({
            level,
            text: attrString(raw.text),
            anchor,
            children: [],
        });
    }

    return items;
}

function buildTree(flat: TocItem[]): TocItem[] {
    const root: TocItem[] = [];
    const stack: TocItem[][] = [root];
    const levels: number[] = [0];

    for (const entry of flat) {
        while (levels.length > 1 && levels[levels.length - 1] >= entry.level) {
            stack.pop();
            levels.pop();
        }

        const node: TocItem = { ...entry, children: [] };
        const parent = stack[stack.length - 1];
        parent.push(node);
        stack.push(node.children);
        levels.push(entry.level);
    }

    return root;
}

type VNode = ReturnType<typeof h>;

function renderList(nodes: TocItem[], listTag: 'ol' | 'ul'): VNode {
    return h(
        listTag,
        { class: 'ap-toc__list' },
        nodes.map((node, index) =>
            h(
                'li',
                { key: `${node.anchor}-${index}`, class: 'ap-toc__item' },
                [
                    h(
                        'a',
                        { class: 'ap-toc__link', href: `#${node.anchor}` },
                        node.text,
                    ),
                    node.children.length > 0 ? renderList(node.children, listTag) : null,
                ],
            ),
        ),
    );
}

export const TocBlock = defineComponent({
    name: 'TocBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const heading = attrString(props.attributes.heading);
            const level = clampLabelLevel(props.attributes.headingLevel);
            const ordered = attrBoolean(props.attributes.ordered, false);
            const className = attrString(props.attributes.className);

            const classes = classList(['ap-toc', className]);

            const flat = normalizeItems(props.attributes._resolvedItems);
            const tree = buildTree(flat);
            const listTag: 'ol' | 'ul' = ordered ? 'ol' : 'ul';

            const children: VNode[] = [];

            if (heading !== '') {
                children.push(
                    h(`h${level}`, {
                        class: 'ap-toc__heading',
                        innerHTML: heading,
                    }),
                );
            }

            if (tree.length === 0) {
                children.push(
                    h(
                        'p',
                        { class: 'ap-toc__placeholder' },
                        'No headings found on this page yet.',
                    ),
                );
            } else {
                children.push(renderList(tree, listTag));
            }

            // A block-level ariaLabel (from `supports.ariaLabel`) wins
            // over the heading-derived label; fall back to the English
            // default when neither is set.
            const customLabel = attrString(props.attributes.ariaLabel).trim();
            const ariaLabel = customLabel !== ''
                ? customLabel
                : heading.trim() !== ''
                    ? heading.replace(/<[^>]*>/g, '')
                    : 'Table of contents';

            return h(
                'nav',
                {
                    class: classes,
                    'aria-label': ariaLabel,
                },
                children,
            );
        };
    },
});
