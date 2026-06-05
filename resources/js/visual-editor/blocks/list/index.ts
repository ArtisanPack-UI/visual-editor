/**
 * List block entrypoint.
 *
 * Fork target: V2 block-fork phase I1 (content cluster, issue #409).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';
import icon from './inserter-icon';

import './list.css';

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
