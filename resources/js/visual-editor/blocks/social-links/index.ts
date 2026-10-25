/**
 * Social Icons block entrypoint (#1023).
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Holds
 * `artisanpack/social-link` children and passes its settings to them
 * through block context.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';
import './social-links.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
