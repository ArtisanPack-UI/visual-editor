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
 *
 * Each row records whether its heading sits inside a Post Content block,
 * so `filterItemsBySource()` can apply the block's `source` attribute the
 * way `TocResolver::stampTocBlock()` does.
 */

import removeAccents from 'remove-accents';

export interface HeadingItem {
    readonly level: number;
    readonly text: string;
    readonly anchor: string;
    readonly inContent: boolean;
}

export type TocSource = 'page' | 'post-content';

interface EditorBlock {
    readonly name: string;
    readonly attributes: Record<string, unknown>;
    readonly innerBlocks?: ReadonlyArray<EditorBlock>;
}

const HEADING_BLOCKS = new Set<string>(['core/heading', 'artisanpack/heading']);
const POST_CONTENT_BLOCKS = new Set<string>(['core/post-content', 'artisanpack/post-content']);

// Blocks that show other entries than the one being edited. Post Content
// inside one of them is another post's body, so it doesn't count as "the
// post". Mirrors `TocResolver::ENTRY_LOOP_BLOCKS`.
const ENTRY_LOOP_BLOCKS = new Set<string>([
    'core/query',
    'artisanpack/query',
    'artisanpack/related-posts',
    'artisanpack/single-content',
]);

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

    walk(blocks, used, items, false, false);

    return items;
}

function walk(
    blocks: ReadonlyArray<EditorBlock>,
    used: Set<string>,
    items: HeadingItem[],
    inContent: boolean,
    inEntryLoop: boolean
): void {
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
                inContent,
            });
        }

        if (Array.isArray(block.innerBlocks) && block.innerBlocks.length > 0) {
            const childInEntryLoop = inEntryLoop || ENTRY_LOOP_BLOCKS.has(block.name);
            const childInContent = inContent || (!childInEntryLoop && POST_CONTENT_BLOCKS.has(block.name));

            walk(block.innerBlocks, used, items, childInContent, childInEntryLoop);
        }
    }
}

/**
 * Whether the tree holds a Post Content block at any depth, outside the
 * entry loops. Mirrors `TocResolver::containsPostContent()`.
 */
export function containsPostContent(blocks: ReadonlyArray<EditorBlock>): boolean {
    return blocks.some((block) => {
        if (block === null || typeof block !== 'object') {
            return false;
        }

        if (POST_CONTENT_BLOCKS.has(block.name)) {
            return true;
        }

        if (ENTRY_LOOP_BLOCKS.has(block.name)) {
            return false;
        }

        return Array.isArray(block.innerBlocks) && containsPostContent(block.innerBlocks);
    });
}

/**
 * Apply the block's `source` attribute: `post-content` keeps only the
 * headings inside Post Content. A tree with no Post Content block (the
 * post editor, where the TOC sits in the post body) keeps every heading,
 * as the server does.
 */
export function filterItemsBySource(
    items: ReadonlyArray<HeadingItem>,
    source: TocSource,
    hasPostContent: boolean
): ReadonlyArray<HeadingItem> {
    if (source !== 'post-content' || !hasPostContent) {
        return items;
    }

    return items.filter((item) => item.inContent);
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
