/**
 * Search block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I4 — widgets cluster
 * (issue #412).
 *
 * `core/search` is rendered outside `save` (server-interactive), so the
 * fork omits a real save (returns `null`) and the front-end Blade/React/Vue
 * renderers build the markup from the persisted attributes — reusing the
 * `core/search` renderers, which carry the #338 button-icon a11y fix.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';
import variations from './variations';
import icon from './inserter-icon';

import './search.css';

export { edit, save, metadata, icon, deprecated, transforms, variations };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
    deprecated,
    transforms,
    variations,
};
