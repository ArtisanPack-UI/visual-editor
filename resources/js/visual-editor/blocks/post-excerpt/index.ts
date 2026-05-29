/**
 * PostExcerpt block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I5 — entity cluster
 * (issue #413).
 *
 * Server-rendered display block: `edit` previews via the lightweight
 * `createEntityPlaceholderEdit` helper (see
 * `../_shared/entity-placeholder-edit.tsx`) — it renders the stamped
 * `_resolved*` value when present, otherwise a clearly-labelled
 * placeholder. Upstream's entity-querying edit is intentionally not
 * delegated because the post editor's `@wordpress/core-data` shim does
 * not expose the page/site entity; the front-end markup is produced
 * server-side from the stamped `_resolved*` attributes.
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
