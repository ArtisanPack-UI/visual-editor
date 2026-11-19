/**
 * Carousel slide block entrypoint (#1127).
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Child of
 * `artisanpack/carousel`; holds one slide's blocks.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

import '../carousel/carousel.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
