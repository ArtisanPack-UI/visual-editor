/**
 * Single Post Type Search Results block entrypoint (#502).
 *
 * Child of `artisanpack/post-types-search-results`. Each instance owns
 * a `postType` attribute; the renderers emit the wrapper + inner blocks
 * only when the host page's `?post_type=` parameter matches the
 * attribute (or when neither is set and the attribute is `all`).
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
