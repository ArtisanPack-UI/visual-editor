/**
 * Grid Item block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Child of
 * `artisanpack/grid`; renders a single cell with per-breakpoint span
 * controls (#498).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

import '../grid/grid.css';
import '../../../../css/flex-layout.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
