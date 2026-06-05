/**
 * Heading block entrypoint.
 *
 * Re-exports `edit`, `save`, `metadata`, `deprecated`, `transforms`, and
 * `icon` so the host JS bundle (via the custom-block auto-discovery glob —
 * see `../../editor/custom-blocks.ts`) can register the block with
 * `@wordpress/blocks.registerBlockType`.
 *
 * Fork target: V2 block-fork phase I1 (content cluster, issue #409).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';
import icon from './inserter-icon';

import './heading.css';

export { edit, save, metadata, icon, deprecated, transforms };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
    deprecated,
    transforms,
};
