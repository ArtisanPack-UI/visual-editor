/**
 * Breadcrumbs block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Server-rendered display block
 * (#496): `edit` previews a stub trail so authors see the chosen
 * separator immediately, and the real trail is produced at runtime by
 * the renderers from the stamped `_resolvedTrail` attribute. No
 * transforms are registered.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

import './breadcrumbs.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
