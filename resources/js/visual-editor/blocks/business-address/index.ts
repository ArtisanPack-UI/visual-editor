/**
 * Business Address block entrypoint (#761).
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Server-rendered dynamic
 * display block. Address + optional map embed are stamped onto
 * `_resolvedBusinessInfo` by BusinessInfoResolver from the host-supplied
 * `ap.visualEditor.businessInfo` filter; the resolver also composes the
 * map embed URL (OSM by default, Google Maps when a Maps API key is
 * configured) so the renderers stay declarative.
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
