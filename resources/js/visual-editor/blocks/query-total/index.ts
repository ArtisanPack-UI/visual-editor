/**
 * QueryTotal block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Prints the surrounding query's
 * result count ("25 results") or the current page's range ("1–9 of 25
 * results") — the equivalent of WordPress 6.8's `core/query-total`
 * (#962).
 *
 * Ancestor-locked to `artisanpack/query`. Server-rendered leaf — the
 * `_resolvedTotal` / page snapshot is stamped by `QueryInliner` on the
 * way into the renderer.
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
