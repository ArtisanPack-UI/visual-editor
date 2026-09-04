/**
 * Business Email block entrypoint (#761).
 *
 * Auto-discovered by `editor/custom-blocks.ts`. Server-rendered dynamic
 * display block: the email address arrives on `_resolvedBusinessInfo`
 * from BusinessInfoResolver + the host's `ap.visualEditor.businessInfo`
 * filter. Renderers wrap the address in a `mailto:` link.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import icon from './inserter-icon';

export { edit, save, metadata, icon };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
};
