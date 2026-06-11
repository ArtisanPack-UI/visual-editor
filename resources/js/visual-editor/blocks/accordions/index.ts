/**
 * Accordions block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Parent block of the accordion
 * family (#497).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';

import '../accordion/accordion.css';

export { edit, save, metadata };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
};
