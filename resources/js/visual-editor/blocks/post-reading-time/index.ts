/**
 * Post Reading Time block entrypoint (#943).
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Dynamic block: server-side
 * `PostResolver` counts the post's words and stamps
 * `_resolvedReadingTime` (whole minutes), which the renderers print as
 * "{prefix}{minutes} {suffix}", e.g. "6 min read".
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
