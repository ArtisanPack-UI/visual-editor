/**
 * QueryTitle block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I-Block-Fork query
 * family (#521).
 *
 * Standalone block (no ancestor lock) — query-title is placed outside
 * the loop in upstream Gutenberg, typically inside an archive
 * template header. Server-rendered leaf — `_resolvedQueryTitle` is
 * stamped by `QueryInliner` on the way into the renderer.
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
