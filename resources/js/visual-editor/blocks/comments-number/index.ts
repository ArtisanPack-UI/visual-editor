/**
 * Comments Number block entrypoint (#500).
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Dynamic block: the count is
 * stamped server-side by `PostResolver` as `_resolvedCommentCount`, and
 * the renderers combine it with the saved `singularCommentText` /
 * `pluralCommentText` labels.
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
