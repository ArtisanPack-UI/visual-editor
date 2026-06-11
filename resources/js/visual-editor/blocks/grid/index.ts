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

import './grid.css';

export { edit, save, metadata };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
};
