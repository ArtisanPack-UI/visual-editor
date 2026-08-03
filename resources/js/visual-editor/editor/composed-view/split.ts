/**
 * Split an applied template's block list into the chrome that renders
 * before the content slot (header) and the chrome that renders after
 * (footer), with `core/template-part` refs expanded inline from the
 * parts map.
 *
 * Used by the composed view's inert chrome preview panels — see
 * `editor-app.tsx` and `ChromePreviewPanel.tsx` for why we render
 * chrome outside the block canvas instead of composing a single tree.
 *
 * @since 1.1.0
 */

import type { BlockInstance } from '@wordpress/blocks';

import type { AppliedTemplatePart } from './api';

const CONTENT_SLOT_NAME = 'core/post-content';

export interface SplitTemplateResult {
    header: readonly BlockInstance[];
    footer: readonly BlockInstance[];
    templateName: string;
}

export function splitTemplateAroundContentSlot(
    blocks: readonly BlockInstance[],
    parts: Readonly<Record<string, AppliedTemplatePart>>,
    templateName: string
): SplitTemplateResult {
    const expanded = expandParts(blocks, parts);
    const header: BlockInstance[] = [];
    const footer: BlockInstance[] = [];
    let seenSlot = false;

    for (const block of expanded) {
        if (!seenSlot && block.name === CONTENT_SLOT_NAME) {
            seenSlot = true;
            continue;
        }

        if (seenSlot) {
            footer.push(block);
        } else {
            header.push(block);
        }
    }

    // No slot in the template — treat the whole thing as header chrome
    // so authors still see what the template contains. Frontend fallback
    // is a separate concern (see the umbrella issue's default-template
    // path).
    if (!seenSlot) {
        return { header: expanded, footer: [], templateName };
    }

    return { header, footer, templateName };
}

/**
 * Inline `core/template-part` refs from the parts map.
 *
 * `visited` carries the slugs currently being expanded on this branch so a
 * self- or mutually-referencing part terminates instead of recursing until
 * the stack blows. Template parts are theme/DB content, so they can't be
 * assumed acyclic — the backend's own collector guards the same way, but it
 * still ships the circular parts in the flat map it returns. A ref whose
 * slug is already on the branch is dropped rather than re-expanded; the
 * cycle has nothing new to contribute to the preview.
 *
 * The set is passed by value per branch (not mutated in place) so sibling
 * refs to the same shared part still expand normally.
 */
function expandParts(
    blocks: readonly BlockInstance[],
    parts: Readonly<Record<string, AppliedTemplatePart>>,
    visited: ReadonlySet<string> = new Set()
): BlockInstance[] {
    const out: BlockInstance[] = [];

    for (const block of blocks) {
        if (block.name === 'core/template-part') {
            const attrs = block.attributes as { slug?: unknown } | undefined;
            const slug = typeof attrs?.slug === 'string' ? attrs.slug.trim() : '';
            const part = slug !== '' ? parts[slug] : undefined;

            if (part !== undefined) {
                if (visited.has(slug)) {
                    continue;
                }

                const nextVisited = new Set(visited).add(slug);

                for (const nested of expandParts(part.blocks, parts, nextVisited)) {
                    out.push(nested);
                }
                continue;
            }
        }

        out.push(block);
    }

    return out;
}
