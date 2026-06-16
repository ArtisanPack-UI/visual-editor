/**
 * Post Variant block entrypoint (#591).
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Child block of
 * `artisanpack/post-template`: declares an override template that the
 * server-side `QueryInliner` swaps in for posts matching its rule.
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
