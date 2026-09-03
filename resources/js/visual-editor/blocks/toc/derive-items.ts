/**
 * Client-side heading extractor for the TOC block's WYSIWYG preview
 * (#760).
 *
 * Walks the editor's live block tree, finds every `core/heading` /
 * `artisanpack/heading`, and returns the same `{level, text, anchor}`
 * rows the server-side `TocResolver` stamps at render time. Slugs are
 * generated with the same algorithm as
 * `blocks/heading/autogenerate-anchors.ts` (unicode letter/number
 * classes, hyphenated, lowercased) plus a per-tree uniqueness suffix
 * (`-1`, `-2`, …) so the preview matches what the frontend will emit
 * for a document without any author-set anchors.
 *
 * When a heading carries an author-set `anchor` attribute the preview
 * uses it verbatim (matching the server's behavior), and later
 * duplicates get suffixed against it.
 *
 * Known limitation: in the site editor a `post-content` block is a
 * placeholder for the eventual post body — the actual post's block
 * tree is not in the block-editor store. The server-side `TocResolver`
 * fills that gap by parsing headings out of `_resolvedContent` at
 * render time, so the frontend output is always correct; the editor
 * preview inside a template just cannot show post-body headings.
 */

import removeAccents from 'remove-accents';

export interface HeadingItem {
    readonly level: number;
    readonly text: string;
    readonly anchor: string;
}

interface EditorBlock {
    readonly name: string;
    readonly attributes: Record<string, unknown>;
    readonly innerBlocks?: ReadonlyArray<EditorBlock>;
}

const HEADING_BLOCKS = new Set<string>(['core/heading', 'artisanpack/heading']);

function plainText(html: string): string {
    if (typeof document === 'undefined') {
        // SSR / test environments without a DOM — fall back to a naive
        // strip so the preview still shows something reasonable.
        return html.replace(/<[^>]*>/g, '').trim();
    }

    const shell = document.createElement('div');
    shell.innerHTML = html;

    return (shell.innerText ?? shell.textContent ?? '').trim();
}

function slug(content: string): string {
    const text = plainText(content);

    if (text === '') {
        return '';
    }

    return removeAccents(text)
        .replace(/[^\p{L}\p{N}]+/gu, '-')
        .toLowerCase()
        .replace(/(^-+)|(-+$)/g, '');
}

function uniqueSlug(base: string, used: Set<string>): string {
    if (!used.has(base)) {
        return base;
    }

    let i = 1;

    while (used.has(`${base}-${i}`)) {
        i += 1;
    }

    return `${base}-${i}`;
}

function coerceString(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

function coerceLevel(value: unknown): number {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return Math.round(value);
    }

    const numeric = Number(value);

    return Number.isFinite(numeric) ? Math.round(numeric) : 2;
}

/**
 * Walk a block tree in document order, extract every heading, and
 * assign unique anchors. Author-set anchors are preserved and claim
 * their slug against later duplicates.
 */
export function deriveHeadingItems(blocks: ReadonlyArray<EditorBlock>): ReadonlyArray<HeadingItem> {
    const used: Set<string> = new Set();
    const items: HeadingItem[] = [];

    walk(blocks, used, items);

    return items;
}

function walk(blocks: ReadonlyArray<EditorBlock>, used: Set<string>, items: HeadingItem[]): void {
    for (const block of blocks) {
        if (block === null || typeof block !== 'object') {
            continue;
        }

        if (HEADING_BLOCKS.has(block.name)) {
            const content = coerceString(block.attributes?.content);
            const rawLevel = coerceLevel(block.attributes?.level);
            const level = rawLevel < 1 ? 1 : rawLevel > 6 ? 6 : rawLevel;

            const authorAnchor = coerceString(block.attributes?.anchor).trim();

            let anchor: string;

            if (authorAnchor !== '') {
                used.add(authorAnchor);
                anchor = authorAnchor;
            } else {
                const base = slug(content);

                if (base === '') {
                    // Heading with punctuation-only text — skip; matches
                    // the server-side resolver.
                    continue;
                }

                anchor = uniqueSlug(base, used);
                used.add(anchor);
            }

            items.push({
                level,
                text: plainText(content),
                anchor,
            });
        }

        if (Array.isArray(block.innerBlocks) && block.innerBlocks.length > 0) {
            walk(block.innerBlocks, used, items);
        }
    }
}

/**
 * Filter a derived heading list to just the entries whose level falls
 * within the block's `minLevel` / `maxLevel` range, swapping the range
 * bounds when they are inverted so the preview never renders empty
 * for a fixable configuration mistake.
 */
export function filterItemsByLevel(
    items: ReadonlyArray<HeadingItem>,
    minLevel: number,
    maxLevel: number
): ReadonlyArray<HeadingItem> {
    const lo = Math.min(minLevel, maxLevel);
    const hi = Math.max(minLevel, maxLevel);

    return items.filter((item) => item.level >= lo && item.level <= hi);
}
