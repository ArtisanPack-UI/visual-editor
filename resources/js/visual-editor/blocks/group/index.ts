/**
 * Group block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I3 — layout cluster
 * (issue #411). Also exports the `group`, `row`, and `stack`
 * variations (the upstream `grid` variation is excluded; see
 * `variations.ts` for details).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';
import variations from './variations';
import icon from './inserter-icon';

import './group.css';

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
