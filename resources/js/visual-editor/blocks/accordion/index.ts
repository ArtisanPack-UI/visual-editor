/**
 * Accordion panel block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Child of
 * `artisanpack/accordions`; renders a single collapsible panel (#497).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

import './accordion.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
