/**
 * Tab ids — Vue renderer (#1057).
 *
 * Mirrors `TabIdStamper` on the PHP side. Tabs print each section's id
 * into `id`, `href`, `aria-controls` and `aria-labelledby`, so two Tabs
 * blocks with the same `tabId` values, or with empty ones that fall back
 * to `tab-N`, would repeat element ids. This pass walks the tree in
 * document order and stamps each section with an id unique across the
 * tree; a repeat takes a `-2`, `-3`, … suffix.
 *
 * Ids are unique per tree: two trees rendered on one page can repeat one.
 * Blade keeps them unique across a whole request.
 *
 * The mirror lives at the matching path in the React renderer.
 *
 * @package @artisanpack-ui/visual-editor-renderer-vue
 * @since 1.20.0
 */

import type { Block } from '../types';
import { attrString } from './attributes';

/** Attribute holding a tab section's stamped id. */
export const TAB_ID_ATTRIBUTE = '_resolvedTabId';

/**
 * Normalize a tab id so it's safe to interpolate into HTML id /
 * aria-controls / fragment href values. Mirrors the editor-side
 * slugify, with a positional fallback when the result is empty.
 */
export function sanitizeTabId(raw: string, sectionIndex: number): string {
    // ASCII-only lowercase, like the Blade partial's `strtolower()`.
    const slug = raw
        .replace(/[A-Z]/g, (char) => char.toLowerCase())
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/^-+|-+$/g, '');
    return slug === '' ? `tab-${sectionIndex}` : slug;
}

/** Claim an id, adding a numeric suffix when it's taken. */
export function uniqueTabId(base: string, used: Set<string>): string {
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

/**
 * Stamp every tab section in a tree with a unique id, overwriting any
 * stored value so content can't set one. Mirrors `TabIdStamper::stamp()`.
 */
export function stampTabIds(tree: Block[]): Block[] {
    const used = new Set<string>();

    function walk(nodes: Block[]): Block[] {
        return nodes.map((block) => {
            if (block === null || typeof block !== 'object' || !Array.isArray(block.innerBlocks)) {
                return block;
            }

            const innerBlocks = block.name === 'artisanpack/tabs'
                ? walkSections(block.innerBlocks as Block[])
                : walk(block.innerBlocks as Block[]);

            return { ...block, innerBlocks } as Block;
        });
    }

    function walkSections(nodes: Block[]): Block[] {
        let sectionIndex = 0;

        return nodes.map((block) => {
            if (block === null || typeof block !== 'object' || block.name !== 'artisanpack/tab-section') {
                return block;
            }

            sectionIndex += 1;

            const raw = (block as { attributes?: unknown }).attributes;
            const attributes =
                raw !== null && typeof raw === 'object' && !Array.isArray(raw) ? (raw as Record<string, unknown>) : {};
            const tabId = uniqueTabId(sanitizeTabId(attrString(attributes.tabId), sectionIndex), used);

            // Claim the section before walking into it, so a Tabs block
            // nested here numbers after this section and before the next.
            return {
                ...block,
                attributes: { ...attributes, [TAB_ID_ATTRIBUTE]: tabId },
                ...(Array.isArray(block.innerBlocks) ? { innerBlocks: walk(block.innerBlocks as Block[]) } : {}),
            } as Block;
        });
    }

    return walk(tree);
}
