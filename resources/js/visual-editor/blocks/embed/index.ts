/**
 * Embed block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I2 — media cluster
 * (issue #410).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';
import variations from './variations';
import icon from './inserter-icon';

import './embed.css';

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
