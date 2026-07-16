/**
 * Snippet block entrypoint.
 *
 * @since 1.4.0
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';

export { edit, save, metadata };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
};
