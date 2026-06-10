/**
 * Icon block entrypoint.
 *
 * Phase 1 of the Icon Block feature (#552, parent #494).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

import './icon.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
