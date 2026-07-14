/**
 * Composition logic for the post-editor's composed view (#621).
 *
 * Takes the content model's own block list plus the resolved applied template
 * and produces a display tree where:
 *
 *   - Every non-content block from the template renders with a `lock`
 *     attribute (`{ move: true, remove: true, edit: true }`) so it appears in
 *     the canvas but can't be selected, dragged, or edited.
 *   - `core/template-part` refs are expanded inline from the response's
 *     `template_parts` map so nested parts (header/footer/etc) render at
 *     the correct positions.
 *   - The template's content slot — the first `core/post-content` block
 *     reachable — is replaced by the content list *unlocked* so all inline
 *     editing continues to work against the model's real blocks.
 *
 * A pure function so it's cheap to memoize against `(content, template)`
 * identity in the editor. Inverse extraction lives in `extract.ts` — the
 * two functions round-trip a block list without loss.
 *
 * @since 1.1.0
 */

import type { BlockInstance } from '@wordpress/blocks';

import type { AppliedTemplate, AppliedTemplatePart } from './api';

/**
 * Sentinel `attributes` key stamped onto every block the composition
 * introduces (template chrome + expanded parts). Used by `extract.ts` to
 * distinguish chrome blocks from user content blocks when rebuilding the
 * content tree on save.
 */
export const COMPOSED_CHROME_MARKER = '__apVisualEditorChrome';

/**
 * Sentinel marker for the content-slot placeholder host in the composed
 * tree. Every block whose `attributes[COMPOSED_CONTENT_SLOT_MARKER]` is
 * `true` is treated as the editable slot; its `innerBlocks` are the
 * user's real content blocks.
 */
export const COMPOSED_CONTENT_SLOT_MARKER = '__apVisualEditorContentSlot';

/** Block names treated as the composed view's content slot. */
const CONTENT_SLOT_BLOCK_NAMES = new Set<string>([
    'core/post-content',
    'core/post-title',
]);

const LOCKED_ATTRS = {
    lock: { move: true, remove: true, edit: true },
    [COMPOSED_CHROME_MARKER]: true as const,
};

/**
 * Build the composed tree. Returns a fresh block list; input trees are
 * never mutated.
 */
export function composeBlocks(
    contentBlocks: readonly BlockInstance[],
    template: AppliedTemplate
): BlockInstance[] {
    const templateWithParts = expandTemplateParts(
        template.blocks,
        template.template_parts
    );

    const { blocks: composed, slotFound } = injectContentSlot(
        templateWithParts,
        contentBlocks
    );

    // If the template has no `core/post-content` reachable, fall back to
    // appending the content list at the end so the author still sees
    // their work. Matches the umbrella issue's "close enough to see the
    // shape" guarantee.
    if (!slotFound) {
        return [
            ...composed.map(lockChromeBlock),
            wrapAsContentSlot(contentBlocks),
        ];
    }

    return composed.map(lockChromeBlock);
}

/**
 * Depth-first: replace every `core/template-part` block with the inlined
 * blocks of the referenced part. Refs whose slug is not in the parts map
 * render as an empty part shell (kept as a locked `core/template-part` in
 * place) — the same behavior the site editor uses for unresolved parts.
 */
function expandTemplateParts(
    blocks: readonly BlockInstance[],
    parts: Readonly<Record<string, AppliedTemplatePart>>
): BlockInstance[] {
    const out: BlockInstance[] = [];

    for (const block of blocks) {
        if (block.name === 'core/template-part') {
            const slug = readSlug(block);
            const part = slug !== null ? parts[slug] : undefined;

            if (part !== undefined) {
                // Expand the part's blocks in place of the ref.
                for (const nested of expandTemplateParts(part.blocks, parts)) {
                    out.push(nested);
                }

                continue;
            }
        }

        const expandedInner =
            block.innerBlocks.length > 0
                ? expandTemplateParts(block.innerBlocks, parts)
                : block.innerBlocks;

        out.push(
            expandedInner === block.innerBlocks
                ? block
                : { ...block, innerBlocks: expandedInner }
        );
    }

    return out;
}

function readSlug(block: BlockInstance): string | null {
    const attrs = block.attributes as { slug?: unknown } | undefined;
    const slug = attrs?.slug;

    return typeof slug === 'string' && slug.trim() !== '' ? slug.trim() : null;
}

/**
 * Walk the composed tree and replace the first content-slot block with a
 * live editable host whose `innerBlocks` are the actual content blocks.
 * Returns the mutated tree plus a `slotFound` flag so the caller can pick
 * a fallback rendering when the template omits a content slot.
 */
function injectContentSlot(
    blocks: readonly BlockInstance[],
    contentBlocks: readonly BlockInstance[]
): { blocks: BlockInstance[]; slotFound: boolean } {
    let slotFound = false;

    const walk = (list: readonly BlockInstance[]): BlockInstance[] =>
        list.map((block) => {
            if (slotFound) {
                return block;
            }

            if (CONTENT_SLOT_BLOCK_NAMES.has(block.name)) {
                slotFound = true;

                return replaceWithContentSlot(block, contentBlocks);
            }

            if (block.innerBlocks.length > 0) {
                const nextInner = walk(block.innerBlocks);

                return nextInner === block.innerBlocks
                    ? block
                    : { ...block, innerBlocks: nextInner };
            }

            return block;
        });

    return { blocks: walk(blocks), slotFound };
}

function replaceWithContentSlot(
    host: BlockInstance,
    contentBlocks: readonly BlockInstance[]
): BlockInstance {
    return {
        ...host,
        attributes: {
            ...host.attributes,
            [COMPOSED_CONTENT_SLOT_MARKER]: true,
        },
        innerBlocks: [...contentBlocks],
    };
}

function wrapAsContentSlot(contentBlocks: readonly BlockInstance[]): BlockInstance {
    return {
        clientId: '__composed_view_content_slot__',
        name: 'core/group',
        isValid: true,
        attributes: {
            [COMPOSED_CONTENT_SLOT_MARKER]: true,
        },
        innerBlocks: [...contentBlocks],
    } as unknown as BlockInstance;
}

/**
 * Stamp the lock + chrome marker onto every block that isn't the content
 * slot. Recurses only through blocks that aren't the slot — anything nested
 * inside the slot is user content and must stay editable.
 */
function lockChromeBlock(block: BlockInstance): BlockInstance {
    if (isContentSlotBlock(block)) {
        return block;
    }

    const nextAttrs = {
        ...block.attributes,
        ...LOCKED_ATTRS,
    };

    const nextInner =
        block.innerBlocks.length > 0
            ? block.innerBlocks.map(lockChromeBlock)
            : block.innerBlocks;

    return {
        ...block,
        attributes: nextAttrs,
        innerBlocks: nextInner,
    };
}

function isContentSlotBlock(block: BlockInstance): boolean {
    const attrs = block.attributes as Record<string, unknown> | undefined;

    return attrs?.[COMPOSED_CONTENT_SLOT_MARKER] === true;
}
