/**
 * Post Types Search Results block entrypoint (#502).
 *
 * Container for one or more `artisanpack/single-post-types-search-results`
 * children. Each child shows only when its `postType` attribute matches
 * the current `?post_type=…` query parameter.
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
