/**
 * Verse block entrypoint.
 *
 * Fork target: V2 block-fork phase I1 (content cluster, issue #409).
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';
import icon from './inserter-icon';

import './verse.css';

/**
 * Join two blocks' content with a blank line on merge (#1043). Mirrors
 * upstream's `merge`.
 */
function merge(
    attributes: { content?: string },
    attributesToMerge: { content?: string }
): { content: string } {
    return {
        content: attributes.content + '\n\n' + attributesToMerge.content,
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
