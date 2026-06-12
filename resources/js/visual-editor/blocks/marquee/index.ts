/**
 * Marquee block entrypoint (#500).
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Static block — `save` emits
 * the same `<div><p>` shell the renderers produce, so the saved markup
 * round-trips through Gutenberg's `source: 'html'` parser without a
 * deprecation chain.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

import './marquee.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
