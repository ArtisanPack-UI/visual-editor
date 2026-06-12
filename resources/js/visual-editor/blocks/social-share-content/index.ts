/**
 * Social Share Content block entrypoint (#501).
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Dynamic block: server-side
 * `PostResolver` reads the current post's URL, title, and featured
 * image, then stamps `_resolvedShareLinks` with the per-platform share
 * URLs so the renderers do not have to know about share-URL syntax.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';
import '../_shared/social-icons.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
