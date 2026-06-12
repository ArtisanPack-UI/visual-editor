/**
 * Search Filters Taxonomy block entrypoint (#502).
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Dynamic block:
 * renderers populate the `<select>` from the host's stamped
 * `_resolvedTerms` attribute and pre-select the queried term from the
 * current request.
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
