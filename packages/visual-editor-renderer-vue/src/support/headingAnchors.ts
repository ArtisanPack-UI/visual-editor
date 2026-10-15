/**
 * Heading anchors — Vue renderer (#888).
 *
 * Mirrors the heading pass of `TocResolver` on the PHP side: every
 * `core/heading` / `artisanpack/heading` block without an author-set
 * `anchor` gets one generated from its text, unique across the tree
 * (duplicates take a `-1`, `-2`, … suffix). Author anchors are kept and
 * claim their slug first-come, in document order, exactly as Blade does,
 * so both renderers print the same `id` on every heading.
 *
 * Out of scope here (still server-side only): ids injected into
 * post-content `_resolvedContent` HTML and the `_resolvedItems` list on
 * `artisanpack/toc` blocks.
 *
 * The mirror lives at the matching path in the React renderer.
 *
 * @package @artisanpack-ui/visual-editor-renderer-vue
 * @since 1.15.0
 */

import type { Block } from '../types';
import { phpTrim } from './attributes';

const HEADING_BLOCKS = ['core/heading', 'artisanpack/heading'];

/**
 * Named entities decoded before slugging. `TocResolver` runs PHP's full
 * `html_entity_decode()`; rich text only stores these few as entities
 * (everything else is saved as UTF-8), so this set covers real content.
 */
const NAMED_ENTITIES: Readonly<Record<string, string>> = {
    amp: '&',
    lt: '<',
    gt: '>',
    quot: '"',
    apos: "'",
    nbsp: ' ',
    ndash: '–',
    mdash: '—',
    hellip: '…',
    lsquo: '‘',
    rsquo: '’',
    ldquo: '“',
    rdquo: '”',
};

/**
 * Walk a tree and stamp an auto-generated `anchor` onto every heading
 * block that lacks one. Mirrors `TocResolver::stampHeadings()`.
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
 * collapsed to `-`, lowercased, and trimmed of `-`.
 */
export function headingSlug(content: string): string {
    const text = plainText(content);

    if (text === '') {
        return '';
    }

    return text
        .normalize('NFD')
        .replace(/\p{Mn}+/gu, '')
        .replace(/[^\p{L}\p{N}]+/gu, '-')
        .toLowerCase()
        .replace(/^-+|-+$/g, '');
}

function stampBlock(block: Block, used: Set<string>): Block {
    if (typeof block.name !== 'string' || !HEADING_BLOCKS.includes(block.name)) {
        return block;
    }

    const raw = (block as { attributes?: unknown }).attributes;
    const attributes =
        raw !== null && typeof raw === 'object' && !Array.isArray(raw) ? (raw as Record<string, unknown>) : {};
    const existing = typeof attributes.anchor === 'string' ? phpTrim(attributes.anchor) : '';

    if (existing !== '') {
        // Claim the slug so a later auto-generated anchor can't collide
        // with an author-set one.
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

/** Strip tags, decode entities and collapse whitespace. */
function plainText(content: string): string {
    const decoded = decodeEntities(content.replace(/<[^>]*>/g, ''));

    return phpTrim(decoded.replace(/\s+/gu, ' '));
}

function decodeEntities(value: string): string {
    return value.replace(/&(#\d+|#x[0-9a-f]+|[a-z]+);/gi, (match, entity: string) => {
        if (entity[0] === '#') {
            const code = entity[1] === 'x' || entity[1] === 'X'
                ? Number.parseInt(entity.slice(2), 16)
                : Number.parseInt(entity.slice(1), 10);

            return code > 0 && code <= 0x10ffff ? String.fromCodePoint(code) : match;
        }

        return Object.prototype.hasOwnProperty.call(NAMED_ENTITIES, entity) ? NAMED_ENTITIES[entity] : match;
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
