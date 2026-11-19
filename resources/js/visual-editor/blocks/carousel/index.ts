/**
 * Carousel block entrypoint (#1127).
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Parent block of the
 * carousel family; its slides are `artisanpack/carousel-slide` blocks.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

import './carousel.css';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
