/**
 * Loginout block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I-Block-Fork auth (#522).
 *
 * Standalone block (no ancestor lock) — loginout is placed wherever a
 * theme wants to surface an auth-state link (header, sidebar, footer).
 * Server-rendered leaf — `_resolvedIsUserLoggedIn` and the matching
 * login / logout URLs are stamped against the current viewer's auth
 * state at render time.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import transforms from './transforms';
import icon from './inserter-icon';

export { edit, save, metadata, icon, transforms };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
    transforms,
};
