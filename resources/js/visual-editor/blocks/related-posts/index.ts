/**
 * Related Posts block entrypoint (#501).
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Dynamic block: server-side
 * `QueryInliner` resolves N related posts via `QueryResolverContract`
 * (using the host post's terms) and clones the inner-block tree once per
 * result with `_resolved*` stamps applied through `PostResolver`.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';
import '../_shared/social-icons.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
