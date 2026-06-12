/**
 * Single Content block entrypoint (#501).
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Container block: the inner-block
 * tree previews against the post the editor canvas already has in scope;
 * server-side `QueryInliner` resolves the chosen post via
 * `QueryResolverContract` and re-stamps the inner tree against it via
 * `PostResolver` so the rendered output reflects the selected entry.
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
