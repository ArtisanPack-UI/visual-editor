/**
 * Accordion title block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Grandchild of
 * `artisanpack/accordions`; the clickable header for a panel (#497).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
