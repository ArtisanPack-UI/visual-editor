/**
 * QueryNoResults block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I-Block-Fork query
 * family (#521).
 *
 * Ancestor-locked under `artisanpack/query`. The wrapper's inner-block
 * tree is serialized verbatim by save(); the server-side `QueryInliner`
 * drops the wrapper from the rendered tree when the surrounding query
 * resolves to one or more posts and keeps it (so the empty-state markup
 * renders) when the result set is empty.
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
