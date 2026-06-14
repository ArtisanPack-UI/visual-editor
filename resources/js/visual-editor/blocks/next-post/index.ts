/**
 * Next Post block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Wraps inner blocks against the
 * next adjacent post; the server-side `PostResolver` swaps the post
 * context to the resolved adjacent post before the renderer walks the
 * inner tree. (#499)
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
