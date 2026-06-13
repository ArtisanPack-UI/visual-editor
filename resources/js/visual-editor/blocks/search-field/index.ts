/**
 * Search Field block entrypoint (#502).
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Dynamic block: `edit` previews
 * an empty input so authors see the label + placeholder, and the real
 * markup is emitted by the Blade / React / Vue renderers at request
 * time (the input value comes from the current `s` query parameter so
 * the host page can pre-fill it).
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
