/**
 * Query Loop block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I6 — loop / feed cluster
 * (issue #414).
 *
 * The saved tree is expanded server-side by `QueryInliner` (one clone of
 * the inner `artisanpack/post-template` per resolved post) and rendered by
 * the renderer packages' thin `QueryBlock` wrapper; the editor previews the
 * first matching post via the `useQueryPreview` hook. Upstream's
 * variations + deprecation chain are intentionally not carried — see
 * `upstream-state.json`.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import transforms from './transforms';
import icon from './inserter-icon';

export { edit, save, metadata, icon, transforms };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
    transforms,
};
