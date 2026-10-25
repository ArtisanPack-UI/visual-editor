/**
 * Social Icons context stamp — React renderer (#1023).
 *
 * The editor hands a Social Icons block's settings to its children
 * through block context; the renderer has no context, so the parent's
 * settings are stamped onto each `artisanpack/social-link` child as a
 * `_veSocialLinks` attribute before rendering. Mirrors
 * `BlockRenderer::stampSocialLinksContext()` in the Blade renderer: a
 * child's own `_veSocialLinks` is always overwritten, so stored content
 * can't forge one.
 */

import type { Block } from '../types';

export interface SocialLinksContext {
    readonly openInNewTab: boolean;
    readonly showLabels: boolean;
}

function contextFor(attributes: Record<string, unknown>): SocialLinksContext {
    return {
        openInNewTab: attributes.openInNewTab === true,
        showLabels: attributes.showLabels === true,
    };
}

function stampBlock(block: Block): Block {
    const innerBlocks = Array.isArray(block.innerBlocks) ? block.innerBlocks.map(stampBlock) : block.innerBlocks;

    if (block.name !== 'artisanpack/social-links' || !Array.isArray(innerBlocks)) {
        return innerBlocks === block.innerBlocks ? block : { ...block, innerBlocks };
    }

    const context = contextFor(block.attributes ?? {});

    return {
        ...block,
        innerBlocks: innerBlocks.map((child) =>
            child.name === 'artisanpack/social-link'
                ? { ...child, attributes: { ...(child.attributes ?? {}), _veSocialLinks: context } }
                : child
        ),
    };
}

export function stampSocialLinksContext(tree: Block[]): Block[] {
    return tree.map(stampBlock);
}
