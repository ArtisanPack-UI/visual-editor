/**
 * Tabs block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Parent block of the
 * tabs family (#497).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';

import './tabs.css';

export { edit, save, metadata };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
};
