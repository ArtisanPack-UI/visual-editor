/**
 * Business Hours block entrypoint (#761).
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Server-rendered dynamic
 * display block: `edit` shows a placeholder / stub so authors see the
 * block in the canvas, and the real weekly hours + special-hours
 * overrides are produced at runtime by the Blade / React / Vue renderers
 * from the server-stamped `_resolvedBusinessInfo` attribute (populated
 * by BusinessInfoResolver from the host-supplied
 * `ap.visualEditor.businessInfo` filter).
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
