/**
 * Block variations for `artisanpack/single-content` (#501).
 *
 * One variation per cms-framework-registered content type so authors
 * can drop a Single Content block pre-configured for posts, pages, or
 * any custom resource straight from the inserter. The list comes from
 * the {@see getContentTypes} runtime registry, which mirrors
 * `config('artisanpack.visual-editor.resources')`.
 *
 * Each variation sets the `postType` attribute (and re-zeros `postId`
 * so authors always pick a fresh entry from the dropdown). `isActive`
 * matches on `postType` so the inserter highlights the right variation
 * when an existing block is reopened.
 */

import type { BlockConfiguration } from '@wordpress/blocks';

import { getContentTypes } from '../../editor/content-type-registry';

const variations: NonNullable<BlockConfiguration['variations']> = getContentTypes().map(
    (type) => ({
        name: `single-${type.slug}`,
        title: `Single ${type.label}`,
        description: `Render a single ${type.label.toLowerCase()} and scope the inner blocks to it.`,
        scope: ['inserter', 'transform'],
        attributes: {
            postType: type.slug,
            postId: 0,
        },
        isActive: ['postType'],
        keywords: [type.slug, type.plural],
    })
);

export default variations;
