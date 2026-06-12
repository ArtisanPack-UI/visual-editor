/**
 * Copyright block entrypoint (#500).
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Dynamic block: `edit` previews
 * the resolved line with the current year so authors see the chosen
 * type immediately, and `save` returns `null` so renderers produce the
 * real markup from the saved attributes at render time.
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
