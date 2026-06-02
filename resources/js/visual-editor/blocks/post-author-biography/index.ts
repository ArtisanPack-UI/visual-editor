/**
 * PostAuthorBiography block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Author family fork (#518) —
 * recommended replacement for the deprecated `core/post-author`.
 *
 * Server-rendered display block: `edit` previews via the lightweight
 * `createEntityPlaceholderEdit` helper (see
 * `../_shared/entity-placeholder-edit.tsx`) — it renders the stamped
 * `_resolvedAuthorBio` value when present, otherwise a clearly-labelled
 * placeholder. Upstream's entity-querying edit is intentionally not
 * delegated because the post editor's `@wordpress/core-data` shim does
 * not expose the user entity; the front-end markup is produced
 * server-side from the stamped `_resolvedAuthorBio` attribute.
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
