/**
 * Grid block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Parent block of the grid
 * family (#498).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

import './grid.css';
import '../_shared/photo-grid/photo-grid.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
