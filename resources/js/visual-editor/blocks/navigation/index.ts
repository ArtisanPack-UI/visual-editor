/**
 * Deprecated `artisanpack/navigation` entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`.
 *
 * The block was forked in Phase I5 (#413) and reverted to `core/navigation`
 * in #808. What remains is a stub whose only job is to keep persisted
 * `<!-- wp:artisanpack/navigation ... -->` markup valid on parse and swap
 * itself for the upstream `core/navigation` block on mount. See `edit.tsx`.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';

// #814 — the block-gap default applies to the live `core/navigation`
// block this stub migrates to; it rides along here so the auto-discovered
// block stylesheets carry it into both canvas surfaces.
import './navigation.css';

export { edit, save, metadata };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
};
