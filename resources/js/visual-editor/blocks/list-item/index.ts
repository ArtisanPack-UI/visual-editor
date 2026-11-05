/**
 * List item block entrypoint.
 *
 * Fork target: V2 block-fork phase I1 (content cluster, issue #409).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';
import icon from './inserter-icon';

import './list-item.css';

/**
 * Join two list items' content on merge (#1043). Mirrors upstream's `merge`.
 */
function merge<T extends { content: string }>(
    attributes: T,
    attributesToMerge: { content: string }
): T {
    return {
        ...attributes,
        content: attributes.content + attributesToMerge.content,
    };
}

export { edit, save, metadata, icon, deprecated, transforms, merge };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
    deprecated,
    transforms,
    merge,
};
