/**
 * Form block entrypoint.
 *
 * Re-exports `edit`, `save`, `metadata`, and `icon` so the host JS
 * bundle (via the custom block auto-discovery glob — see
 * `../../editor/custom-blocks.ts`) can register the block with
 * `@wordpress/blocks.registerBlockType`.
 *
 * Server-side rendering is handled by `FormBlock::render()` in the PHP
 * service provider; this module only owns the editor experience.
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
