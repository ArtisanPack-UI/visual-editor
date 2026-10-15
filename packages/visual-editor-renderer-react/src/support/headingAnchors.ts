/**
 * Heading anchors — React renderer (#888).
 *
 * Mirrors the heading pass of `TocResolver` on the PHP side: every
 * `core/heading` / `artisanpack/heading` block without an author-set
 * `anchor` gets one generated from its text, unique across the tree
 * (duplicates take a `-1`, `-2`, … suffix). Author anchors are kept and
 * claim their rendered (sanitized) id first-come, in document order,
 * exactly as Blade does, so every renderer prints the same `id` on every
 * heading and no two headings share one.
 *
 * Post-content `_resolvedContent` HTML takes part too, as in Blade's
 * `stampContentHeadings()`: an `<h1>`–`<h6>` that already has an `id`
 * claims it, and one without gets a unique auto anchor injected.
 *
 * Out of scope here (still server-side only): the `_resolvedItems` list
 * on `artisanpack/toc` blocks.
 *
 * Anchors are unique per tree: two trees rendered on one page can repeat
 * an id.
 *
 * The mirror lives at the matching path in the Vue renderer.
 *
 * @package @artisanpack-ui/visual-editor-renderer-react
 * @since 1.15.0
 */

import type { Block } from '../types';
import { phpTrim } from './attributes';
import { NAMED_ENTITY_CODE_POINTS } from './htmlEntities';

const HEADING_BLOCKS = ['core/heading', 'artisanpack/heading'];

const POST_CONTENT_BLOCKS = ['core/post-content', 'artisanpack/post-content'];

/**
 * One `<h1>`–`<h6>` tag: level, attribute string, inner HTML. Mirrors
 * `TocResolver::HEADING_TAG_PATTERN`.
 */
const HEADING_TAG_PATTERN = /<h([1-6])([^>]*)>([\s\S]*?)<\/h\1>/gi;

