/**
 * Server → editor BlockInstance hydration for the composed view.
 *
 * The applied-template endpoint returns block data as plain JSON — no
 * clientIds, no `isValid` flags, no schema defaults filled in. Feeding
 * that shape straight into `BlockEditorProvider` triggers Gutenberg's
 * validation pass (serialize→parse→compare), which fails on every
 * block and surfaces the "block contains unexpected or invalid content"
 * warning + Attempt Recovery UI.
 *
 * Running the tree through `wp.blocks.createBlock` gives every block:
 *   - a fresh `clientId`,
 *   - schema defaults merged into `attributes`,
 *   - `isValid: true`,
 *   - proper block-type registration lookup.
 *
 * Unregistered block types (blocks the host hasn't loaded) fall through
 * to `core/missing` via createBlock's built-in handling.
 *
 * @since 1.1.0
 */

import { createBlock } from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';

import type { AppliedTemplate, AppliedTemplatePart } from './api';

/**
 * Rebuild the applied template with every server-sourced block converted
 * to a proper editor BlockInstance. Idempotent: passing an
 * already-hydrated template through this a second time is safe (createBlock
 * just makes fresh clientIds).
 */
export function hydrateAppliedTemplate(
    template: AppliedTemplate
): AppliedTemplate {
    return {
        ...template,
        blocks: hydrateBlocks(template.blocks),
        template_parts: hydrateParts(template.template_parts),
    };
}

function hydrateParts(
    parts: Readonly<Record<string, AppliedTemplatePart>>
): Readonly<Record<string, AppliedTemplatePart>> {
    const out: Record<string, AppliedTemplatePart> = {};

    for (const [slug, part] of Object.entries(parts)) {
        out[slug] = {
            ...part,
            blocks: hydrateBlocks(part.blocks),
        };
    }

    return out;
}

function hydrateBlocks(
    blocks: readonly BlockInstance[]
): BlockInstance[] {
    const out: BlockInstance[] = [];

    for (const block of blocks) {
        const attrs = normalizeAttributes(block.attributes);
        const innerBlocks =
            block.innerBlocks.length > 0
                ? hydrateBlocks(block.innerBlocks)
                : [];

        try {
            out.push(createBlock(block.name, attrs, innerBlocks));
        } catch {
            // createBlock throws for genuinely unknown types on older
            // Gutenberg builds. Fall back to a manually-shaped instance
            // with `isValid: true` so the tree still renders as chrome.
            out.push({
                clientId: fallbackClientId(),
                name: block.name,
                isValid: true,
                attributes: attrs,
                innerBlocks,
            } as unknown as BlockInstance);
        }
    }

    return out;
}

/**
 * PHP `json_encode` on an empty PHP array emits `[]` rather than `{}`,
 * which types as `unknown[]` in TS. `createBlock` expects an object for
 * `attributes` — a stray array trips its schema merger. Coerce those
 * back to a plain object here.
 */
function normalizeAttributes(attrs: unknown): Record<string, unknown> {
    if (attrs === null || attrs === undefined) {
        return {};
    }

    if (Array.isArray(attrs)) {
        return {};
    }

    if (typeof attrs !== 'object') {
        return {};
    }

    return { ...(attrs as Record<string, unknown>) };
}

// Non-zero-collision id used only when createBlock fails. Not for
// production block trees; `createBlock` is the intended path.
let fallbackCounter = 0;
function fallbackClientId(): string {
    fallbackCounter += 1;
    return `ap-composed-fallback-${fallbackCounter}`;
}
