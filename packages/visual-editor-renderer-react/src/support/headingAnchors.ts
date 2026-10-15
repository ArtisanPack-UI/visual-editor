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
 * Out of scope here (still server-side only): ids injected into
 * post-content `_resolvedContent` HTML and the `_resolvedItems` list on
 * `artisanpack/toc` blocks.
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
    // The id the heading renders, not the raw value: `foo bar` renders
    // `foobar`, so that is what a later auto anchor must avoid. An anchor
    // that sanitizes to nothing renders no id and is replaced.
    const existing = typeof attributes.anchor === 'string' ? (resolveAnchor(attributes.anchor) ?? '') : '';

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
