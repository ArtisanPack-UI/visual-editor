/**
 * ReadMore block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I-Block-Fork —
 * post navigation / metadata family (#520).
 *
 * Server-rendered display block: `edit` previews the configured `content`
 * attribute as a styled chip via `createEntityPlaceholderEdit`; the actual
 * link href is produced server-side from the stamped `_resolvedPermalink`.
 * Upstream has no `deprecated.js` (read-more shipped with only one revision
 * and no `textAlign` legacy).
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
