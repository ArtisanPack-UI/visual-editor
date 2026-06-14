/**
 * Tab section block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Child of
 * `artisanpack/tabs`; renders a single tab panel (#497).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

import '../tabs/tabs.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
