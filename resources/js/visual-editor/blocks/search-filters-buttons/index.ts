/**
 * Search Filters Buttons block entrypoint (#502).
 *
 * Submit + reset pair for the surrounding `artisanpack/search-filters`
 * form. Renderers emit `<input type="submit">` + `<input type="reset">`
 * pairs at request time.
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