/** Mirrors `TocResolver::headingId()`. */
const HEADING_ID_PATTERN = /\bid\s*=\s*(["'])([^"']+)\1/i;

/**
 * The auto anchor for a heading whose text slugs only to characters an
 * `id` can't keep (e.g. `日本語`). Mirrors `TocResolver::FALLBACK_SLUG`.
 */
export const HEADING_FALLBACK_SLUG = 'heading';

/**
 * Walk a tree and stamp an auto-generated `anchor` onto every heading
 * block that lacks one, and an `id` onto every post-content heading that
 * lacks one. Mirrors `TocResolver::stampHeadings()`.
 */
export function stampHeadingAnchors(tree: Block[]): Block[] {
    const used = new Set<string>();

    function walk(nodes: Block[]): Block[] {
        return nodes.map((block) => {
            if (block === null || typeof block !== 'object') {
                return block;
            }

            const stamped = stampBlock(block, used);

            if (!Array.isArray(stamped.innerBlocks)) {
                return stamped;
            }

            return { ...stamped, innerBlocks: walk(stamped.innerBlocks as Block[]) } as Block;
        });
    }

    return walk(tree);
}

/**
 * Slug a heading's stored content into an HTML-id-safe anchor. Mirrors
 * `TocResolver::slug()` (and the editor's `autogenerate-anchors.ts`):
 * plain text, accents removed, runs of non-letter/non-number characters
 * collapsed to `-`, lowercased, and trimmed of `-`. The result is then
 * reduced to the characters a rendered `id` keeps (`[A-Za-z0-9_:.-]`,
 * see {@link resolveAnchor}) with runs of `-` collapsed and trimmed, so
 * the uniqueness check runs on the id that is actually printed. Text
 * that leaves nothing behind (`日本語`) falls back to
 * {@link HEADING_FALLBACK_SLUG}; text with no letters or numbers at all
 * (`???`) gives `''`, and the heading gets no anchor.
 */
export function headingSlug(content: string): string {
    const text = plainText(content);

    if (text === '') {
        return '';
    }

    const slug = text
        .normalize('NFD')
        .replace(/\p{Mn}+/gu, '')
        .replace(/[^\p{L}\p{N}]+/gu, '-')
        .toLowerCase()
        .replace(/^-+|-+$/g, '');

    if (slug === '') {
        return '';
    }

    const safe = slug
        .replace(/[^A-Za-z0-9_:.-]/g, '')
        .replace(/-{2,}/g, '-')
        .replace(/^-+|-+$/g, '');

    return safe === '' ? HEADING_FALLBACK_SLUG : safe;
}

function stampBlock(block: Block, used: Set<string>): Block {
    if (typeof block.name !== 'string') {
        return block;
    }

    const raw = (block as { attributes?: unknown }).attributes;
    const attributes =
        raw !== null && typeof raw === 'object' && !Array.isArray(raw) ? (raw as Record<string, unknown>) : {};

    if (POST_CONTENT_BLOCKS.includes(block.name)) {
        return stampPostContent(block, attributes, used);
    }

    if (!HEADING_BLOCKS.includes(block.name)) {
        return block;
    }

    // The id the heading renders, not the raw value: `foo bar` renders
    // `foobar`, so that is what a later auto anchor must avoid. An anchor
    // that sanitizes to nothing renders no id and is replaced.
    const existing = resolveAnchor(attributes.anchor) ?? '';

    if (existing !== '') {
        used.add(existing);

        return block;
    }

    const slug = headingSlug(typeof attributes.content === 'string' ? attributes.content : '');

    if (slug === '') {
        return block;
    }

    const anchor = uniqueSlug(slug, used);
    used.add(anchor);

    return { ...block, attributes: { ...attributes, anchor } } as Block;
}

/**
 * Claim the ids of the headings in a post-content block's resolved HTML,
 * in document order, and inject a unique auto anchor on each heading
 * that has none. Mirrors `TocResolver::stampContentHeadings()`.
 */
function stampPostContent(block: Block, attributes: Record<string, unknown>, used: Set<string>): Block {
    const content = attributes._resolvedContent;

    if (typeof content !== 'string' || content === '') {
        return block;
    }

    const rewritten = content.replace(
        HEADING_TAG_PATTERN,
        (match: string, level: string, attrs: string, inner: string) => {
            if (plainText(inner) === '') {
                return match;
            }

            const existing = HEADING_ID_PATTERN.exec(attrs);

            if (existing !== null) {
                used.add(existing[2]);

                return match;
            }

            const slug = headingSlug(inner);

            if (slug === '') {
                return match;
            }

            const anchor = uniqueSlug(slug, used);
            used.add(anchor);

            return `<h${level} id="${escapeAttribute(anchor)}"${attrs}>${inner}</h${level}>`;
        }
    );

    return rewritten === content
        ? block
        : ({ ...block, attributes: { ...attributes, _resolvedContent: rewritten } } as Block);
}

function uniqueSlug(slug: string, used: Set<string>): string {
    if (!used.has(slug)) {
        return slug;
    }

    let i = 1;

    while (used.has(`${slug}-${i}`)) {
        i++;
    }

    return `${slug}-${i}`;
}

/** Mirrors PHP's `htmlspecialchars( …, ENT_QUOTES | ENT_HTML5 )`. */
function escapeAttribute(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

/** Strip tags, decode entities and collapse whitespace. */
function plainText(content: string): string {
    const decoded = decodeEntities(stripTags(content));

    return phpTrim(decoded.replace(/\s+/gu, ' '));
}

/**
 * Remove tags the way PHP's `strip_tags()` does, so heading text slugs
 * the same in every renderer. A `<` followed by whitespace is text
 * (`a < b > c` keeps both signs); any other `<` opens a tag that runs to
 * the matching `>` (quote-aware, nested `<` counted) or to the end of the
 * string, and `<!--` opens a comment that runs to `-->`.
 */
export function stripTags(content: string): string {
    let out = '';
    let i = 0;

    while (i < content.length) {
        const char = content[i];

        if (char !== '<' || /^[ \t\n\r\v\f]$/.test(content[i + 1] ?? '')) {
            out += char;
            i++;

            continue;
        }

        if (content.startsWith('<!--', i)) {
            const end = content.indexOf('-->', i + 4);

            if (end === -1) {
                break;
            }

            i = end + 3;

            continue;
        }

        let depth = 0;
        let quote = '';

        for (i++; i < content.length; i++) {
            const tagChar = content[i];

            if (quote !== '') {
                if (tagChar === quote) {
                    quote = '';
                }
            } else if (tagChar === '"' || tagChar === "'") {
                quote = tagChar;
            } else if (tagChar === '<') {
                depth++;
            } else if (tagChar === '>') {
                if (depth === 0) {
                    break;
                }

                depth--;
            }
        }

        i++;
    }

    return out;
}

function decodeEntities(value: string): string {
    return value.replace(/&(#\d+|#x[0-9a-f]+|[a-z][a-z0-9]*);/gi, (match, entity: string) => {
        if (entity[0] === '#') {
            const code = entity[1] === 'x' || entity[1] === 'X'
                ? Number.parseInt(entity.slice(2), 16)
                : Number.parseInt(entity.slice(1), 10);

            return code > 0 && code <= 0x10ffff ? String.fromCodePoint(code) : match;
        }

        return Object.prototype.hasOwnProperty.call(NAMED_ENTITY_CODE_POINTS, entity)
            ? String.fromCodePoint(NAMED_ENTITY_CODE_POINTS[entity])
            : match;
    });
}

/**
 * The `id` a block prints for its `anchor` attribute, or `undefined` when
 * none survives. Mirrors `BlockSupports::resolveAnchor()`: characters
 * outside `[A-Za-z0-9_:.-]` are dropped.
 */
export function resolveAnchor(value: unknown): string | undefined {
    const anchor =
        typeof value === 'string'
            ? phpTrim(value)
            : typeof value === 'number' && Number.isFinite(value)
              ? String(value)
              : '';
    const sanitized = anchor.replace(/[^A-Za-z0-9_:.-]/g, '');

    return sanitized === '' ? undefined : sanitized;
}
