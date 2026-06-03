/**
 * TermDescription block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I-Block-Fork —
 * post navigation / metadata family (#520).
 *
 * Server-rendered display block: `edit` previews via
 * `createEntityPlaceholderEdit`, reading the stamped
 * `_resolvedTermDescription` attribute when present and otherwise
 * rendering a clearly-labelled placeholder. The block reads its term
 * identity from `termId` / `taxonomy` block context, which the post
 * editor doesn't populate — the live-entity-fetch path is intentionally
 * absent for the same reason.
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
