/**
 * Social Icon block entrypoint (#1023).
 *
 * Auto-discovered by `editor/custom-blocks.ts`. A child of
 * `artisanpack/social-links`; each inserter variation is one service.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';
import getSocialLinkVariations from './variations';

const variations = getSocialLinkVariations();

export { edit, save, metadata, icon, variations };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
    variations,
};
