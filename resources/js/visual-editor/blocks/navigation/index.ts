/**
 * Navigation block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I5 — entity cluster
 * (issue #413).
 *
 * Server-rendered entity block: `edit` delegates to the registered
 * `core/navigation` edit (see `../_shared/forked-entity-edit.tsx`) so the
 * fork inherits the upstream + V1 editor surface and reads entity data
 * through the same `@wordpress/core-data` shim selectors.
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
