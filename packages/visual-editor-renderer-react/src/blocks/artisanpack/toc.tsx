/**
 * React renderer for the `artisanpack/toc` block (#760).
 *
 * The item list is derived server-side by `TocResolver` and stamped
 * onto the block's `_resolvedItems` attribute. When those items are
 * present in the incoming tree (e.g. because the host serialized the
 * fully-resolved tree from the Blade side into a JSON payload for a
 * hybrid React frontend), we render the same nested list the Blade
 * partial produces; when they are not, we emit just the optional label
 * plus a placeholder so downstream head managers and hydration paths
 * still see a stable landmark.
 */

import { attrBoolean, attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

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

function normalizeItems(value: unknown): ReadonlyArray<TocItem> {
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

function buildTree(flat: ReadonlyArray<TocItem>): TocItem[] {
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

function renderList(nodes: ReadonlyArray<TocItem>, ordered: boolean): JSX.Element {
    const ListTag = ordered ? 'ol' : 'ul';

    return (
        <ListTag className="ap-toc__list">
            {nodes.map((node, index) => (
                <li key={`${node.anchor}-${index}`} className="ap-toc__item">
                    <a className="ap-toc__link" href={`#${node.anchor}`}>{node.text}</a>
                    {node.children.length > 0 && renderList(node.children, ordered)}
                </li>
            ))}
        </ListTag>
    );
}

export function TocBlock({ attributes }: BlockRendererProps): JSX.Element {
    const heading = attrString(attributes.heading);
    const level = clampLabelLevel(attributes.headingLevel);
    const ordered = attrBoolean(attributes.ordered, false);
    const className = attrString(attributes.className);

    const classes = classList(['ap-toc', className]);

    const HeadingTag = `h${level}` as 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    const flat = normalizeItems(attributes._resolvedItems);
    const tree = buildTree(flat);

    // A block-level ariaLabel (from the block editor's `supports.ariaLabel`
    // panel) wins over the heading-derived label; falls back to the
    // English default when neither is set. The label is rendered as
    // plain text — strip any RichText markup the heading may carry.
    const customLabel = attrString(attributes.ariaLabel).trim();
    const ariaLabel = customLabel !== ''
        ? customLabel
        : heading.trim() !== ''
            ? heading.replace(/<[^>]*>/g, '')
            : 'Table of contents';

    return (
        <nav className={classes} aria-label={ariaLabel}>
            {heading !== '' && (
                <HeadingTag
                    className="ap-toc__heading"
                    dangerouslySetInnerHTML={{ __html: heading }}
                />
            )}
            {tree.length === 0
                ? <p className="ap-toc__placeholder">No headings found on this page yet.</p>
                : renderList(tree, ordered)}
        </nav>
    );
}
