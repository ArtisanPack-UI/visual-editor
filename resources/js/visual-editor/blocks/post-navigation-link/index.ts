/**
 * PostNavigationLink block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I-Block-Fork —
 * post navigation / metadata family (#520).
 *
 * Server-rendered display block: `edit` previews via a thin wrapper around
 * `createEntityPlaceholderEdit` that synthesizes the `_resolvedAdjacent*`
 * preview from the block's own `label` / `arrow` / `type` attributes (the
 * adjacent post identity isn't part of the editor's `artisanpack/postPreview`
 * payload). Front-end markup is produced server-side from the stamped
 * `_resolvedAdjacent*` attributes.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';
import icon from './inserter-icon';

export { edit, save, metadata, icon, deprecated, transforms };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
    deprecated,
    transforms,
};
