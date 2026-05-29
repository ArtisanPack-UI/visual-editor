/**
 * Archives block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I6 — loop / feed cluster
 * (issue #414).
 *
 * Dynamic block: `save` returns `null` and the markup is produced server
 * side by `ArchivesBlock::render()`; the editor previews it through the
 * package's `<ServerSideRender>` seam.
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
