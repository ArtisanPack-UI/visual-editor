/**
 * Search Filters block entrypoint (#502).
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Wraps a set of inner
 * filter controls inside a single GET form that posts back to the host
 * site, scoped to the configured post type via a hidden input.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
