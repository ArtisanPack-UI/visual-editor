/**
 * Button block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I3 — layout cluster
 * (issue #411). Child of `artisanpack/buttons`.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';
import icon from './inserter-icon';

import './button.css';

/**
 * Join two buttons' text on merge (#1043). Mirrors upstream's `merge`.
 */
function merge<T extends { text?: string }>(
    attributes: T,
    { text = '' }: { text?: string }
): T {
    return {
        ...attributes,
        text: (attributes.text || '') + text,
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
