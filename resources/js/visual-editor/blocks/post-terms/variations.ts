/**
 * Block variations for `artisanpack/post-terms` (#771).
 *
 * One variation per host-registered taxonomy so authors can drop a Post
 * Terms block pre-configured for Categories, Tags, or any custom
 * taxonomy straight from the inserter — matching WP core's
 * `core/post-terms` behaviour. The list comes from the
 * {@see getTaxonomies} runtime registry, which mirrors
 * `config('artisanpack.visual-editor.taxonomies')`.
 *
 * Each variation sets the `term` attribute to the taxonomy slug.
 * `isActive` matches on `term` so the inserter highlights the right
 * variation when an existing block is reopened. The `category`
 * variation is marked default so a bare Post Terms insertion resolves
 * to categories instead of the previously-unbound `term: undefined`
 * placeholder.
 */

import type { BlockConfiguration } from '@wordpress/blocks';

import { getTaxonomies, type TaxonomyDescriptor } from '../../editor/taxonomy-registry';

// A host may register a custom-only taxonomy set that omits `category`
// (e.g. a bookings site that only surfaces `venue` and `sport`). Without
// a fallback the resulting variation list has no `isDefault` entry, and
// a bare Post Terms insertion keeps `term: undefined` — the very bug
// #771 exists to close. Anchor the default to `category` when present,
// otherwise fall back to the first resolved taxonomy so the bare
// insertion always binds to something.
function resolveDefaultSlug( taxonomies: ReadonlyArray<TaxonomyDescriptor> ): string | null {
    if ( taxonomies.length === 0 ) {
        return null;
    }

    if ( taxonomies.some( ( taxonomy ) => taxonomy.slug === 'category' ) ) {
        return 'category';
    }

    return taxonomies[ 0 ]!.slug;
}

const taxonomies = getTaxonomies();
const defaultSlug = resolveDefaultSlug( taxonomies );

const variations: NonNullable<BlockConfiguration['variations']> = taxonomies.map(
    (taxonomy) => ({
        name: `term-${taxonomy.slug}`,
        title: taxonomy.label,
        description: `Display the ${taxonomy.label.toLowerCase()} terms assigned to the post.`,
        scope: ['inserter', 'transform'],
        attributes: {
            term: taxonomy.slug,
        },
        isActive: ['term'],
        keywords: [taxonomy.slug, taxonomy.label, taxonomy.plural],
        ...(taxonomy.slug === defaultSlug ? { isDefault: true } : {}),
    })
);

export default variations;
