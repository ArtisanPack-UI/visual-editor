/**
 * QueryPagination block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I-Block-Fork query
 * family (#521).
 *
 * Ancestor-locked under `artisanpack/query`. Wrapper for the next /
 * previous / numbers leaves; the inner-block tree is serialized
 * verbatim by save() and the front-end pagination metadata is
 * resolved server-side by `QueryInliner`.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import transforms from './transforms';
import icon from './inserter-icon';

import './query-pagination.css';

export { edit, save, metadata, icon, transforms };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
    transforms,
};
