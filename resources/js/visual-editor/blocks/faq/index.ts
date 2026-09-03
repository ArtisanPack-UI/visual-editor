/**
 * FAQ block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Dedicated FAQ block with
 * question/answer semantics + FAQPage JSON-LD (#758).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

import './faq.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
