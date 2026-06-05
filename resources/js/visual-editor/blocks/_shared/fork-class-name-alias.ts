/**
 * Fork class-name alias filter.
 *
 * `@wordpress/blocks.getBlockDefaultClassName(name)` derives a wrapper
 * class from the block name by stripping the `core/` prefix and replacing
 * the slash with a hyphen — so `core/table` → `wp-block-table`, and
 * `artisanpack/table` → `wp-block-artisanpack-table`.
 *
 * Every CSS rule in `@wordpress/block-library`'s stylesheet (which is
 * bundled into our gutenberg chunk) targets the *upstream* class
 * (`.wp-block-table`, `.wp-block-heading`, …). The I1 content-cluster
 * forks emit byte-equivalent saved markup to their `core/*` counterparts
 * — but without this filter their wrapper element gets a different class
 * and none of the upstream CSS applies, so e.g. table cells render at 0×0
 * and look like blank space in the editor.
 *
 * The filter remaps the default class for each I1 fork to its upstream
 * equivalent. The wrapper still gets an `artisanpack`-namespaced class
 * additively (block-editor adds both), so per-fork CSS overrides remain
 * possible — but the base rules from upstream "just work".
 *
 * Scoped to a known whitelist of fork slugs so we never accidentally
 * collide with a block that genuinely needs an `artisanpack`-only class
 * (e.g. `artisanpack/callout`).
 */

import { addFilter } from '@wordpress/hooks';

const I1_FORK_SLUGS: ReadonlyArray<string> = [
    // I1 content cluster
    'heading',
    'list',
    'list-item',
    'quote',
    'code',
    'preformatted',
    'pullquote',
    'verse',
    'table',
    // I2 media cluster
    'image',
    'gallery',
    'video',
    'audio',
    'file',
    'embed',
    'cover',
    'media-text',
];

const FORK_NAMESPACE_PREFIX = 'artisanpack/';

let registered = false;

export function registerForkClassNameAlias(): void {
    if (registered) {
        return;
    }
    registered = true;

    addFilter(
        'blocks.getBlockDefaultClassName',
        'artisanpack/visual-editor/fork-class-name-alias',
        (className: string, blockName: string) => {
            if (!blockName.startsWith(FORK_NAMESPACE_PREFIX)) {
                return className;
            }
            const slug = blockName.slice(FORK_NAMESPACE_PREFIX.length);
            if (!I1_FORK_SLUGS.includes(slug)) {
                return className;
            }
            return `wp-block-${slug}`;
        }
    );
}
