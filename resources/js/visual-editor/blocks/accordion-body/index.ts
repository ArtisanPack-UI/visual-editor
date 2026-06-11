/**
 * Accordion body block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Grandchild of
 * `artisanpack/accordions`; the expandable content of a panel (#497).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';

export { edit, save, metadata };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
};
