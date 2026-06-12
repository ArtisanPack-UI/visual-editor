/**
 * Author Social Icons block entrypoint (#501).
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Dynamic block: server-side
 * `PostResolver` reads the post author's stored social profile URLs and
 * stamps `_resolvedAuthorSocialLinks` so the renderers can emit the
 * chip list without per-renderer author lookups.
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
