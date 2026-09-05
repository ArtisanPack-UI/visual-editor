/**
 * Table of Contents block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Derives its item list at render
 * time from the page's headings — the editor-side preview surfaces a
 * placeholder because the full page tree is not available in the block
 * canvas (#760).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

import './toc.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
