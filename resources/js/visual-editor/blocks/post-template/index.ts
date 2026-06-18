/**
 * Post Template block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I6 — loop / feed cluster
 * (issue #414).
 *
 * Parent-locked child of `artisanpack/query`. The per-iteration markup is
 * produced by the server-side `QueryInliner` pre-pass (one clone of the
 * template subtree per resolved post) and the renderer packages' thin
 * `PostTemplateBlock` wrapper; the editor previews the first result via the
 * wrapping query block's `BlockContextProvider`.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import transforms from './transforms';
import icon from './inserter-icon';

import './post-template.css';
import '../_shared/masonry/masonry.css';

export { edit, save, metadata, icon, transforms };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
    transforms,
};
