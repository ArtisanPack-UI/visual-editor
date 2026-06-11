/**
 * Accordion panel block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Child of
 * `artisanpack/accordions`; renders a single collapsible panel (#497).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';

import './accordion.css';

export { edit, save, metadata };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
};
