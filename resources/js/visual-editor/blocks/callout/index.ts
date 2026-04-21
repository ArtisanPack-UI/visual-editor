/**
 * Callout block entrypoint.
 *
 * Re-exports `edit`, `save`, and `metadata` so the host JS bundle (via the
 * custom block auto-discovery glob — see `../../editor/custom-blocks.ts`)
 * can register the block with `@wordpress/blocks.registerBlockType`.
 *
 * Also exports an `icon` SVG for the block-library inserter. The editor
 * does not bundle `dashicons.css`, so a dashicon-slug icon in `block.json`
 * would render as a blank square. Providing the inline SVG here bypasses
 * the Dashicon pipeline entirely.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

import './callout.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
