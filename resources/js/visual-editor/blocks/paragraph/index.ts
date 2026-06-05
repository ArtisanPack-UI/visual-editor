/**
 * Paragraph block entrypoint.
 *
 * Re-exports `edit`, `save`, `metadata`, `deprecated`, `transforms`, and
 * `icon` so the host JS bundle (via the custom-block auto-discovery glob —
 * see `../../editor/custom-blocks.ts`) can register the block with
 * `@wordpress/blocks.registerBlockType`.
 *
 * Pilot fork target for V2 block-fork phase I0 (issue #408).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';
import icon from './inserter-icon';

import './paragraph.css';

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
